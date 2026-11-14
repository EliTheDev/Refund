<?php

namespace refund\form;

use lib\form\SimpleForm;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use refund\model\Refund;
use refund\Main;
use refund\menu\PlayerDeathList;
use refund\menu\RefundMenu;
use refund\utils\Utils;

class PlayerDeathInfo extends SimpleForm
{

    public function __construct(private Refund $refund)
    {
        parent::__construct($this->getListener());
        $this->setTitle("Death Refund Details");
        $this->setContent($this->buildContent());

        $this->addButton("Preview\nread-only look at the items", -1, "", "preview");
        $this->addButton("Refund via Chest\nopen the item chest", -1, "", "chest");
        $this->addButton("Give to Player\nput items straight in their inventory", -1, "", "give");
        $this->addButton("Teleport\ngo to the death location", -1, "", "teleport");
        $this->addButton("Back\nreturn to the death list", -1, "", "back");
    }

    private function buildContent(): string
    {
        $position = $this->refund->getPosition();
        $location = preg_replace('/X: ([\d.]+) Y: ([\d.]+) Z: ([\d.]+) World: (.+)/', '$1, $2, $3 ($4)', $position) ?? $position;

        $status = $this->refund->isRefunded()
            ? TextFormat::GREEN . "✓ Refunded"
            : ($this->refund->hasClaimProgress() ? TextFormat::YELLOW . "◷ Partially claimed" : TextFormat::RED . "X Pending");
        $claimDetails = "";
        if ($this->refund->hasClaimProgress()) {
            $claimDetails = "\n&7Issued by: " . TextFormat::WHITE . $this->refund->getIssuedBy() .
                "\n&7Received by: " . TextFormat::WHITE . $this->refund->getReceivedBy() .
                "\n&7Claim activity: " . TextFormat::WHITE . Utils::formatTimeAgo($this->refund->getRefundedAt());
        }

        return TextFormat::colorize(
            "&7Player: " . TextFormat::WHITE . $this->refund->getName() . "\n" .
            "&7When: " . TextFormat::WHITE . Utils::getRefundDate($this->refund) . "\n" .
            "&7Where: " . TextFormat::WHITE . $location . "\n" .
            "&7Cause: " . TextFormat::WHITE . $this->refund->getKilledBy() . "\n" .
            "&7XP: " . TextFormat::GREEN . number_format($this->refund->getExperience()) . "\n" .
            $status . $claimDetails
        );
    }

    public function getListener(): callable
    {
        return function (Player $user, mixed $data): void {
            if (is_null($data) || $data === "back") {
                (new PlayerDeathList($this->refund->getName(), $user))->send($user);
                return;
            }

            if ($this->refund->isRefunded() && in_array($data, ["chest", "give"], true)) {
                $user->sendMessage(Main::PREFIX . "This death has already been refunded.");
                return;
            }

            $menu = new RefundMenu($this->refund);

            switch ($data) {
                case "preview":
                    $menu->setReadonly(true);
                    $menu->send($user);
                    return;
                case "chest":
                    Utils::sendForm(new RefundConfirmation($this->refund, "chest", $user->getName()), $user);
                    return;
                case "give":
                    Utils::sendForm(new RefundConfirmation($this->refund, "give", $user->getName()), $user);
                    return;
                case "teleport":
                    if (preg_match('/X: ([\d.]+)\s*Y: ([\d.]+)\s*Z: ([\d.]+)\s*World: (.+)/', $this->refund->getPosition(), $matches) !== 1) {
                        $user->sendMessage(Main::PREFIX . "Unable to locate the death position.");
                        (new PlayerDeathList($this->refund->getName(), $user))->send($user);
                        return;
                    }

                    $worldName = trim($matches[4]);
                    $world = $user->getServer()->getWorldManager()->getWorldByName($worldName) ?? $user->getWorld();
                    if ($world === null) {
                        $user->sendMessage(Main::PREFIX . "The death world is not loaded right now.");
                        (new PlayerDeathList($this->refund->getName(), $user))->send($user);
                        return;
                    }

                    $user->teleport(new \pocketmine\world\Position((float) $matches[1], (float) $matches[2], (float) $matches[3], $world));
                    $user->sendMessage(Main::PREFIX . "Teleported to the death location.");
                    (new PlayerDeathList($this->refund->getName(), $user))->send($user);
                    return;
                default:
                    (new PlayerDeathList($this->refund->getName(), $user))->send($user);
                    return;
            }
        };
    }

}