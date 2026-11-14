<?php

namespace refund;

use Closure;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\EventPriority;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use refund\command\CommandManager;
use refund\database\Database;
use refund\database\PlayerRefundRepository;
use refund\database\RefundRepository;
use refund\listener\CommandCompletionListener;
use refund\listener\PlayerDeathListener;
use refund\model\Refund;
use refund\service\RefundService;

class Main extends PluginBase
{
    use SingletonTrait;

    public const PREFIX = "§8(§l§fREFUND§r§8) §7";

    private ?RefundService $refundService = null;
    private ?CommandManager $commandManager = null;

    protected function onLoad(): void
    {
        self::setInstance($this);
    }

    public function onEnable(): void
    {
        $dataFolder = $this->getDataFolder();
        if (!is_dir($dataFolder) && !mkdir($dataFolder, 0777, true) && !is_dir($dataFolder)) {
            throw new \RuntimeException("Unable to create the plugin data folder.");
        }

        $database = new Database($dataFolder . "data.db");
        $this->refundService = new RefundService(
            $database,
            new RefundRepository($database),
            new PlayerRefundRepository($database)
        );
        $this->commandManager = new CommandManager($this);
        
        $this->registerListeners();
        $this->registerCommandCompletionListeners();
    }

    public function onDisable(): void
    {
        $this->refundService?->close();
    }

    public function getRefundService(): RefundService
    {
        return $this->refundService ?? throw new \LogicException("Refund service is not initialized.");
    }

    private function addListener(string $class, Closure $func): void
    {
        $this->getServer()->getPluginManager()->registerEvent($class, $func, EventPriority::LOWEST, $this);
    }

    private function registerListeners(): void
    {
        $listener = new PlayerDeathListener($this);
        $this->addListener(
            PlayerDeathEvent::class,
            static function (PlayerDeathEvent $event) use ($listener): void {
                $listener->onDeath($event);
            }
        );
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        return ($this->commandManager ?? throw new \LogicException("Command manager is not initialized."))
            ->dispatch($sender, $command, $label, $args);
    }

    private function registerCommandCompletionListeners(): void
    {
        $commandManager = $this->commandManager ?? throw new \LogicException("Command manager is not initialized.");
        $listener = new CommandCompletionListener($this, $commandManager);
        $this->addListener(
            DataPacketSendEvent::class,
            static function (DataPacketSendEvent $event) use ($listener): void {
                $listener->onPacketSend($event);
            }
        );
        $this->addListener(
            PlayerJoinEvent::class,
            static function (PlayerJoinEvent $event) use ($listener): void {
                $listener->onPlayerJoin($event);
            }
        );
        $this->addListener(
            PlayerQuitEvent::class,
            static function (PlayerQuitEvent $event) use ($listener): void {
                $listener->onPlayerQuit($event);
            }
        );
    }

    /** @return array<string, array<int, int>> */
    public function getUsersData(): array
    {
        return $this->getRefundService()->getPlayerRefunds();
    }

    public function getPlayerRefunds(string $name): array
    {
        return $this->getRefundService()->getRefundIdsForPlayer($name);
    }

    public function addRefund(Player $user, Refund $refund): void
    {
        $this->getRefundService()->saveRefund($refund, $user->getName());
    }

    public function getRefundById(int $id): ?Refund
    {
        return $this->getRefundService()->getRefundById($id);
    }

    public function getRefundByPlayerDeath(string $name, int $death): ?Refund
    {
        return $this->getRefundService()->getRefundByPlayerDeath($name, $death);
    }

}