<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Query builder đơn giản, an toàn (giá trị luôn bind, tên cột được kiểm tra).
 *
 *   $db->table('products')->where('status', 'active')->where('price', '>=', 100000)
 *      ->orderBy('id', 'desc')->paginate(12, $page);
 */
final class QueryBuilder
{
    private const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    /** @var string[] */
    private array $columns = ['*'];
    /** @var array<int, array{bool: string, sql: string}> */
    private array $wheres = [];
    /** @var array<int, mixed> */
    private array $bindings = [];
    /** @var string[] */
    private array $joins = [];
    /** @var string[] */
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(private Database $db, private string $table)
    {
    }

    public function select(string ...$columns): self
    {
        $this->columns = array_map(fn (string $c): string => $this->col($c), $columns ?: ['*']);
        return $this;
    }

    /** Cẩn thận: $sql được chèn nguyên văn, không đưa dữ liệu người dùng vào đây. */
    public function selectRaw(string $sql): self
    {
        $this->columns = [$sql];
        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'AND'): self
    {
        if (func_num_args() === 2) {
            [$value, $operator] = [$operator, '='];
        }
        $operator = strtoupper((string) $operator);
        if (!in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException("Toán tử không hợp lệ: {$operator}");
        }

        if ($value === null) {
            $sql = $this->col($column) . (in_array($operator, ['!=', '<>'], true) ? ' IS NOT NULL' : ' IS NULL');
            return $this->addWhere($sql, [], $boolean);
        }

        return $this->addWhere($this->col($column) . " {$operator} ?", [$value], $boolean);
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            return $this->where($column, '=', $operator, 'OR');
        }
        return $this->where($column, $operator, $value, 'OR');
    }

    /** @param array<int, mixed> $values */
    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            return $this->addWhere('1 = 0', []);
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        return $this->addWhere($this->col($column) . " IN ({$placeholders})", array_values($values));
    }

    public function whereNull(string $column): self
    {
        return $this->addWhere($this->col($column) . ' IS NULL', []);
    }

    public function whereNotNull(string $column): self
    {
        return $this->addWhere($this->col($column) . ' IS NOT NULL', []);
    }

    /** @param array<int, mixed> $bindings */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        return $this->addWhere('(' . $sql . ')', $bindings, $boolean);
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $type = strtoupper($type);
        if (!in_array($type, ['INNER', 'LEFT', 'RIGHT'], true) || !in_array($operator, self::OPERATORS, true)) {
            throw new InvalidArgumentException('JOIN không hợp lệ');
        }
        [$tableName, $alias] = array_pad(preg_split('/\s+as\s+|\s+/i', trim($table)) ?: [], 2, null);
        $tableSql = $this->db->quoteIdentifier((string) $tableName) . ($alias ? ' ' . $this->db->quoteIdentifier($alias) : '');
        $this->joins[] = sprintf('%s JOIN %s ON %s %s %s', $type, $tableSql, $this->col($first), $operator, $this->col($second));
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orders[] = $this->col($column) . ' ' . $direction;
        return $this;
    }

    /** Cẩn thận: $sql được chèn nguyên văn, không đưa dữ liệu người dùng vào đây. */
    public function orderByRaw(string $sql): self
    {
        $this->orders[] = $sql;
        return $this;
    }

    public function latest(string $column = 'id'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);
        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function get(): array
    {
        return $this->db->select($this->toSql(), $this->bindings());
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $clone = clone $this;
        $clone->limit = 1;
        return $this->db->selectOne($clone->toSql(), $clone->bindings());
    }

    /** @return array<string, mixed>|null */
    public function find(int|string $id, string $column = 'id'): ?array
    {
        return (clone $this)->where($column, $id)->first();
    }

    public function value(string $column): mixed
    {
        $row = (clone $this)->select($column)->first();
        return $row === null ? null : reset($row);
    }

    /** @return array<int, mixed> */
    public function pluck(string $column): array
    {
        return array_map(fn (array $row) => reset($row), (clone $this)->select($column)->get());
    }

    public function count(): int
    {
        $clone = clone $this;
        $clone->columns = ['COUNT(*) AS aggregate'];
        $clone->orders = [];
        $clone->limit = $clone->offset = null;
        return (int) $this->db->scalar($clone->toSql(), $clone->bindings());
    }

    public function sum(string $column): int|float
    {
        $clone = clone $this;
        $clone->columns = ['COALESCE(SUM(' . $this->col($column) . '), 0) AS aggregate'];
        $clone->orders = [];
        $clone->limit = $clone->offset = null;
        $value = $this->db->scalar($clone->toSql(), $clone->bindings());
        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, total: int, per_page: int, page: int, last_page: int}
     */
    public function paginate(int $perPage = 15, int $page = 1): array
    {
        $perPage = max(1, $perPage);
        $total = $this->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);
        $data = (clone $this)->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return ['data' => $data, 'total' => $total, 'per_page' => $perPage, 'page' => $page, 'last_page' => $lastPage];
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        return $this->db->insert($this->table, $data);
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): int
    {
        if ($this->wheres === []) {
            throw new InvalidArgumentException('Từ chối UPDATE không có điều kiện WHERE.');
        }
        return $this->db->update($this->table, $data, $this->compileWheres(), $this->bindings);
    }

    public function delete(): int
    {
        if ($this->wheres === []) {
            throw new InvalidArgumentException('Từ chối DELETE không có điều kiện WHERE.');
        }
        return $this->db->delete($this->table, $this->compileWheres(), $this->bindings);
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . ' FROM ' . $this->tableSql();
        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        if ($this->wheres !== []) {
            $sql .= ' WHERE ' . $this->compileWheres();
        }
        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
            if ($this->offset !== null) {
                $sql .= ' OFFSET ' . $this->offset;
            }
        }
        return $sql;
    }

    /** @return array<int, mixed> */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /** @param array<int, mixed> $bindings */
    private function addWhere(string $sql, array $bindings, string $boolean = 'AND'): self
    {
        $this->wheres[] = ['bool' => strtoupper($boolean) === 'OR' ? 'OR' : 'AND', 'sql' => $sql];
        array_push($this->bindings, ...$bindings);
        return $this;
    }

    private function compileWheres(): string
    {
        $sql = '';
        foreach ($this->wheres as $i => $where) {
            $sql .= ($i === 0 ? '' : ' ' . $where['bool'] . ' ') . $where['sql'];
        }
        return $sql;
    }

    private function tableSql(): string
    {
        $parts = preg_split('/\s+as\s+|\s+/i', trim($this->table)) ?: [$this->table];
        $sql = $this->db->quoteIdentifier($parts[0]);
        return isset($parts[1]) ? $sql . ' ' . $this->db->quoteIdentifier($parts[1]) : $sql;
    }

    private function col(string $column): string
    {
        return $this->db->quoteIdentifier($column);
    }
}
