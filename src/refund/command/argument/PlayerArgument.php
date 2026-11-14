<?php

namespace refund\command\argument;

use InvalidArgumentException;
use pocketmine\player\Player;
use lib\commandx\ArgumentType;
use lib\commandx\ArgumentValidator;
use lib\commandx\CommandContext;
use lib\commandx\DynamicCompletionProvider;

final class PlayerArgument implements ArgumentValidator, DynamicCompletionProvider
{
    public function getArgumentType(): ArgumentType
    {
        return ArgumentType::STRING;
    }

    public function validate(string $value, CommandContext $context): string
    {
        $name = strtolower(trim($value));
        if ($name === "") {
            throw new InvalidArgumentException("Specify a player name.");
        }

        $matches = [];
        foreach ($context->getSender()->getServer()->getOnlinePlayers() as $player) {
            if (!$player instanceof Player) {
                continue;
            }

            $onlineName = $player->getName();
            $normalizedOnlineName = strtolower($onlineName);
            if ($normalizedOnlineName === $name) {
                return $normalizedOnlineName;
            }
            if (str_starts_with($normalizedOnlineName, $name)) {
                $matches[] = $normalizedOnlineName;
            }
        }

        if (count($matches) > 1) {
            throw new InvalidArgumentException(
                "The player prefix is ambiguous. Matches: " . implode(", ", $matches) . "."
            );
        }

        return $matches[0] ?? $name;
    }

    public function getSuggestions(CommandContext $context, string $input): array
    {
        $suggestions = [];
        foreach ($context->getSender()->getServer()->getOnlinePlayers() as $player) {
            $suggestions[] = $player->getName();
        }
        return $suggestions;
    }

    public function getEnumName(): string
    {
        return "refund_plugin_online_players";
    }
}
