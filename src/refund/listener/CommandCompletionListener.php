<?php

namespace refund\listener;

use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\UpdateSoftEnumPacket;
use pocketmine\player\Player;
use refund\command\CommandManager;
use refund\Main;

final class CommandCompletionListener
{
    public function __construct(private Main $plugin, private CommandManager $commandManager)
    {
    }

    public function onPacketSend(DataPacketSendEvent $event): void
    {
        $senders = [];
        foreach ($event->getTargets() as $target) {
            $player = $target->getPlayer();
            if (!$player instanceof Player) {
                return;
            }
            $senders[] = $player;
        }

        $packets = [];
        foreach ($event->getPackets() as $packet) {
            $packets[] = $packet instanceof AvailableCommandsPacket
                ? $this->commandManager->addCompletionData($packet, $senders)
                : $packet;
        }
        $event->setPackets($packets);
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $this->refresh();
    }

    public function onPlayerQuit(PlayerQuitEvent $event): void
    {
        $this->refresh($event->getPlayer());
    }

    private function refresh(?Player $excluded = null): void
    {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            if ($player === $excluded) {
                continue;
            }
            foreach ($this->commandManager->getDynamicCompletionSuggestions($player) as $enumName => $values) {
                $player->getNetworkSession()->sendDataPacket(
                    UpdateSoftEnumPacket::create($enumName, $values, UpdateSoftEnumPacket::TYPE_SET)
                );
            }
        }
    }
}
