<?php

declare(strict_types=1);

namespace LeafPHP\Database\Schema;

/**
 * Fluent table definition used by Schema::create().
 *
 * Supports Laravel-style column modifiers such as nullable(), default(),
 * primary(), unique(), and index(). Indexes are compiled separately so the
 * generated SQL remains valid for supported database drivers.
 */
final class Blueprint
{
    /** @var list<string> */
    private array $columns = [];

    /** @var list<array{type:string, columns:list<string>, name:?string}> */
    private array $indexes = [];

    private ?int $lastColumn = null;
    private ?string $lastColumnName = null;

    public function id(string $name = 'id'): self
    {
        $this->addColumn($name, 'BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY');
        return $this;
    }

    public function string(string $name, int $length = 255): self
    {
        $length = max(1, min(4000, $length));
        $this->addColumn($name, 'VARCHAR(' . $length . ') NOT NULL');
        return $this;
    }

    public function text(string $name): self
    {
        $this->addColumn($name, 'TEXT NOT NULL');
        return $this;
    }

    public function longText(string $name): self
    {
        $this->addColumn($name, 'LONGTEXT NOT NULL');
        return $this;
    }

    public function integer(string $name): self
    {
        $this->addColumn($name, 'INT NOT NULL');
        return $this;
    }

    public function unsignedInteger(string $name): self
    {
        $this->addColumn($name, 'INT UNSIGNED NOT NULL');
        return $this;
    }

    public function bigInteger(string $name): self
    {
        $this->addColumn($name, 'BIGINT NOT NULL');
        return $this;
    }

    public function unsignedBigInteger(string $name): self
    {
        $this->addColumn($name, 'BIGINT UNSIGNED NOT NULL');
        return $this;
    }

    public function boolean(string $name): self
    {
        $this->addColumn($name, 'TINYINT(1) NOT NULL');
        return $this;
    }

    public function timestamp(string $name): self
    {
        $this->addColumn($name, 'TIMESTAMP NULL');
        return $this;
    }

    public function date(string $name): self
    {
        $this->addColumn($name, 'DATE NULL');
        return $this;
    }

    public function dateTime(string $name): self
    {
        $this->addColumn($name, 'DATETIME NULL');
        return $this;
    }

    public function timestamps(): self
    {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');
        return $this;
    }

    public function nullable(): self
    {
        $this->modifyLast(static function (string $definition): string {
            $definition = preg_replace('/\s+NOT NULL\b/i', '', $definition) ?? $definition;
            return preg_match('/\sNULL\b/i', $definition) ? $definition : $definition . ' NULL';
        });
        return $this;
    }

    public function default(mixed $value): self
    {
        if ($value === null) {
            $sqlValue = 'NULL';
        } elseif (is_bool($value)) {
            $sqlValue = $value ? '1' : '0';
        } elseif (is_int($value) || is_float($value)) {
            $sqlValue = (string) $value;
        } else {
            $sqlValue = "'" . str_replace("'", "''", (string) $value) . "'";
        }

        $this->modifyLast(static function (string $definition) use ($sqlValue): string {
            $definition = preg_replace('/\s+DEFAULT\s+[^,]+$/i', '', $definition) ?? $definition;
            return $definition . ' DEFAULT ' . $sqlValue;
        });
        return $this;
    }

    public function primary(?string $name = null): self
    {
        $this->modifyLast(static function (string $definition): string {
            return preg_match('/\bPRIMARY KEY\b/i', $definition) ? $definition : $definition . ' PRIMARY KEY';
        });
        return $this;
    }

    public function unique(string|array|null $columns = null, ?string $name = null): self
    {
        $this->addIndex('unique', $columns, $name);
        return $this;
    }

    public function index(string|array|null $columns = null, ?string $name = null): self
    {
        $this->addIndex('index', $columns, $name);
        return $this;
    }

    /** @return list<string> */
    public function definitions(string $driver = 'mysql'): array
    {
        if ($driver !== 'sqlite') {
            return $this->columns;
        }

        return array_map(static function (string $definition): string {
            $definition = str_replace('BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY', 'INTEGER PRIMARY KEY AUTOINCREMENT', $definition);
            $definition = preg_replace('/\bBIGINT UNSIGNED\b/i', 'INTEGER', $definition) ?? $definition;
            $definition = preg_replace('/\bBIGINT\b/i', 'INTEGER', $definition) ?? $definition;
            $definition = preg_replace('/\bINT UNSIGNED\b/i', 'INTEGER', $definition) ?? $definition;
            $definition = preg_replace('/\bINT\b/i', 'INTEGER', $definition) ?? $definition;
            $definition = preg_replace('/TINYINT\(1\)/i', 'INTEGER', $definition) ?? $definition;
            $definition = preg_replace('/\bLONGTEXT\b/i', 'TEXT', $definition) ?? $definition;
            return $definition;
        }, $this->columns);
    }

    /** @return list<array{type:string, columns:list<string>, name:?string}> */
    public function indexes(): array
    {
        return $this->indexes;
    }

    private function addColumn(string $name, string $definition): void
    {
        $quoted = $this->quoteIdentifier($name);
        $this->columns[] = $quoted . ' ' . $definition;
        $this->lastColumn = array_key_last($this->columns);
        $this->lastColumnName = $name;
    }

    private function modifyLast(callable $modifier): void
    {
        $this->assertLastColumn();
        $this->columns[$this->lastColumn] = $modifier($this->columns[$this->lastColumn]);
    }

    private function addIndex(string $type, string|array|null $columns, ?string $name): void
    {
        if ($columns === null) {
            $this->assertLastColumn();
            $columns = [$this->lastColumnName];
        } elseif (is_string($columns)) {
            $columns = [$columns];
        }
        $indexName = $name;

        foreach ($columns as $column) {
            if (!is_string($column) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
                throw new \InvalidArgumentException('Invalid index column name.');
            }
        }
        if ($columns === []) {
            throw new \InvalidArgumentException('An index must contain at least one column.');
        }
        $this->indexes[] = ['type' => $type, 'columns' => array_values($columns), 'name' => $indexName];
    }

    private function assertLastColumn(): void
    {
        if ($this->lastColumn === null || $this->lastColumnName === null) {
            throw new \LogicException('Define a column before applying a modifier or index.');
        }
    }

    private function quoteIdentifier(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid SQL identifier: ' . $name);
        }
        return '`' . $name . '`';
    }
}
