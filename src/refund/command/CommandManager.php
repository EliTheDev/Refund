<?php

namespace refund\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\player\Player;
use refund\Main;
use lib\commandx\CommandRegistry;

final class CommandManager
{
    private CommandRegistry $registry;

    public function __construct(Main $plugin)
    {
        $this->registry = new CommandRegistry();
        $this->registry->register("refund", new RefundCommand($plugin));
        $this->registry->register("refunds", new RefundsCommand($plugin));
    }

    public function dispatch(CommandSender $sender, Command $command, string $label, array $arguments): bool
    {
        return $this->registry->dispatch($sender, $command, $label, $arguments);
    }

    /**
     * @param CommandSender[] $senders
     */
    public function addCompletionData(AvailableCommandsPacket $packet, array $senders): AvailableCommandsPacket
    {
        return $this->registry->addCompletionData($packet, $senders);
    }

    /**
     * @return array<string, string[]>
     */
    public function getDynamicCompletionSuggestions(Player $player): array
    {
        return $this->registry->getDynamicCompletionSuggestions($player);
    }
}
