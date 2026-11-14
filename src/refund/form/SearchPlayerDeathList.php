<?php

namespace refund\form;

use lib\form\CustomForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use refund\model\Refund;
use refund\Main;
use refund\menu\PlayerDeathList;
use refund\utils\Utils;

class SearchPlayerDeathList extends CustomForm
{

    public function __construct(private string $name)
    {
        parent::__construct($this->getListener());
        $this->setTitle("Search Death History");
        $this->addInput("Enter a death number to search", "1", null, "number");
    }

    public function getListener(): callable
    {
        return function (Player $player, mixed $data = null): void {
            if ($data === null || is_null($data["number"]) || !is_numeric($data["number"])) {
                (new PlayerDeathList($this->name, $player))->send($player);
                return;
            }
            $refund = Main::getInstance()->getRefundByPlayerDeath($this->name, $data["number"]);
            if ($refund instanceof Refund) {
                Utils::sendForm(new PlayerDeathInfo($refund), $player);
            } else {
                $player->sendMessage(Main::PREFIX . TextFormat::RED . $this->name . TextFormat::colorize(" &7doesn't have a death with this number."));
            }
        };
    }

}