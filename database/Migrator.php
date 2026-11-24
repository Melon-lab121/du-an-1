<?php

declare(strict_types=1);

namespace Database;

use App\Core\Database;
use RuntimeException;

/**
 * Chạy file .sql trong database/migrations và modules/*\/migrations theo thứ tự tên file.
 * Migration đã chạy được ghi vào bảng `migrations` nên mỗi file chỉ chạy một lần.
 *
 * Thêm tính năng mới => tạo file mới, vd: 2026_02_01_000001_create_coupons_table.sql
 */
final class Migrator
{
    /** @param string[] $paths */
    public function __construct(private Database $db, private array $paths)
    {
    }

    /** @return string[] tên migration vừa chạy */
    public function migrate(?callable $output = null): array
    {
        $this->ensureTable();
        $ran = array_column($this->db->select('SELECT migration FROM migrations'), 'migration');
        $batch = (int) $this->db->scalar('SELECT COALESCE(MAX(batch), 0) FROM migrations') + 1;
        $done = [];

        foreach ($this->files() as $name => $file) {
            if (in_array($name, $ran, true)) {
                continue;
            }
            $output && $output("Migrating: {$name}");
            foreach (self::splitStatements((string) file_get_contents($file)) as $statement) {
                try {
                    $this->db->pdo()->exec($statement);
                } catch (\PDOException $e) {
                    throw new RuntimeException("Lỗi ở migration {$name}: " . $e->getMessage() . "\nSQL: " . mb_substr($statement, 0, 300), 0, $e);
                }
            }
            $this->db->insert('migrations', ['migration' => $name, 'batch' => $batch, 'ran_at' => date('Y-m-d H:i:s')]);
            $done[] = $name;
        }

        return $done;
    }

    /** Xoá toàn bộ bảng (chỉ dùng khi dev). */
    public function dropAllTables(): void
    {
        if ($this->db->driver() !== 'mysql') {
            throw new RuntimeException('dropAllTables chỉ hỗ trợ MySQL.');
        }
        $tables = $this->db->select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']);
        $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $row) {
            $this->db->pdo()->exec('DROP TABLE IF EXISTS ' . $this->db->quoteIdentifier((string) reset($row)));
        }
        $this->db->pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /** @return array<string, string> tên => đường dẫn */
    public function files(): array
    {
        $files = [];
        foreach ($this->paths as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $files[basename($file, '.sql')] = $file;
            }
        }
        ksort($files);
        return $files;
    }

    /** Tách câu lệnh theo dấu ; ở cuối dòng, bỏ comment "--". @return string[] */
    public static function splitStatements(string $sql): array
    {
        $lines = array_filter(
            preg_split('/\R/', $sql) ?: [],
            fn (string $line): bool => !str_starts_with(ltrim($line), '--'),
        );
        $statements = preg_split('/;\s*(\R|$)/', implode("\n", $lines)) ?: [];
        return array_values(array_filter(array_map('trim', $statements), fn (string $s): bool => $s !== ''));
    }

    private function ensureTable(): void
    {
        $this->db->pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (migration VARCHAR(190) NOT NULL PRIMARY KEY, batch INT NOT NULL, ran_at DATETIME NULL)'
        );
    }
}
