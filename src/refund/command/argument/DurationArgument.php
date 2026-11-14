<?php

namespace refund\command\argument;

use InvalidArgumentException;
use lib\commandx\ArgumentType;
use lib\commandx\ArgumentValidator;
use lib\commandx\CommandContext;

final class DurationArgument implements ArgumentValidator
{
    private const UNIT_SECONDS = [
        "s" => 1,
        "sec" => 1,
        "secs" => 1,
        "second" => 1,
        "seconds" => 1,
        "min" => 60,
        "mins" => 60,
        "minute" => 60,
        "minutes" => 60,
        "h" => 3600,
        "hr" => 3600,
        "hrs" => 3600,
        "hour" => 3600,
        "hours" => 3600,
        "d" => 86400,
        "day" => 86400,
        "days" => 86400,
        "w" => 604800,
        "week" => 604800,
        "weeks" => 604800,
        "mo" => 2592000,
        "mos" => 2592000,
        "month" => 2592000,
        "months" => 2592000,
        "y" => 31536000,
        "yr" => 31536000,
        "yrs" => 31536000,
        "year" => 31536000,
        "years" => 31536000
    ];

    public function getArgumentType(): ArgumentType
    {
        return ArgumentType::STRING;
    }

    public function validate(string $value, CommandContext $context): int
    {
        if (preg_match('/^([0-9]+)\s*([a-z]+)?$/i', trim($value), $matches) !== 1) {
            throw new InvalidArgumentException(
                "Enter a non-negative duration such as 30d, 6mo, or 1y. A unitless number is interpreted as days."
            );
        }

        $amount = filter_var($matches[1], FILTER_VALIDATE_INT);
        if ($amount === false) {
            throw new InvalidArgumentException("The duration is too large.");
        }

        $unit = strtolower($matches[2] ?? "d");
        $secondsPerUnit = self::UNIT_SECONDS[$unit] ?? null;
        if ($secondsPerUnit === null) {
            throw new InvalidArgumentException(
                "Use seconds, minutes, hours, days, weeks, months, or years (for example 6mo or 1y)."
            );
        }
        if ($amount > intdiv(PHP_INT_MAX, $secondsPerUnit)) {
            throw new InvalidArgumentException("The duration is too large.");
        }

        return $amount * $secondsPerUnit;
    }
}
