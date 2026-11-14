<?php

namespace lib\commandx;

use pocketmine\command\CommandSender;

final class CommandContext
{
    /**
     * @param string[] $rawArguments
     * @param array<string, mixed> $parsedArguments
     */
    public function __construct(
        private CommandSender $sender,
        private string $commandPath,
        private array $rawArguments,
        private array $parsedArguments = []
    ) {
    }

    public function getSender(): CommandSender
    {
        return $this->sender;
    }

    public function getCommandPath(): string
    {
        return $this->commandPath;
    }

    /**
     * @return string[]
     */
    public function getRawArguments(): array
    {
        return $this->rawArguments;
    }

    public function getArgument(string $name): mixed
    {
        return $this->parsedArguments[$name] ?? null;
    }

    /**
     * @param array<string, mixed> $parsedArguments
     */
    public function withParsedArguments(array $parsedArguments): self
    {
        return new self($this->sender, $this->commandPath, $this->rawArguments, $parsedArguments);
    }

    /**
     * @param string[] $rawArguments
     */
    public function withInvocation(string $commandPath, array $rawArguments): self
    {
        return new self($this->sender, $commandPath, $rawArguments);
    }
}
