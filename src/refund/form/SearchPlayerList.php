<?php

namespace refund\form;

use lib\form\CustomForm;
use pocketmine\player\Player;
use refund\Main;
use refund\menu\PlayerDeathList;
use refund\menu\RefundListMenu;
use refund\utils\ArrayPage;
use refund\utils\Utils;

class SearchPlayerList extends CustomForm
{

    public function __construct()
    {
        parent::__construct($this->getListener());
        $this->setTitle("Search Players");
        $this->addInput("Enter a player name to search", "E.g. John Doe", null, "name");
    }

    public function getListener(): callable
    {
        return function (Player $player, mixed $data = null): void {
            if ($data === null || is_null($data["name"])) {
                (new RefundListMenu($player))->send($player);
                return;
            }
            $name = $data["name"];
            $list = Utils::getPlayersByPrefix($name);
            if (is_null($list)) {
                $player->sendMessage(Main::PREFIX . "There are no players matching your search.");
            } else if (is_string($list)) {
                (new PlayerDeathList($list, $player))->send($player);
            } else if (is_array($list) && !empty($list)) {
                (new RefundListMenu($player, 1, new ArrayPage($list, (4 * 9))))->send($player);
            }
        };
    }

}