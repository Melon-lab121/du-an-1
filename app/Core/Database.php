<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Lớp bọc PDO: kết nối lười (chỉ kết nối khi cần), luôn dùng prepared statement.
 * Hỗ trợ MySQL/MariaDB (XAMPP) và SQLite (dùng cho test).
 */
final class Database
{
    private ?PDO $pdo = null;

    /** @param array<string, mixed> $config */
    public function __construct(private array $config)
    {
    }

    public function pdo(): PDO
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        if ($this->driver() === 'sqlite') {
            $this->pdo = new PDO('sqlite:' . ($this->config['database'] ?? ':memory:'), null, null, $options);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            return $this->pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'] ?? '127.0.0.1',
            (int) ($this->config['port'] ?? 3306),
            $this->config['database'] ?? '',
            $this->config['charset'] ?? 'utf8mb4',
        );
        $this->pdo = new PDO($dsn, (string) ($this->config['username'] ?? 'root'), (string) ($this->config['password'] ?? ''), $options);

        return $this->pdo;
    }

    public function driver(): string
    {
        return (string) ($this->config['driver'] ?? 'mysql');
    }

    /** @param array<int|string, mixed> $params */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $positional = count(array_filter(array_keys($params), 'is_int')) === count($params);
        foreach ($positional ? array_values($params) : $params as $key => $value) {
            $param = is_int($key) ? $key + 1 : (str_starts_with((string) $key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($param, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /** @return array<int, array<string, mixed>> */
    public function select(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function selectOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_map([$this, 'quoteIdentifier'], array_keys($data));
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteIdentifier($table),
            implode(', ', $columns),
            implode(', ', array_fill(0, count($data), '?')),
        );
        $this->query($sql, array_values($data));
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, mixed> $whereParams tham số dạng ? cho mệnh đề WHERE
     * @return int số dòng bị ảnh hưởng
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = array_map(fn (string $col): string => $this->quoteIdentifier($col) . ' = ?', array_keys($data));
        $sql = sprintf('UPDATE %s SET %s WHERE %s', $this->quoteIdentifier($table), implode(', ', $sets), $where);
        return $this->query($sql, array_merge(array_values($data), array_values($whereParams)))->rowCount();
    }

    /** @param array<int, mixed> $params */
    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query(sprintf('DELETE FROM %s WHERE %s', $this->quoteIdentifier($table), $where), $params)->rowCount();
    }

    /**
     * Chạy callback trong transaction. Transaction lồng nhau sẽ dùng chung transaction ngoài.
     *
     * @template T
     * @param callable(Database): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        if ($pdo->inTransaction()) {
            return $callback($this);
        }

        $attempts = 0;
        $maxAttempts = 3;
        while (true) {
            $attempts++;
            $pdo->beginTransaction();
            try {
                $result = $callback($this);
                $pdo->commit();
                return $result;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $isDeadlock = ($e instanceof \PDOException) && in_array((int) ($e->errorInfo[1] ?? 0), [1213, 1205], true);
                if ($isDeadlock && $attempts < $maxAttempts) {
                    usleep(random_int(10000, 50000));
                    continue;
                }
                throw $e;
            }
        }
    }

    public function table(string $table): QueryBuilder
    {
        return new QueryBuilder($this, $table);
    }

    /** Quote tên bảng/cột, chặn SQL injection qua tên cột động. */
    public function quoteIdentifier(string $name): string
    {
        $name = trim($name);
        if ($name === '*') {
            return '*';
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.([A-Za-z_][A-Za-z0-9_]*|\*))?$/', $name)) {
            throw new InvalidArgumentException("Tên cột/bảng không hợp lệ: {$name}");
        }

        $quote = $this->driver() === 'mysql' ? '`' : '"';
        return implode('.', array_map(
            fn (string $part): string => $part === '*' ? '*' : $quote . $part . $quote,
            explode('.', $name),
        ));
    }
}
