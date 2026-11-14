<?php

namespace lib\commandx;

final class CommandArgument
{
    public function __construct(
        private string $name,
        private ArgumentType|ArgumentValidator $type = ArgumentType::STRING,
        private bool $required = true,
        private ?CompletionProvider $completionProvider = null
    ) {
        if ($this->completionProvider === null && $type instanceof CompletionProvider) {
            $this->completionProvider = $type;
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getType(): ArgumentType|ArgumentValidator
    {
        return $this->type;
    }

    public function getCompletionProvider(): ?CompletionProvider
    {
        return $this->completionProvider;
    }

    public function getUsage(): string
    {
        return $this->required ? "<" . $this->name . ">" : "[" . $this->name . "]";
    }

    public function parse(string $value, CommandContext $context): mixed
    {
        return $this->type instanceof ArgumentValidator
            ? $this->type->validate($value, $context)
            : $this->type->parse($value);
    }

    /**
     * @return string[]
     */
    public function getSuggestions(CommandContext $context, string $input): array
    {
        return $this->completionProvider?->getSuggestions($context, $input) ?? [];
    }
}
