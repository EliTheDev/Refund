<?php

namespace lib\commandx;

use InvalidArgumentException;

interface ArgumentValidator
{
    public function getArgumentType(): ArgumentType;

    /**
     * @throws InvalidArgumentException When the input is invalid.
     */
    public function validate(string $value, CommandContext $context): mixed;
}
