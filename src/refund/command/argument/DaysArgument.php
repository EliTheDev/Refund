<?php

namespace refund\command\argument;

use InvalidArgumentException;
use lib\commandx\ArgumentType;
use lib\commandx\ArgumentValidator;
use lib\commandx\CommandContext;

final class DaysArgument implements ArgumentValidator
{
    public function getArgumentType(): ArgumentType
    {
        return ArgumentType::INTEGER;
    }

    public function validate(string $value, CommandContext $context): int
    {
        if (!ctype_digit($value) || (int) $value < 30 || (int) $value > 36500) {
            throw new InvalidArgumentException(
                "Specify an age from 30 to 36500 days. Only completed refunds at least 30 days old can be pruned."
            );
        }

        return (int) $value;
    }
}
