<?php

namespace refund\command\subcommand;

use refund\command\argument\DurationArgument;
use refund\Main;
use lib\commandx\ArgumentType;
use lib\commandx\CommandArgument;
use lib\commandx\CommandContext;
use lib\commandx\CommandHandler;
use lib\commandx\StaticCompletionProvider;

final class PruneSubCommand extends CommandHandler
{
    public function __construct(private Main $plugin)
    {
        parent::__construct("refunds.command", [
            new CommandArgument("age", new DurationArgument()),
            new CommandArgument("confirm", ArgumentType::STRING, false, new StaticCompletionProvider(["confirm"]))
        ]);
    }

    protected function execute(CommandContext $context): bool
    {
        $durationSeconds = $context->getArgument("age");
        $now = time();
        $cutoff = $durationSeconds >= $now ? 0 : $now - $durationSeconds;
        $age = $context->getRawArguments()[0];
        $refundCount = $this->plugin->getRefundService()->countOldRefunds($cutoff);
        $sender = $context->getSender();

        if (strtolower($context->getArgument("confirm") ?? "") !== "confirm") {
            $sender->sendMessage(
                Main::PREFIX . $refundCount . " completed refund(s) older than " . $age .
                " would be deleted. Pending and partially claimed refunds are preserved."
            );
            $sender->sendMessage(Main::PREFIX . "Run /refunds prune " . $age . " confirm to delete them.");
            return true;
        }

        $deleted = $this->plugin->getRefundService()->deleteOldRefunds($cutoff);
        $sender->sendMessage(
            Main::PREFIX . "Deleted " . $deleted . " completed refund record(s) older than " . $age . "."
        );
        return true;
    }
}
