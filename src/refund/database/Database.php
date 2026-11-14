<?php

namespace refund\database;

use RuntimeException;
use SQLite3;
use SQLite3Result;
use SQLite3Stmt;
use Throwable;

final class Database
{
    private SQLite3 $connection;

    public function __construct(string $path)
    {
        $this->connection = new SQLite3($path);
        $this->createSchema();
    }

    public function prepare(string $query): SQLite3Stmt
    {
        $statement = $this->connection->prepare($query);
        if ($statement === false) {
            throw new RuntimeException("Failed to prepare database query: " . $this->connection->lastErrorMsg());
        }

        return $statement;
    }

    public function query(string $query): SQLite3Result
    {
        $result = $this->connection->query($query);
        if ($result === false) {
            throw new RuntimeException("Failed to execute database query: " . $this->connection->lastErrorMsg());
        }

        return $result;
    }

    public function execute(SQLite3Stmt $statement): SQLite3Result
    {
        $result = $statement->execute();
        if ($result === false) {
            throw new RuntimeException("Failed to execute database statement: " . $this->connection->lastErrorMsg());
        }

        return $result;
    }

    public function executeSql(string $query): void
    {
        if (!$this->connection->exec($query)) {
            throw new RuntimeException("Failed to execute database statement: " . $this->connection->lastErrorMsg());
        }
    }

    public function changes(): int
    {
        return $this->connection->changes();
    }

    public function transaction(callable $operation): mixed
    {
        $this->executeSql("BEGIN IMMEDIATE TRANSACTION");

        try {
            $result = $operation();
            $this->executeSql("COMMIT");
            return $result;
        } catch (Throwable $exception) {
            try {
                $this->executeSql("ROLLBACK");
            } catch (Throwable $rollbackException) {
                throw new RuntimeException(
                    "Database transaction failed and could not be rolled back: " . $rollbackException->getMessage(),
                    0,
                    $exception
                );
            }
            throw $exception;
        }
    }

    public function close(): void
    {
        $this->connection->close();
    }

    private function createSchema(): void
    {
        $this->executeSql("CREATE TABLE IF NOT EXISTS refunds (
            id TEXT PRIMARY KEY,
            name TEXT,
            time INTEGER,
            armor TEXT,
            inventory TEXT,
            offHand TEXT,
            position TEXT,
            refunded INTEGER,
            killedBy TEXT,
            experience INTEGER NOT NULL DEFAULT 0,
            refunded_by TEXT NOT NULL DEFAULT '',
            refunded_at INTEGER NOT NULL DEFAULT 0,
            issued_by TEXT NOT NULL DEFAULT '',
            received_by TEXT NOT NULL DEFAULT ''
        )");
        $this->ensureRefundColumn("experience", "INTEGER NOT NULL DEFAULT 0");
        $this->ensureRefundColumn("refunded_by", "TEXT NOT NULL DEFAULT ''");
        $this->ensureRefundColumn("refunded_at", "INTEGER NOT NULL DEFAULT 0");
        $this->ensureRefundColumn("issued_by", "TEXT NOT NULL DEFAULT ''");
        $this->ensureRefundColumn("received_by", "TEXT NOT NULL DEFAULT ''");
        $this->executeSql("CREATE TABLE IF NOT EXISTS player_refunds (
            player_name TEXT NOT NULL,
            death_number INTEGER NOT NULL,
            refund_id TEXT NOT NULL,
            PRIMARY KEY (player_name, death_number)
        )");
    }

    private function ensureRefundColumn(string $column, string $definition): void
    {
        $result = $this->query("PRAGMA table_info(refunds)");
        try {
            while (($row = $result->fetchArray(SQLITE3_ASSOC)) !== false) {
                if ($row["name"] === $column) {
                    return;
                }
            }
        } finally {
            $result->finalize();
        }

        $this->executeSql("ALTER TABLE refunds ADD COLUMN " . $column . " " . $definition);
    }
}
