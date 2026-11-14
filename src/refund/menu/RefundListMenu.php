<?php

namespace refund\menu;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\DeterministicInvMenuTransaction;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\VanillaItems;
use pocketmine\block\utils\DyeColor;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use refund\form\SearchPlayerList;
use refund\Main;
use refund\utils\ArrayPage;
use refund\utils\Utils;

class RefundListMenu extends InvMenu
{

    public function __construct(private Player $player, private int $page = 1, private ?ArrayPage $arrayPage = null)
    {
        parent::__construct(InvMenuHandler::getTypeRegistry()->get(InvMenu::TYPE_DOUBLE_CHEST));
        $this->setName(TextFormat::colorize("&b&lRefund History"));
        if ($this->arrayPage === null) {
            $refunds = [];
            foreach (Main::getInstance()->getUsersData() as $name => $deaths) {
                $refunds[count($refunds)] = [
                    "name" => $name,
                    "deaths" => count($deaths),
                ];
            }
            $this->arrayPage = new ArrayPage($refunds, (4 * 9));
        }
        if ($page > $this->arrayPage->getMaxPages() || $page <= 0){
            $this->player->sendMessage(Main::PREFIX . "Please enter a valid page number!");
            $this->player->removeCurrentWindow();
            return;
        }
        $this->getInventory()->setContents([
            45 => VanillaItems::WRITABLE_BOOK()->setCustomName(TextFormat::colorize("&r&l&f✦ &r&8| &7Search Player"))->setLore([
                "",
                TextFormat::colorize("&r&l&e🔍 &r&7Search:"),
                TextFormat::colorize("&7"),
                TextFormat::colorize("&r&f* &7Find a player by name."),
                TextFormat::colorize("&7&7"),
                TextFormat::colorize("&r&7Click to open search."),
            ]),
            49 => VanillaItems::END_CRYSTAL()
                ->setCustomName(TextFormat::colorize("&r&l&f⟳ &r&8| &7Refresh\n&8[&a" . $this->page . "&8/&c" . $this->arrayPage->getMaxPages() . "&8]"))
                ->setLore([
                    "",
                    TextFormat::colorize("&r&l&6⟳ &r&7Refresh:"),
                    TextFormat::colorize("&7"),
                    TextFormat::colorize("&r&f* &7Reload current page."),
                    TextFormat::colorize("&7&7"),
                    TextFormat::colorize("&r&7Click to refresh."),
                ]),
        ]);
        for ($i = 45; $i <= 53; $i++) {
            if ($this->getInventory()->getItem($i)->isNull()) $this->getInventory()->setItem($i, VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY)->asItem()->setCustomName(TextFormat::colorize("&r")));
        }
        $this->loadPage($this->page);
        $this->setListener($this->getCallable());
    }

    public function loadPage(int $page): void
    {
        $i = 0;
        for ($x = 0; $x < 45; $x++) {
            $this->getInventory()->setItem($x, VanillaItems::AIR());
        }
        $pane = VanillaBlocks::STAINED_GLASS_PANE()->setColor(DyeColor::GRAY)->asItem()->setCustomName(TextFormat::colorize("&r"));
        $this->getInventory()->setItem(48, clone $pane);
        $this->getInventory()->setItem(50, clone $pane);
        if ($page > 1) {
            $this->getInventory()->setItem(48, VanillaItems::PAPER()->setCustomName(TextFormat::colorize("&r&l&f◀ &r&8| &7Previous"))->setLore([
                "",
                TextFormat::colorize("&r&l&9◀ &r&7Navigate:"),
                TextFormat::colorize("&7"),
                TextFormat::colorize("&r&f* &7Previous page."),
                TextFormat::colorize("&7&7"),
                TextFormat::colorize("&r&7Click to go back."),
            ]));
        }
        if ($page < $this->arrayPage->getMaxPages()) {
            $this->getInventory()->setItem(50, VanillaItems::PAPER()->setCustomName(TextFormat::colorize("&r&l&f▶ &r&8| &7Next"))->setLore([
                "",
                TextFormat::colorize("&r&l&9▶ &r&7Navigate:"),
                TextFormat::colorize("&7"),
                TextFormat::colorize("&r&f* &7Next page."),
                TextFormat::colorize("&7&7"),
                TextFormat::colorize("&r&7Click to advance."),
            ]));
        }
        foreach ($this->arrayPage->toArrayPage($page) as $id => $value) {
            $refundItem = VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::PLAYER)->asItem()->setCustomName(TextFormat::colorize("&r&l&e📅&r&c " . ucfirst($value["name"])))->setLore([
                "",
                TextFormat::colorize("&r&l&c⚰ &r&7Death Records"),
                TextFormat::colorize("&r&f  &a" . $value["deaths"] . " &r&7saved entry(ies)"),
                "",
                TextFormat::colorize("&r&7Click to view their death history."),
            ]);
            $refundItem->getNamedTag()->setString("name", $value["name"]);

            $this->getInventory()->setItem($i++, $refundItem);
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
    }

    public function getCallable(): \Closure
    {
        return self::readonly(function (DeterministicInvMenuTransaction $transaction): void {
            $item = $transaction->getItemClicked();
            $player = $transaction->getPlayer();
            $slot = $transaction->getAction()->getSlot();
            if (($tag = $item->getNamedTag()->getTag("name")) !== null) {
                $player->removeCurrentWindow();
                (new PlayerDeathList($tag->getValue(), $player))->send($player);
            } else {
                if ($slot === 45) {
                    $player->removeCurrentWindow();
                    Utils::sendForm(new SearchPlayerList(), $player);
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
                }
            }
        });
    }

}