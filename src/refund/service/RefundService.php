<?php

namespace refund\service;

use refund\model\Refund;
use refund\database\Database;
use refund\database\PlayerRefundRepository;
use refund\database\RefundRepository;

final class RefundService
{
    /** @var array<int, Refund> */
    private array $refunds = [];

    /** @var array<string, array<int, int>> */
    private array $playerRefunds;

    public function __construct(
        private Database $database,
        private RefundRepository $refundRepository,
        private PlayerRefundRepository $playerRefundRepository
    ) {
        $this->playerRefundRepository->deleteOrphans();
        $this->playerRefunds = $this->playerRefundRepository->findAll();
    }

    public function getNextRefundId(): int
    {
        return $this->refundRepository->getNextId();
    }

    /**
     * @return array<string, array<int, int>>
     */
    public function getPlayerRefunds(): array
    {
        return $this->playerRefunds;
    }

    /**
     * @return array<int, int>
     */
    public function getRefundIdsForPlayer(string $name): array
    {
        return $this->playerRefunds[PlayerRefundRepository::normalizePlayerName($name)] ?? [];
    }

    public function getRefundById(int $id): ?Refund
    {
        if (isset($this->refunds[$id])) {
            return $this->refunds[$id];
        }

        $refund = $this->refundRepository->findById($id);
        if ($refund !== null) {
            $this->refunds[$id] = $refund;
        }

        return $refund;
    }

    public function getRefundByPlayerDeath(string $name, int $deathNumber): ?Refund
    {
        $refundId = $this->getRefundIdsForPlayer($name)[$deathNumber] ?? null;
        return $refundId === null ? null : $this->getRefundById($refundId);
    }

    public function saveRefund(Refund $refund, string $playerName): void
    {
        $normalizedName = PlayerRefundRepository::normalizePlayerName($playerName);
        $deathNumber = $this->database->transaction(function () use ($refund, $normalizedName): int {
            $deathNumber = $this->playerRefundRepository->getNextDeathNumber($normalizedName);
            $this->refundRepository->save($refund);
            $this->playerRefundRepository->save($normalizedName, $deathNumber, $refund->getId());
            return $deathNumber;
        });

        $this->refunds[$refund->getId()] = $refund;
        $this->playerRefunds[$normalizedName][$deathNumber] = $refund->getId();
    }

    public function saveClaimProgress(
        Refund $refund,
        array $items,
        int $experience,
        string $issuedBy,
        string $receivedBy
    ): void {
        $this->refundRepository->saveClaimProgress($refund, $items, $experience, $issuedBy, $receivedBy);
    }

    public function countOldRefunds(int $cutoff): int
    {
        return $this->refundRepository->countCompletedBefore($cutoff);
    }

    /**
     * @return Refund[]
     */
    public function getOldRefunds(int $cutoff, int $limit): array
    {
        return $this->refundRepository->findCompletedBefore($cutoff, $limit);
    }

    public function deleteOldRefunds(int $cutoff): int
    {
        $deletedIds = $this->database->transaction(function () use ($cutoff): array {
            $ids = $this->refundRepository->findCompletedIdsBefore($cutoff);
            $deletedIds = $this->refundRepository->deleteCompletedBefore($cutoff, $ids);
            $this->playerRefundRepository->deleteForRefunds($deletedIds);
            return $deletedIds;
        });

        $deleted = array_fill_keys($deletedIds, true);
        foreach ($deletedIds as $id) {
            unset($this->refunds[$id]);
        }
        foreach ($this->playerRefunds as $playerName => $deaths) {
            foreach ($deaths as $deathNumber => $refundId) {
                if (isset($deleted[$refundId])) {
                    unset($this->playerRefunds[$playerName][$deathNumber]);
                }
            }
            if ($this->playerRefunds[$playerName] === []) {
                unset($this->playerRefunds[$playerName]);
            }
        }

        return count($deletedIds);
    }

    public function close(): void
    {
        $this->database->close();
    }
}
