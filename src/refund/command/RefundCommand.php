<?php

namespace refund\command;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use refund\command\argument\PlayerArgument;
use refund\Main;
use refund\menu\PlayerDeathList;
use lib\commandx\CommandArgument;
use lib\commandx\CommandContext;
use lib\commandx\CommandHandler;

final class RefundCommand extends CommandHandler
{
    public function __construct(private Main $plugin)
    {
        parent::__construct("refund.command", [
            new CommandArgument("player", new PlayerArgument())
        ]);
    }

    protected function requiresPlayer(): bool
    {
        return true;
    }

    protected function execute(CommandContext $context): bool
    {
        $sender = $context->getSender();
        if (!$sender instanceof Player) {
            return true;
        }

        $name = $context->getArgument("player");
        if ($this->plugin->getPlayerRefunds($name) === []) {
            $sender->sendMessage(Main::PREFIX . TextFormat::RED . $name . TextFormat::GRAY . " has no saved refund data yet.");
            return true;
        }

        (new PlayerDeathList($name, $sender))->send($sender);
        return true;
    }
}
