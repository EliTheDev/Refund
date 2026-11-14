<?php

namespace refund\menu;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\Inventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use refund\model\Refund;
use refund\Main;

class RefundMenu extends InvMenu
{
    /** @var array<int, true> */
    private static array $activeClaims = [];

    private bool $readonly = false;
    private bool $madeProgress = false;
    private string $issuedBy = "";
    private string $receivedBy = "";

    public function __construct(private Refund $refund)
    {
        parent::__construct(InvMenuHandler::getTypeRegistry()->get(InvMenu::TYPE_DOUBLE_CHEST));

        foreach ($refund->getItem("inventory") as $slot => $item) {
            if ($item instanceof Item) {
                $this->getInventory()->setItem((int) $slot, $item);
            }
        }

        foreach ($refund->getItem("armor") as $slot => $item) {
            if ($item instanceof Item && $slot >= 0 && $slot <= 3) {
                $this->getInventory()->setItem(46 + $slot, $item);
            }
        }

        foreach ($refund->getItem("offHand") as $item) {
            if ($item instanceof Item) {
                $this->getInventory()->setItem(53, $item);
            }
        }

        if ($refund->getExperience() > 0) {
            $experienceItem = VanillaItems::EXPERIENCE_BOTTLE()
                ->setCustomName(TextFormat::colorize("&aXP: " . number_format($refund->getExperience())))
                ->setLore([TextFormat::colorize("&7Click to claim this experience.")]);
            $experienceItem->getNamedTag()->setByte("refund_experience", 1);
            $this->getInventory()->setItem(50, $experienceItem);
        }

        $pane = $this->createPlaceholderPane();
        for ($slot = 0; $slot < 54; $slot++) {
            if ($this->getInventory()->getItem($slot)->isNull()) {
                $this->getInventory()->setItem($slot, clone $pane);
            }
        }

        $this->setListener($this->getListener());
    }

    private function createPlaceholderPane(): Item
    {
        $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY)->asItem()->setCustomName(TextFormat::colorize("&r"))->setLore([""]);
        $pane->getNamedTag()->setByte("refund_placeholder", 1);
        return $pane;
    }

    public function setReadonly(bool $readonly): self
    {
        $this->readonly = $readonly;
        return $this;
    }

    public function openClaim(Player $recipient, string $issuedBy): bool
    {
        $refundId = $this->refund->getId();
        if ($this->refund->isRefunded()) {
            $recipient->sendMessage(Main::PREFIX . "This refund has already been fully claimed.");
            return false;
        }
        if (!self::reserveClaim($refundId)) {
            $recipient->sendMessage(Main::PREFIX . "This refund is already open for claiming.");
            return false;
        }

        $this->issuedBy = $issuedBy;
        $this->receivedBy = $recipient->getName();
        $this->setInventoryCloseListener(function (Player $player, Inventory $inventory): void {
            try {
                if (!$this->readonly && $this->madeProgress) {
                    $this->saveRemainingContents($this->refund->getExperience());
                }
            } finally {
                self::releaseClaim($this->refund->getId());
            }
        });

        $this->send($recipient, null, function (bool $success) use ($recipient, $refundId): void {
            if (!$success) {
                self::releaseClaim($refundId);
                $recipient->sendMessage(Main::PREFIX . "Unable to open the refund chest.");
            }
        });
        return true;
    }

    public static function reserveClaim(int $refundId): bool
    {
        if (isset(self::$activeClaims[$refundId])) {
            return false;
        }
        self::$activeClaims[$refundId] = true;
        return true;
    }

    public static function releaseClaim(int $refundId): void
    {
        unset(self::$activeClaims[$refundId]);
    }

    public function getName(): ?string
    {
        return TextFormat::colorize("&8Refund ID: #" . $this->refund->getId());
    }

    public function getListener(): \Closure
    {
        return function (InvMenuTransaction $transaction): InvMenuTransactionResult {
            if ($this->readonly) {
                $transaction->getPlayer()->sendMessage(Main::PREFIX . "Preview mode: read-only.");
                return $transaction->discard();
            }
            if ($this->refund->isRefunded()) {
                return $transaction->discard();
            }

            $item = $transaction->getItemClicked();
            if ($item->getNamedTag()->getTag("refund_placeholder") !== null) {
                return $transaction->discard();
            }
            if ($item->getNamedTag()->getTag("refund_experience") !== null) {
                $this->claimExperience($transaction->getPlayer());
                return $transaction->discard();
            }

            $itemAfterClick = $transaction->getItemClickedWith();
            if ($item->isNull() || (!$itemAfterClick->isNull() && (!$item->canStackWith($itemAfterClick) || $itemAfterClick->getCount() >= $item->getCount()))) {
                return $transaction->discard();
            }

            return $transaction->continue()->then(function (Player $player): void {
                $this->madeProgress = true;
                $this->saveRemainingContents($this->refund->getExperience());
            });
        };
    }

    private function claimExperience(Player $recipient): void
    {
        $experience = $this->refund->getExperience();
        if ($experience <= 0) {
            return;
        }

        $this->madeProgress = true;
        $this->getInventory()->setItem(50, VanillaItems::AIR());
        $this->saveRemainingContents(0);
        $recipient->getXpManager()->addXp($experience);
        $recipient->sendMessage(Main::PREFIX . "You received " . number_format($experience) . " XP.");
    }

    private function saveRemainingContents(int $experience): void
    {
        $items = [
            "inventory" => [],
            "armor" => [],
            "offHand" => [],
        ];
        for ($slot = 0; $slot < 36; $slot++) {
            $item = $this->getInventory()->getItem($slot);
            if (!$item->isNull() && $item->getNamedTag()->getTag("refund_placeholder") === null) {
                $items["inventory"][$slot] = $item;
            }
        }
        for ($slot = 0; $slot < 4; $slot++) {
            $item = $this->getInventory()->getItem(46 + $slot);
            if (!$item->isNull() && $item->getNamedTag()->getTag("refund_placeholder") === null) {
                $items["armor"][$slot] = $item;
            }
        }
        $offHand = $this->getInventory()->getItem(53);
        if (!$offHand->isNull() && $offHand->getNamedTag()->getTag("refund_placeholder") === null) {
            $items["offHand"][0] = $offHand;
        }

        Main::getInstance()->getRefundService()->saveClaimProgress(
            $this->refund,
            $items,
            $experience,
            $this->issuedBy,
            $this->receivedBy
        );
    }
}
