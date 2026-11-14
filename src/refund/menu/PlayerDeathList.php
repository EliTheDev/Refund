<?php

namespace refund\menu;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use refund\model\Refund;
use pocketmine\block\utils\DyeColor;
use refund\form\PlayerDeathInfo;
use refund\form\SearchPlayerDeathList;
use refund\Main;
use refund\utils\ArrayPage;
use refund\utils\Utils;

class PlayerDeathList extends InvMenu
{
    private string $filter = "all";

    public function __construct(private string $refunded, private Player $player, private int $page = 1, private ?ArrayPage $arrayPage = null)
    {
        parent::__construct(InvMenuHandler::getTypeRegistry()->get(InvMenu::TYPE_DOUBLE_CHEST));
        $this->setName(TextFormat::colorize("&b&l" . ucfirst($this->refunded) . "'s History"));
        $this->refreshArrayPage();
        if ($page > $this->arrayPage->getMaxPages() || $page <= 0){
            $this->player->sendMessage(Main::PREFIX . "Please enter a valid page number!");
            $this->player->removeCurrentWindow();
            return;
        }
        $this->getInventory()->setContents([
            45 => VanillaItems::WRITABLE_BOOK()->setCustomName(TextFormat::colorize("&r&l&f✦ &r&8| &7Search Death"))->setLore([
                "",
                TextFormat::colorize("&r&l&e🔍 &r&7Search:"),
                TextFormat::colorize("&7"),
                TextFormat::colorize("&f* &7Search for a death by number."),
                TextFormat::colorize("&7&7"),
                TextFormat::colorize("&7Click to open search."),
            ]),
            49 => VanillaItems::END_CRYSTAL()
                ->setCustomName(TextFormat::colorize("&r&l&f⟳ &r&8| &7Refresh\n&8[&a" . $this->page . "&8/&c" . $this->arrayPage->getMaxPages() . "&8]"))
                ->setLore([
                "",
                TextFormat::colorize("&r&l&6⟳ &r&7Refresh:"),
                TextFormat::colorize("&7"),
                TextFormat::colorize("&f* &7Reload current page."),
                TextFormat::colorize("&7&7"),
                TextFormat::colorize("&7Click to refresh."),
            ]),
            52 => VanillaItems::CLOCK()->setCustomName(TextFormat::colorize("&r&l&f✦ &r&8| &7Filter"))->setLore([
                "",
                TextFormat::colorize("&r&l&e🧭 &r&7Showing:"),
                TextFormat::colorize("&r&f  " . $this->getFilterLabel()),
                "",
                TextFormat::colorize("&r&7Click to toggle the filter."),
            ]),
            53 => VanillaBlocks::BARRIER()->asItem()->setCustomName(TextFormat::colorize("&r&l&f↺ &r&8| &7Back"))->setLore([
                "",
                TextFormat::colorize("&r&l&d↺ &r&7Return:"),
                TextFormat::colorize("&7"),
                TextFormat::colorize("&f* &7Go back to the player list."),
                TextFormat::colorize("&7&7"),
                TextFormat::colorize("&7Click to return."),
            ])
        ]);
        for ($i = 45; $i <= 53; $i++) {
            if ($this->getInventory()->getItem($i)->isNull()) $this->getInventory()->setItem($i, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY)->asItem()->setCustomName(TextFormat::colorize("&r"))->setLore([""]));
        }
        $this->loadPage($this->page);
        $this->setListener($this->getCallable());
    }

    private function refreshArrayPage(): void
    {
        $refunds = [];
        foreach (Main::getInstance()->getPlayerRefunds($this->refunded) as $deaths => $ids) {
            $refund = Main::getInstance()->getRefundById($ids);
            if ($refund === null) {
                continue;
            }
            if ($this->filter !== "all" && (($this->filter === "pending" && $refund->isRefunded()) || ($this->filter === "refunded" && !$refund->isRefunded()))) {
                continue;
            }
            $refunds[count($refunds)] = [
                $deaths => $refund
            ];
        }
        $this->arrayPage = new ArrayPage($refunds, (4 * 9));
        if ($this->page > $this->arrayPage->getMaxPages() || $this->page <= 0) {
            $this->page = max(1, $this->arrayPage->getMaxPages());
        }
    }

    private function getFilterLabel(): string
    {
        return match ($this->filter) {
            "pending" => "Pending",
            "refunded" => "Refunded",
            default => "All Deaths",
        };
    }

    private function cycleFilter(): void
    {
        $this->filter = match ($this->filter) {
            "all" => "pending",
            "pending" => "refunded",
            default => "all",
        };
        $this->page = 1;
        $this->refreshArrayPage();
        $this->getInventory()->setItem(52, VanillaItems::CLOCK()->setCustomName(TextFormat::colorize("&r&l&f✦ &r&8| &7Filter"))->setLore([
            "",
            TextFormat::colorize("&r&l&e🧭 &r&7Showing:"),
            TextFormat::colorize("&r&f  " . $this->getFilterLabel()),
            "",
            TextFormat::colorize("&r&7Click to toggle the filter."),
        ]));
        $this->loadPage($this->page);
    }

    public function loadPage(int $page): void
    {
        $i = 0;
        for ($x = 0; $x < 45; $x++) {
            $this->getInventory()->setItem($x, VanillaItems::AIR());
        }
        $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY)->asItem()->setCustomName(TextFormat::colorize("&r"))->setLore([""]);
        $this->getInventory()->setItem(48, clone $pane);
        $this->getInventory()->setItem(50, clone $pane);
        if ($page > 1) {
            $this->getInventory()->setItem(48, VanillaItems::PAPER()->setCustomName(TextFormat::colorize("&r&l&f◀ &r&8| &7Previous"))->setLore([
                "",
                TextFormat::colorize("&r&l&9◀ &r&7Navigate:"),
                TextFormat::colorize("&7"),
                TextFormat::colorize("&f* &7Previous page."),
                TextFormat::colorize("&7&7"),
                TextFormat::colorize("&7Click to go back."),
            ]));
        }
        if ($page < $this->arrayPage->getMaxPages()) {
            $this->getInventory()->setItem(50, VanillaItems::PAPER()->setCustomName(TextFormat::colorize("&r&l&f▶ &r&8| &7Next"))->setLore([
                "",
                TextFormat::colorize("&r&l&9▶ &r&7Navigate:"),
                TextFormat::colorize("&7"),
                TextFormat::colorize("&f* &7Next page."),
                TextFormat::colorize("&7&7"),
                TextFormat::colorize("&7Click to advance."),
            ]));
        }
        /** @var Refund $refund */
        foreach ($this->arrayPage->toArrayPage($page) as $id => $value) {
            foreach ($value as $death => $refund) {
                $position = $refund->getPosition();
                $positionShort = preg_replace('/X: ([\d.]+).*Y: ([\d.]+).*Z: ([\d.]+).*World: (\w+)/', '$1, $2, $3 ($4)', $position);

                $lore = [
                    "",
                    TextFormat::colorize("&r&l&e📅 &r&7When:"),
                    TextFormat::colorize("&r&f  " . Utils::getRefundDate($refund)),
                    "",
                    TextFormat::colorize("&r&l&b📍 &r&7Where:"),
                    TextFormat::colorize("&r&f  " . $positionShort),
                    "",
                    TextFormat::colorize("&r&l&c⚰ &r&7Death Cause:"),
                    TextFormat::colorize("&r&f  " . $refund->getKilledBy()),
                    "",
                    TextFormat::colorize("&r&l&d= &r&7Contents:"),
                    TextFormat::colorize("&r&f  " . $refund->getItemCount() . " item(s) - " . Utils::getItemSummary($refund) . ($refund->getExperience() > 0 ? " + " . number_format($refund->getExperience()) . " XP" : "")),
                    "",
                    TextFormat::colorize("&r&l&" . ($refund->isRefunded() ? "a✓ Refunded" : ($refund->hasClaimProgress() ? "e◷ Partial" : "c✗ Pending"))),
                ];
                if ($refund->hasClaimProgress()) {
                    $refundDetails = "&o&7by &f" . ($refund->getIssuedBy() !== "" ? $refund->getIssuedBy() : "unknown");
                    if ($refund->getRefundedAt() > 0) {
                        $refundDetails .= " - " . Utils::formatTimeAgo($refund->getRefundedAt());
                    }
                    $lore[] = TextFormat::colorize("&r&7" . $refundDetails);
                }
                $lore[] = "";
                $lore[] = TextFormat::colorize("&r&7Click to inspect this death.");

                $refundItem = ($refund->isRefunded() ? VanillaItems::WRITTEN_BOOK() : VanillaItems::BOOK());
                $refundItem->setCustomName(TextFormat::colorize("&r&l&a✦ &r&f" . $death . "# &7Death &8(#" . $refund->getId() . ")"))->setLore($lore);
                $refundItem->getNamedTag()->setInt("id", $refund->getId());

                $this->getInventory()->setItem($i++, $refundItem);
            }
        }
        $this->getInventory()->setItem(49, VanillaItems::END_CRYSTAL()
        ->setCustomName(TextFormat::colorize("&r&l&f⟳ &r&8| &7Refresh\n&8[&a" . $this->page . "&8/&c" . $this->arrayPage->getMaxPages() . "&8]"))
        ->setLore([
            "",
            TextFormat::colorize("&r&l&6⟳ &r&7Refresh:"),
            TextFormat::colorize("&7"),
            TextFormat::colorize("&r&f* &7Reload current page."),
            TextFormat::colorize("&7&7"),
            TextFormat::colorize("&r&7Click to refresh."),
        ]));
        $this->getInventory()->setItem(52, VanillaItems::CLOCK()->setCustomName(TextFormat::colorize("&r&l&f✦ &r&8| &7Filter"))->setLore([
            "",
            TextFormat::colorize("&r&l&e🧭 &r&7Showing:"),
            TextFormat::colorize("&r&f  " . $this->getFilterLabel()),
            "",
            TextFormat::colorize("&r&7Click to toggle the filter."),
        ]));
    }

    public function getCallable(): \Closure
    {
        return self::readonly(function (DeterministicInvMenuTransaction $transaction): void {
            $item = $transaction->getItemClicked();
            $player = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();
            if (($tag = $item->getNamedTag()->getTag("id")) !== null) {
                $refund = Main::getInstance()->getRefundById($tag->getValue());
                if ($refund instanceof Refund) {
                    $player->removeCurrentWindow();
                    Utils::sendForm(new PlayerDeathInfo($refund), $player);
                }
            } else {
                if ($slot === 45) {
                    $player->removeCurrentWindow();
                    Utils::sendForm(new SearchPlayerDeathList($this->refunded), $player);
                } else if ($slot === 48) {
                    if ($this->page > 1){
                        $this->page--;
                    }
                    $this->loadPage($this->page);
                } else if ($slot === 49) {
                    $this->loadPage($this->page);
                } else if ($slot === 50) {
                    $nextPage = $this->page + 1;
                    if ($nextPage <= $this->arrayPage->getMaxPages()){
                        if (!empty($this->arrayPage->toArrayPage($nextPage))){
                            $this->page++;
                        }
                    }
                    $this->loadPage($this->page);
                } else if ($slot === 52) {
                    $this->cycleFilter();
                } else if ($slot === 53) {
                    $player->removeCurrentWindow();
                    (new RefundListMenu($player))->send($player);
                }
            }
        });
    }

}