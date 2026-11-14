<?php

namespace refund\database;

use pocketmine\item\Item;
use refund\model\Refund;
use refund\utils\Utils;
use RuntimeException;

final class RefundRepository
{
    public function __construct(private Database $database)
    {
    }

    public function getNextId(): int
    {
        $result = $this->database->query("SELECT COALESCE(MAX(CAST(id AS INTEGER)), 0) + 1 AS next_id FROM refunds");
        try {
            $row = $result->fetchArray(SQLITE3_ASSOC);
            return (int) ($row["next_id"] ?? 1);
        } finally {
            $result->finalize();
        }
    }

    public function findById(int $id): ?Refund
    {
        $statement = $this->database->prepare("SELECT * FROM refunds WHERE id = :id");
        $statement->bindValue(":id", $id, SQLITE3_TEXT);
        $result = $this->database->execute($statement);
        try {
            $row = $result->fetchArray(SQLITE3_ASSOC);
            return $row === false ? null : $this->hydrate($row);
        } finally {
            $result->finalize();
            $statement->close();
        }
    }

    public function countCompletedBefore(int $cutoff): int
    {
        $statement = $this->database->prepare(
            "SELECT COUNT(*) AS refund_count FROM refunds WHERE refunded = 1 AND time < :cutoff"
        );
        $statement->bindValue(":cutoff", $cutoff, SQLITE3_INTEGER);
        $result = $this->database->execute($statement);
        try {
            $row = $result->fetchArray(SQLITE3_ASSOC);
            return (int) ($row["refund_count"] ?? 0);
        } finally {
            $result->finalize();
            $statement->close();
        }
    }

    /**
     * @return Refund[]
     */
    public function findCompletedBefore(int $cutoff, int $limit): array
    {
        $statement = $this->database->prepare(
            "SELECT id, name, time, position, refunded, killedBy, experience, refunded_by,
                refunded_at, issued_by, received_by
            FROM refunds WHERE refunded = 1 AND time < :cutoff ORDER BY time ASC LIMIT :limit"
        );
        $statement->bindValue(":cutoff", $cutoff, SQLITE3_INTEGER);
        $statement->bindValue(":limit", max(0, $limit), SQLITE3_INTEGER);
        $result = $this->database->execute($statement);
        $refunds = [];
        try {
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $refunds[] = $this->hydrate($row);
            }
        } finally {
            $result->finalize();
            $statement->close();
        }

        return $refunds;
    }

    /**
     * @return int[]
     */
    public function findCompletedIdsBefore(int $cutoff): array
    {
        $statement = $this->database->prepare(
            "SELECT id FROM refunds WHERE refunded = 1 AND time < :cutoff ORDER BY time ASC"
        );
        $statement->bindValue(":cutoff", $cutoff, SQLITE3_INTEGER);
        $result = $this->database->execute($statement);
        $ids = [];
        try {
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $ids[] = (int) $row["id"];
            }
        } finally {
            $result->finalize();
            $statement->close();
        }

        return $ids;
    }

    public function save(Refund $refund): void
    {
        $statement = $this->database->prepare("REPLACE INTO refunds (
            id, name, time, armor, inventory, offHand, position, refunded, killedBy, experience,
            refunded_by, refunded_at, issued_by, received_by
        ) VALUES (
            :id, :name, :time, :armor, :inventory, :offHand, :position, :refunded, :killedBy, :experience,
            :refundedBy, :refundedAt, :issuedBy, :receivedBy
        )");
        $statement->bindValue(":id", $refund->getId(), SQLITE3_TEXT);
        $statement->bindValue(":name", $refund->getName(), SQLITE3_TEXT);
        $statement->bindValue(":time", $refund->getTime(), SQLITE3_INTEGER);
        $statement->bindValue(":armor", Utils::serialize($refund->getItem("armor")), SQLITE3_TEXT);
        $statement->bindValue(":inventory", Utils::serialize($refund->getItem("inventory")), SQLITE3_TEXT);
        $statement->bindValue(":offHand", Utils::serialize($refund->getItem("offHand")), SQLITE3_TEXT);
        $statement->bindValue(":position", $refund->getPosition(), SQLITE3_TEXT);
        $statement->bindValue(":refunded", $refund->isRefunded() ? 1 : 0, SQLITE3_INTEGER);
        $statement->bindValue(":killedBy", $refund->getKilledBy(), SQLITE3_TEXT);
        $statement->bindValue(":experience", $refund->getExperience(), SQLITE3_INTEGER);
        $statement->bindValue(":refundedBy", $refund->getRefundedBy(), SQLITE3_TEXT);
        $statement->bindValue(":refundedAt", $refund->getRefundedAt(), SQLITE3_INTEGER);
        $statement->bindValue(":issuedBy", $refund->getIssuedBy(), SQLITE3_TEXT);
        $statement->bindValue(":receivedBy", $refund->getReceivedBy(), SQLITE3_TEXT);
        $result = $this->database->execute($statement);
        $result->finalize();
        $statement->close();
    }

    public function saveClaimProgress(
        Refund $refund,
        array $items,
        int $experience,
        string $issuedBy,
        string $receivedBy
    ): void {
        $completed = $experience <= 0;
        if ($completed) {
            foreach (["inventory", "armor", "offHand"] as $section) {
                foreach ($items[$section] ?? [] as $item) {
                    if ($item instanceof Item && !$item->isNull()) {
                        $completed = false;
                        break 2;
                    }
                }
            }
        }

        $refundedAt = time();
        $experience = max(0, $experience);
        $statement = $this->database->prepare("UPDATE refunds SET
            inventory = :inventory,
            armor = :armor,
            offHand = :offHand,
            experience = :experience,
            refunded = :refunded,
            refunded_by = :refundedBy,
            refunded_at = :refundedAt,
            issued_by = :issuedBy,
            received_by = :receivedBy
            WHERE id = :id");
        $statement->bindValue(":inventory", Utils::serialize($items["inventory"] ?? []), SQLITE3_TEXT);
        $statement->bindValue(":armor", Utils::serialize($items["armor"] ?? []), SQLITE3_TEXT);
        $statement->bindValue(":offHand", Utils::serialize($items["offHand"] ?? []), SQLITE3_TEXT);
        $statement->bindValue(":experience", $experience, SQLITE3_INTEGER);
        $statement->bindValue(":refunded", $completed ? 1 : 0, SQLITE3_INTEGER);
        $statement->bindValue(":refundedBy", $completed ? $issuedBy : "", SQLITE3_TEXT);
        $statement->bindValue(":refundedAt", $refundedAt, SQLITE3_INTEGER);
        $statement->bindValue(":issuedBy", $issuedBy, SQLITE3_TEXT);
        $statement->bindValue(":receivedBy", $receivedBy, SQLITE3_TEXT);
        $statement->bindValue(":id", $refund->getId(), SQLITE3_TEXT);
        $result = $this->database->execute($statement);
        $result->finalize();
        $statement->close();

        if ($this->database->changes() !== 1) {
            throw new RuntimeException("Refund #" . $refund->getId() . " no longer exists in the database.");
        }

        $refund->setClaimProgress($items, $experience, $issuedBy, $receivedBy, $completed, $refundedAt);
    }

    /**
     * @param int[] $ids
     * @return int[]
     */
    public function deleteCompletedBefore(int $cutoff, array $ids): array
    {
        $deletedIds = [];
        $statement = $this->database->prepare(
            "DELETE FROM refunds WHERE id = :id AND refunded = 1 AND time < :cutoff"
        );
        try {
            foreach ($ids as $id) {
                $statement->bindValue(":id", $id, SQLITE3_TEXT);
                $statement->bindValue(":cutoff", $cutoff, SQLITE3_INTEGER);
                $result = $this->database->execute($statement);
                $result->finalize();
                if ($this->database->changes() === 1) {
                    $deletedIds[] = $id;
                }
            }
        } finally {
            $statement->close();
        }

        return $deletedIds;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Refund
    {
        return new Refund(
            (int) $row["id"],
            (string) $row["name"],
            (int) $row["time"],
            [
                "armor" => isset($row["armor"]) ? Utils::deSerialize((string) $row["armor"]) : [],
                "inventory" => isset($row["inventory"]) ? Utils::deSerialize((string) $row["inventory"]) : [],
                "offHand" => isset($row["offHand"]) ? Utils::deSerialize((string) $row["offHand"]) : []
            ],
            (string) $row["position"],
            (bool) $row["refunded"],
            (string) $row["killedBy"],
            (int) $row["experience"],
            (string) $row["refunded_by"],
            (int) $row["refunded_at"],
            (string) ($row["issued_by"] !== "" ? $row["issued_by"] : $row["refunded_by"]),
            (string) ($row["received_by"] ?? "")
        );
    }
}
