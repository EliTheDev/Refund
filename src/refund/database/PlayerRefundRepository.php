<?php

namespace refund\database;

final class PlayerRefundRepository
{
    public function __construct(private Database $database)
    {
    }

    /**
     * @return array<string, array<int, int>>
     */
    public function findAll(): array
    {
        $result = $this->database->query(
            "SELECT player_name, death_number, refund_id FROM player_refunds ORDER BY player_name, death_number"
        );
        $mappings = [];
        try {
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                $name = self::normalizePlayerName((string) $row["player_name"]);
                $mappings[$name][(int) $row["death_number"]] = (int) $row["refund_id"];
            }
        } finally {
            $result->finalize();
        }

        return $mappings;
    }

    public function deleteOrphans(): void
    {
        $this->database->executeSql(
            "DELETE FROM player_refunds WHERE NOT EXISTS (
                SELECT 1 FROM refunds WHERE refunds.id = player_refunds.refund_id
            )"
        );
    }

    public function getNextDeathNumber(string $playerName): int
    {
        $statement = $this->database->prepare(
            "SELECT COALESCE(MAX(death_number), 0) + 1 AS next_death_number
            FROM player_refunds WHERE player_name = :playerName"
        );
        $statement->bindValue(":playerName", self::normalizePlayerName($playerName), SQLITE3_TEXT);
        $result = $this->database->execute($statement);
        try {
            $row = $result->fetchArray(SQLITE3_ASSOC);
            return (int) ($row["next_death_number"] ?? 1);
        } finally {
            $result->finalize();
            $statement->close();
        }
    }

    public function save(string $playerName, int $deathNumber, int $refundId): void
    {
        $statement = $this->database->prepare(
            "INSERT INTO player_refunds (player_name, death_number, refund_id)
            VALUES (:playerName, :deathNumber, :refundId)"
        );
        $statement->bindValue(":playerName", self::normalizePlayerName($playerName), SQLITE3_TEXT);
        $statement->bindValue(":deathNumber", $deathNumber, SQLITE3_INTEGER);
        $statement->bindValue(":refundId", $refundId, SQLITE3_TEXT);
        $result = $this->database->execute($statement);
        $result->finalize();
        $statement->close();
    }

    /**
     * @param int[] $refundIds
     */
    public function deleteForRefunds(array $refundIds): void
    {
        $statement = $this->database->prepare("DELETE FROM player_refunds WHERE refund_id = :id");
        try {
            foreach ($refundIds as $refundId) {
                $statement->bindValue(":id", $refundId, SQLITE3_TEXT);
                $result = $this->database->execute($statement);
                $result->finalize();
            }
        } finally {
            $statement->close();
        }
    }

    public static function normalizePlayerName(string $playerName): string
    {
        return strtolower(trim($playerName));
    }
}
