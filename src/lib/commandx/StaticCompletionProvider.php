<?php

namespace lib\commandx;

final class StaticCompletionProvider implements CompletionProvider
{
    /**
     * @param string[] $suggestions
     */
    public function __construct(private array $suggestions)
    {
    }

    public function getSuggestions(CommandContext $context, string $input): array
    {
        return $this->suggestions;
    }
}
