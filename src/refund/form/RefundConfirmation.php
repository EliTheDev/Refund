<?php

namespace refund\form;

use lib\form\SimpleForm;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use refund\model\Refund;
use refund\Main;
use refund\menu\PlayerDeathList;
use refund\menu\RefundMenu;
use refund\utils\Utils;

class RefundConfirmation extends SimpleForm
{
    public function __construct(private Refund $refund, private string $action, private string $issuedBy)
    {
        parent::__construct($this->getListener());
        $this->setTitle("Confirm Refund");
        $method = $action === "chest" ? "claim the items through the refund chest" : "give the remaining contents directly";
        $this->setContent(
            TextFormat::colorize(
            "&7Recipient: &f" . $refund->getName() . "\n" .
            "&7Items remaining: &f" . $refund->getItemCount() . "\n" .
            "&7XP remaining: &f" . number_format($refund->getExperience()) . "\n\n" .
            "&cConfirm to " . $method . "?"
            )
        );
        $this->addButton("Confirm", -1, "", "confirm");
        $this->addButton("Cancel", -1, "", "cancel");
    }

    public function getListener(): callable
    {
        return function (Player $issuer, mixed $data): void {
            if ($data !== "confirm") {
                Utils::sendForm(new PlayerDeathInfo($this->refund), $issuer);
                return;
            }
            if ($this->refund->isRefunded()) {
                $issuer->sendMessage(Main::PREFIX . "This refund has already been fully claimed.");
                return;
            }

            $recipient = $issuer->getServer()->getPlayerByPrefix($this->refund->getName());
            if (!$recipient instanceof Player) {
                $issuer->sendMessage(Main::PREFIX . "The recipient " . $this->refund->getName() . " is offline or could not be found. They must be online to receive this refund.");
                Utils::sendForm(new PlayerDeathInfo($this->refund), $issuer);
                return;
            }

            if ($this->action === "chest") {
                (new RefundMenu($this->refund))->openClaim($recipient, $issuer->getName());
                return;
            }

            if (!RefundMenu::reserveClaim($this->refund->getId())) {
                $issuer->sendMessage(Main::PREFIX . "This refund is already open for claiming.");
                return;
            }
            try {
                $remainingItems = [];
                foreach (["inventory", "armor", "offHand"] as $section) {
                    foreach ($this->refund->getItem($section) as $item) {
                        if (!$item instanceof Item || $item->isNull()) {
                            continue;
                        }
                        foreach ($recipient->getInventory()->addItem($item) as $leftover) {
                            $remainingItems[] = $leftover;
                        }
                    }
                }

                $remaining = [
                    "inventory" => $remainingItems,
                    "armor" => [],
                    "offHand" => [],
                ];
                $experience = $this->refund->getExperience();
                Main::getInstance()->getRefundService()->saveClaimProgress(
                    $this->refund,
                    $remaining,
                    0,
                    $issuer->getName(),
                    $recipient->getName()
                );
                if ($experience > 0) {
                    $recipient->getXpManager()->addXp($experience);
                }

                if ($this->refund->isRefunded()) {
                    $recipient->sendMessage(Main::PREFIX . "Your refund was delivered.");
                    $issuer->sendMessage(Main::PREFIX . "Refund delivered to " . $recipient->getName() . ".");
                } else {
                    $recipient->sendMessage(Main::PREFIX . "Your inventory was full. Remaining refund items are saved for later.");
                    $issuer->sendMessage(Main::PREFIX . "Some items did not fit in " . $recipient->getName() . "'s inventory and remain available in the refund.");
                }
            } finally {
                RefundMenu::releaseClaim($this->refund->getId());
            }

            (new PlayerDeathList($this->refund->getName(), $issuer))->send($issuer);
        };
    }
}
