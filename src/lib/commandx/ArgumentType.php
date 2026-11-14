<?php

namespace lib\commandx;

use InvalidArgumentException;
use pocketmine\network\mcpe\protocol\types\command\CommandParameterTypes;

enum ArgumentType
{
    case STRING;
    case INTEGER;

    public function getBedrockType(): int
    {
        return match ($this) {
            self::STRING => CommandParameterTypes::ID,
            self::INTEGER => CommandParameterTypes::INT
        };
    }

    public function parse(string $value): mixed
    {
        return match ($this) {
            self::STRING => $value,
            self::INTEGER => ctype_digit($value)
                ? (int) $value
                : throw new InvalidArgumentException("Expected a whole number.")
        };
    }
}
