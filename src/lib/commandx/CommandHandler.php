<?php

namespace lib\commandx;

use InvalidArgumentException;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

abstract class CommandHandler
{
    /**
     * @param CommandArgument[] $arguments
     */
    public function __construct(
        private ?string $permission = null,
        private array $arguments = []
    ) {
    }

    final public function handle(CommandContext $context): bool
    {
        $sender = $context->getSender();
        if ($this->permission !== null && !$sender->hasPermission($this->permission)) {
            $sender->sendMessage(TextFormat::RED . "You do not have permission to use this command.");
            return true;
        }
        if ($this->requiresPlayer() && !$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "This command can only be used by a player.");
            return true;
        }

        if ($this->allowsVariableArguments()) {
            return $this->execute($context);
        }

        if (count($context->getRawArguments()) > count($this->arguments)) {
            return $this->sendUsage($context, "Too many arguments.");
        }

        $parsedArguments = [];
        foreach ($this->arguments as $index => $argument) {
            if (!array_key_exists($index, $context->getRawArguments())) {
                if ($argument->isRequired()) {
                    return $this->sendUsage($context, "Missing required argument: " . $argument->getName() . ".");
                }
                $parsedArguments[$argument->getName()] = null;
                continue;
            }

            try {
                $parsedArguments[$argument->getName()] = $argument->parse(
                    $context->getRawArguments()[$index],
                    $context
                );
            } catch (InvalidArgumentException $exception) {
                return $this->sendUsage($context, $exception->getMessage());
            }
        }

        return $this->execute($context->withParsedArguments($parsedArguments));
    }

    final public function canUse(CommandContext $context): bool
    {
        $sender = $context->getSender();
        return ($this->permission === null || $sender->hasPermission($this->permission)) &&
            (!$this->requiresPlayer() || $sender instanceof Player);
    }

    /**
     * @return string[]
     */
    public function getCompletionSuggestions(CommandContext $context, int $argumentIndex, string $input): array
    {
        if (!$this->canUse($context)) {
            return [];
        }

        $argument = $this->getArguments()[$argumentIndex] ?? null;
        if ($argument === null) {
            return [];
        }

        return self::filterSuggestions($argument->getSuggestions($context, $input), $input);
    }

    /**
     * @return CommandArgument[][]
     */
    public function getCommandOverloads(CommandContext $context): array
    {
        return $this->canUse($context) ? [$this->getArguments()] : [];
    }

    /**
     * @return CommandArgument[]
     */
    public function getCompletionArguments(CommandContext $context): array
    {
        return $this->canUse($context) ? $this->getArguments() : [];
    }

    protected function requiresPlayer(): bool
    {
        return false;
    }

    protected function allowsVariableArguments(): bool
    {
        return false;
    }

    /**
     * @return CommandArgument[]
     */
    final public function getArguments(): array
    {
        return $this->arguments;
    }

    protected function getUsage(CommandContext $context): string
    {
        $arguments = array_map(
            static fn (CommandArgument $argument): string => $argument->getUsage(),
            $this->getArguments()
        );
        return $context->getCommandPath() . ($arguments === [] ? "" : " " . implode(" ", $arguments));
    }

    abstract protected function execute(CommandContext $context): bool;

    /**
     * @param string[] $suggestions
     * @return string[]
     */
    public static function filterSuggestions(array $suggestions, string $input): array
    {
        $filtered = [];
        foreach ($suggestions as $suggestion) {
            if (stripos($suggestion, $input) === 0) {
                $filtered[strtolower($suggestion)] = $suggestion;
            }
        }
        return array_values($filtered);
    }

    private function sendUsage(CommandContext $context, string $error): bool
    {
        $context->getSender()->sendMessage(TextFormat::RED . $error);
        $context->getSender()->sendMessage(TextFormat::GRAY . "Usage: " . $this->getUsage($context));
        return true;
    }
}
