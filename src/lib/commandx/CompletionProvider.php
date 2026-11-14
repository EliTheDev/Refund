<?php

namespace lib\commandx;

interface CompletionProvider
{
    /**
     * @return string[]
     */
    public function getSuggestions(CommandContext $context, string $input): array;
}
