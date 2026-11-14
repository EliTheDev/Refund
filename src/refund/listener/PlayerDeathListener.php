<?php

namespace refund\listener;

use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\player\Player;
use refund\model\Refund;
use refund\Main;

final class PlayerDeathListener
{
    public function __construct(private Main $plugin)
    {
    }

    public function onDeath(PlayerDeathEvent $event): void
    {
        $player = $event->getPlayer();
        $experience = $player->getXpManager()->getCurrentTotalXp();
        $refund = new Refund(
            $this->plugin->getRefundService()->getNextRefundId(),
            strtolower($player->getName()),
            time(),
            [
                "armor" => $player->getArmorInventory()->getContents(),
                "inventory" => $player->getInventory()->getContents(),
                "offHand" => $player->getOffHandInventory()->getContents()
            ],
            $this->formatPosition($player),
            false,
            $this->getDeathReason($player->getLastDamageCause()),
            $experience
        );

        if ($refund->getItemCount() > 0 || $experience > 0) {
            $this->plugin->addRefund($player, $refund);
        }
    }

    private function getDeathReason(?EntityDamageEvent $cause): string
    {
        if ($cause === null) {
            return "Undefined";
        }

        $reason = match ($cause->getCause()) {
            EntityDamageEvent::CAUSE_VOID => "Void",
            EntityDamageEvent::CAUSE_FIRE, EntityDamageEvent::CAUSE_FIRE_TICK => "Fire",
            EntityDamageEvent::CAUSE_LAVA => "Lava",
            EntityDamageEvent::CAUSE_BLOCK_EXPLOSION => "Explosion (TNT)",
            EntityDamageEvent::CAUSE_ENTITY_EXPLOSION => "Explosion (CREEPER)",
            EntityDamageEvent::CAUSE_FALL => "Fall damage",
            EntityDamageEvent::CAUSE_MAGIC => "Effect",
            EntityDamageEvent::CAUSE_DROWNING => "Drowning",
            EntityDamageEvent::CAUSE_FALLING_BLOCK => "Sand/Gravel",
            EntityDamageEvent::CAUSE_SUFFOCATION => "Glitch in blocks",
            EntityDamageEvent::CAUSE_SUICIDE => "Suicide",
            default => "Unknown"
        };

        if ($reason !== "Unknown") {
            return $reason;
        }

        if ($cause instanceof EntityDamageByEntityEvent) {
            $damager = $cause->getDamager();
            return $damager instanceof Player ? $damager->getName() : $damager->getNameTag();
        }

        return "Unknown";
    }

    private function formatPosition(Player $player): string
    {
        $position = $player->getPosition();
        return sprintf(
            "X: %.2f Y: %.2f Z: %.2f World: %s",
            $position->getX(),
            $position->getY(),
            $position->getZ(),
            $position->getWorld()->getFolderName()
        );
    }
}
