<?php

namespace lib\commandx;

use InvalidArgumentException;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\network\mcpe\protocol\AvailableCommandsPacket;
use pocketmine\network\mcpe\protocol\types\command\CommandSoftEnum;
use pocketmine\network\mcpe\protocol\types\command\CommandPermissions;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandEnumRawData;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandOverloadRawData;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandParameterRawData;
use pocketmine\network\mcpe\protocol\types\command\raw\CommandRawData;

final class CommandRegistry
{
    /** @var array<string, CommandHandler> */
    private array $handlers = [];

    public function register(string $name, CommandHandler $handler): void
    {
        $key = strtolower($name);
        if (isset($this->handlers[$key])) {
            throw new InvalidArgumentException("A command handler is already registered for " . $name . ".");
        }
        $this->handlers[$key] = $handler;
    }

    /**
     * @param string[] $args
     */
    public function dispatch(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        $handler = $this->handlers[strtolower($command->getName())] ?? null;
        if ($handler === null) {
            return false;
        }

        return $handler->handle(new CommandContext($sender, "/" . $label, $args));
    }

    /**
     * @param string[] $arguments Arguments before the token currently being completed.
     * @return string[]
     */
    public function getCompletionSuggestions(
        CommandSender $sender,
        string $commandName,
        array $arguments,
        string $input
    ): array {
        $handler = $this->handlers[strtolower($commandName)] ?? null;
        if ($handler === null) {
            return [];
        }

        return $handler->getCompletionSuggestions(
            new CommandContext($sender, "/" . $commandName, $arguments),
            count($arguments),
            $input
        );
    }

    /**
     * Adds this registry's command metadata to PMMP's Bedrock command packet.
     *
     * @param CommandSender[] $senders
     */
    public function addCompletionData(AvailableCommandsPacket $packet, array $senders): AvailableCommandsPacket
    {
        if ($senders === []) {
            return $packet;
        }

        $registeredNames = array_fill_keys(array_keys($this->handlers), true);
        $packet->commandData = array_values(array_filter(
            $packet->commandData,
            static fn (CommandRawData $data): bool => !isset($registeredNames[strtolower($data->getName())])
        ));

        $firstSender = $senders[0];
        foreach ($this->handlers as $name => $handler) {
            $context = new CommandContext($firstSender, "/" . $name, []);
            foreach ($senders as $sender) {
                if (!$handler->canUse(new CommandContext($sender, "/" . $name, []))) {
                    continue 2;
                }
            }

            $commandOverloads = $handler->getCommandOverloads($context);
            foreach (array_slice($senders, 1) as $sender) {
                $senderContext = new CommandContext($sender, "/" . $name, []);
                $availableOverloads = [];
                foreach ($handler->getCommandOverloads($senderContext) as $overload) {
                    $availableOverloads[$this->getOverloadKey($overload, $senderContext)] = true;
                }
                $commandOverloads = array_values(array_filter(
                    $commandOverloads,
                    fn (array $overload): bool => isset(
                        $availableOverloads[$this->getOverloadKey($overload, $context)]
                    )
                ));
            }

            $this->appendCommandData($packet, $name, $commandOverloads, $context);
        }

        return $packet;
    }

    /**
     * @return array<string, string[]>
     */
    public function getDynamicCompletionSuggestions(CommandSender $sender): array
    {
        $suggestions = [];
        foreach ($this->handlers as $name => $handler) {
            $context = new CommandContext($sender, "/" . $name, []);
            foreach ($handler->getCompletionArguments($context) as $argument) {
                $provider = $argument->getCompletionProvider();
                if (!$provider instanceof DynamicCompletionProvider) {
                    continue;
                }

                $enumName = $provider->getEnumName();
                $suggestions[$enumName] = CommandHandler::filterSuggestions(array_merge(
                    $suggestions[$enumName] ?? [],
                    $provider->getSuggestions($context, "")
                ), "");
            }
        }

        return $suggestions;
    }

    /**
     * @param CommandArgument[] $arguments
     */
    private function getOverloadKey(array $arguments, CommandContext $context): string
    {
        $parts = [];
        foreach ($arguments as $argument) {
            $type = $argument->getType();
            $argumentType = $type instanceof ArgumentType ? $type : $type->getArgumentType();
            $parts[] = implode(":", [
                $argument->getName(),
                $argumentType->name,
                $argument->isRequired() ? "required" : "optional",
                implode(",", $argument->getSuggestions($context, ""))
            ]);
        }
        return implode("|", $parts);
    }

    /**
     * @param CommandArgument[][] $overloads
     */
    private function appendCommandData(
        AvailableCommandsPacket $packet,
        string $name,
        array $overloads,
        CommandContext $context
    ): void
    {
        if ($overloads === []) {
            return;
        }

        $rawOverloads = [];
        foreach ($overloads as $overloadIndex => $arguments) {
            $parameters = [];
            foreach ($arguments as $argumentIndex => $argument) {
                $typeInfo = $this->createParameterType(
                    $packet,
                    $argument,
                    $context,
                    $name . "_" . $overloadIndex . "_" . $argumentIndex
                );
                $parameters[] = new CommandParameterRawData(
                    $argument->getName(),
                    $typeInfo,
                    !$argument->isRequired(),
                    0
                );
            }
            $rawOverloads[] = new CommandOverloadRawData(false, $parameters);
        }

        $packet->commandData[] = new CommandRawData(
            $name,
            "",
            0,
            CommandPermissions::toName(CommandPermissions::NORMAL),
            -1,
            [],
            $rawOverloads
        );
    }

    private function createParameterType(
        AvailableCommandsPacket $packet,
        CommandArgument $argument,
        CommandContext $context,
        string $enumSuffix
    ): int {
        $provider = $argument->getCompletionProvider();
        if ($provider instanceof DynamicCompletionProvider) {
            $enumIndex = null;
            foreach ($packet->softEnums as $index => $enum) {
                if ($enum->getName() === $provider->getEnumName()) {
                    $enumIndex = $index;
                    break;
                }
            }

            if ($enumIndex === null) {
                $enumIndex = count($packet->softEnums);
                $packet->softEnums[] = new CommandSoftEnum(
                    $provider->getEnumName(),
                    CommandHandler::filterSuggestions($provider->getSuggestions($context, ""), "")
                );
            } else {
                $packet->softEnums[$enumIndex] = new CommandSoftEnum(
                    $provider->getEnumName(),
                    CommandHandler::filterSuggestions($provider->getSuggestions($context, ""), "")
                );
            }

            return AvailableCommandsPacket::ARG_FLAG_SOFT_ENUM |
                AvailableCommandsPacket::ARG_FLAG_VALID |
                $enumIndex;
        }

        if ($provider !== null) {
            $suggestions = CommandHandler::filterSuggestions($provider->getSuggestions($context, ""), "");
            if ($suggestions !== []) {
                $valueIndexes = [];
                foreach ($suggestions as $suggestion) {
                    $valueIndex = array_search($suggestion, $packet->enumValues, true);
                    if ($valueIndex === false) {
                        $valueIndex = count($packet->enumValues);
                        $packet->enumValues[] = $suggestion;
                    }
                    $valueIndexes[] = $valueIndex;
                }

                $enumIndex = count($packet->enums);
                $packet->enums[] = new CommandEnumRawData("refund_plugin_" . $enumSuffix, $valueIndexes);
                return AvailableCommandsPacket::ARG_FLAG_ENUM |
                    AvailableCommandsPacket::ARG_FLAG_VALID |
                    $enumIndex;
            }
        }

        $type = $argument->getType();
        $argumentType = $type instanceof ArgumentType ? $type : $type->getArgumentType();
        return AvailableCommandsPacket::ARG_FLAG_VALID | $argumentType->getBedrockType();
    }
}
