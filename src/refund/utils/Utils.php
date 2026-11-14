<?php

namespace refund\utils;

use lib\form\Form;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\item\Item;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\TreeRoot;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use refund\model\Refund;
use refund\Main;

final class Utils
{

    private const TAG_NAME = "contents";

    /**
     * @param array<int, Item> $contents
     * @return string
     */
    public static function serialize(array $contents): string
    {
        if (count($contents) === 0) {
            return "";
        }

        $contentsTag = [];
        foreach ($contents as $slot => $item) {
            $contentsTag[] = $item->nbtSerialize($slot);
        }

        return base64_encode((new BigEndianNbtSerializer())->write(new TreeRoot(CompoundTag::create()->setTag(self::TAG_NAME, new ListTag($contentsTag, NBT::TAG_Compound)))));
    }

    /**
     * @param string $string
     * @return array<int, Item>
     */
    public static function deSerialize(string $string): array
    {
        if ($string === "") {
            return [];
        }

        $decoded = base64_decode($string, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException("Invalid serialized string specified");
        }

        $tag = (new BigEndianNbtSerializer())->read($decoded)->mustGetCompoundTag()->getListTag(self::TAG_NAME) ?? throw new \InvalidArgumentException("Invalid serialized string specified");

        $contents = [];
        /** @var CompoundTag $value */
        foreach ($tag as $value) {
            try {
                $item = Item::nbtDeserialize($value);
            } catch (SavedDataLoadingException) {
                continue;
            }
            $contents[$value->getByte("Slot")] = $item;
        }

        return $contents;
    }

    public static function getRefundDate(Refund $refund): string
    {
        $timeAgo = self::formatTimeAgo($refund->getTime());
        return date("d/m/y (h:i:s A)", $refund->getTime()) . " - " . $timeAgo;
    }

    public static function formatTimeAgo(int $timestamp): string
    {
        $diff = max(0, time() - $timestamp);
        if ($diff < 60) {
            return "just now";
        }
        if ($diff < 3600) {
            $minutes = (int) floor($diff / 60);
            return $minutes . "m ago";
        }
        if ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return $hours . "h ago";
        }
        $days = (int) floor($diff / 86400);
        return $days . "d ago";
    }

    public static function getItemSummary(Refund $refund): string
    {
        $names = [];
        foreach (["inventory", "armor", "offHand"] as $section) {
            foreach ($refund->getItem($section) as $item) {
                if ($item instanceof Item && !$item->isNull()) {
                    $names[] = $item->getName();
                }
            }
        }

        if ($names === []) {
            return "empty";
        }

        $unique = [];
        foreach ($names as $name) {
            $unique[$name] = ($unique[$name] ?? 0) + 1;
        }

        $parts = [];
        foreach (array_slice(array_keys($unique), 0, 3) as $name) {
            $parts[] = $name;
        }

        return implode(", ", $parts) . (count($unique) > 3 ? ", ..." : "");
    }

    public static function getPlayersByPrefix(string $name): mixed
    {
        $normalizedName = strtolower($name);
        if ($normalizedName === "") {
            return null;
        }

        $matches = [];
        $bestMatch = null;
        $bestDelta = PHP_INT_MAX;

        foreach (Main::getInstance()->getUsersData() as $player => $data) {
            if (stripos($player, $normalizedName) !== 0) {
                continue;
            }

            $delta = strlen($player) - strlen($normalizedName);
            $matches[] = [
                "name" => $player,
                "deaths" => count($data)
            ];

            if ($delta < $bestDelta) {
                $bestDelta = $delta;
                $bestMatch = $player;
            }

            if ($delta === 0) {
                break;
            }
        }

        if ($matches === []) {
            return null;
        }

        if (count($matches) === 1) {
            return $bestMatch;
        }

        return $matches;
    }

    public static function sendForm(Form $form, Player $player): void
    {
        Main::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($form, $player): void {
            if (!$player->isOnline() || !$player->spawned) {
                return;
            }

            $player->sendForm($form);
        }), 20);
    }

}