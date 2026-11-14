<?php

namespace refund\command\subcommand;

use pocketmine\utils\TextFormat;
use refund\command\argument\DurationArgument;
use refund\Main;
use lib\commandx\CommandArgument;
use lib\commandx\CommandContext;
use lib\commandx\CommandHandler;

final class InspectSubCommand extends CommandHandler
{
    public function __construct(private Main $plugin)
    {
        parent::__construct("refunds.command", [
            new CommandArgument("age", new DurationArgument())
        ]);
    }

    protected function execute(CommandContext $context): bool
    {
        $durationSeconds = $context->getArgument("age");
        $age = $context->getRawArguments()[0];
        $cutoff = time() - $durationSeconds;
        $refundService = $this->plugin->getRefundService();
        $refundCount = $refundService->countOldRefunds($cutoff);
        $oldRefunds = $refundService->getOldRefunds($cutoff, 20);
        $sender = $context->getSender();
        $sender->sendMessage(Main::PREFIX . $refundCount . " completed refund(s) older than " . $age . ".");

        foreach ($oldRefunds as $refund) {
            $sender->sendMessage(
                TextFormat::GRAY . "#" . $refund->getId() . " " . $refund->getName() . " - " .
                date("Y-m-d", $refund->getTime()) . " - issued by " .
                ($refund->getIssuedBy() !== "" ? $refund->getIssuedBy() : "unknown") . ", received by " .
                ($refund->getReceivedBy() !== "" ? $refund->getReceivedBy() : "unknown")
            );
        }

        if ($refundCount > 20) {
            $sender->sendMessage(
                TextFormat::GRAY . "Showing the first 20. Use /refunds prune " . $age . " to review the deletion count."
            );
        }

        return true;
    }
}
