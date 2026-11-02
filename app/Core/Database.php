<?php
namespace App\Core;

use PDO;

class Database
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;

    public static function pdo(): PDO
    {
        if (!self::$pdo) {
            $c = config('database');
            self::$pdo = new PDO(
                "mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4",
                $c['username'],
                $c['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        return self::query($sql, $params)->fetch() ?: null;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::query($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    /** Column names are never taken from user input: callers pass fixed keys or a resource's declared fields. */
    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::query($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, int $id, string $pk = 'id'): void
    {
        $set = implode(',', array_map(fn ($c) => "`$c`=?", array_keys($data)));
        self::query("UPDATE `$table` SET $set WHERE `$pk`=?", [...array_values($data), $id]);
    }

    /** Runs the callback atomically. Nested calls join the outer transaction. */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if (self::$depth === 0) {
            $pdo->beginTransaction();
        }
        self::$depth++;
        try {
            $result = $fn();
            self::$depth--;
            if (self::$depth === 0) {
                $pdo->commit();
            }
            return $result;
        } catch (\Throwable $e) {
            self::$depth--;
            if (self::$depth === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function requireTransaction(): void
    {
        if (self::$depth === 0) {
            throw new \LogicException('Ledger posting must run inside a transaction.');
        }
    }
}
