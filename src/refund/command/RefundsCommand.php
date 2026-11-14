<?php

namespace refund\command;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use refund\Main;
use refund\menu\RefundListMenu;
use refund\command\subcommand\InspectSubCommand;
use refund\command\subcommand\PruneSubCommand;
use lib\commandx\ArgumentType;
use lib\commandx\CommandArgument;
use lib\commandx\CommandContext;
use lib\commandx\CommandHandler;
use lib\commandx\StaticCompletionProvider;

final class RefundsCommand extends CommandHandler
{
    /** @var array<string, CommandHandler> */
    private array $subcommands;

    public function __construct(Main $plugin)
    {
        parent::__construct("refunds.command");
        $this->subcommands = [
            "inspect" => new InspectSubCommand($plugin),
            "prune" => new PruneSubCommand($plugin)
        ];
    }

    protected function allowsVariableArguments(): bool
    {
        return true;
    }

    protected function getUsage(CommandContext $context): string
    {
        return $context->getCommandPath() . " | " . $context->getCommandPath() .
            " inspect <age> | " . $context->getCommandPath() . " prune <age> [confirm]";
    }

    public function getCompletionSuggestions(CommandContext $context, int $argumentIndex, string $input): array
    {
        if (!$this->canUse($context)) {
            return [];
        }

        $arguments = $context->getRawArguments();
        if ($argumentIndex === 0) {
            $suggestions = [];
            foreach ($this->subcommands as $name => $subcommand) {
                if ($subcommand->canUse($context->withInvocation($context->getCommandPath() . " " . $name, []))) {
                    $suggestions[] = $name;
                }
            }
            return self::filterSuggestions($suggestions, $input);
        }

        $name = strtolower($arguments[0] ?? "");
        $subcommand = $this->subcommands[$name] ?? null;
        if ($subcommand === null) {
            return [];
        }

        return $subcommand->getCompletionSuggestions(
            $context->withInvocation(
                $context->getCommandPath() . " " . $name,
                array_slice($arguments, 1)
            ),
            $argumentIndex - 1,
            $input
        );
    }

    public function getCommandOverloads(CommandContext $context): array
    {
        if (!$this->canUse($context)) {
            return [];
        }

        $overloads = [[]];
        foreach ($this->subcommands as $name => $subcommand) {
            $subcommandContext = $context->withInvocation($context->getCommandPath() . " " . $name, []);
            if (!$subcommand->canUse($subcommandContext)) {
                continue;
            }

            $overloads[] = array_merge(
                [
                    new CommandArgument(
                        "subcommand",
                        ArgumentType::STRING,
                        true,
                        new StaticCompletionProvider([$name])
                    )
                ],
                $subcommand->getArguments()
            );
        }

        return $overloads;
    }

    public function getCompletionArguments(CommandContext $context): array
    {
        if (!$this->canUse($context)) {
            return [];
        }

        $arguments = [];
        foreach ($this->subcommands as $name => $subcommand) {
            $subcommandContext = $context->withInvocation($context->getCommandPath() . " " . $name, []);
            if ($subcommand->canUse($subcommandContext)) {
                $arguments = array_merge($arguments, $subcommand->getArguments());
            }
        }
        return $arguments;
    }

    protected function execute(CommandContext $context): bool
    {
        $arguments = $context->getRawArguments();
        if ($arguments === []) {
            if ($context->getSender() instanceof Player) {
                (new RefundListMenu($context->getSender()))->send($context->getSender());
                return true;
            }
            $context->getSender()->sendMessage(TextFormat::GRAY . "Usage: " . $this->getUsage($context));
            return true;
        }

        $name = strtolower($arguments[0]);
        $subcommand = $this->subcommands[$name] ?? null;
        if ($subcommand === null) {
            $context->getSender()->sendMessage(TextFormat::GRAY . "Usage: " . $this->getUsage($context));
            return true;
        }

        return $subcommand->handle(
            $context->withInvocation(
                $context->getCommandPath() . " " . $name,
                array_slice($arguments, 1)
            )
        );
    }
}
