<?php
declare(strict_types=1);
namespace LeafPHP\Database\Schema;
final class Blueprint
{
    private array $columns = []; private ?string $pending = null;
    public function id(): self { $this->columns[] = '`id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY'; return $this; }
    public function string(string $name, int $length = 255): self { $this->pending = $this->identifier($name) . ' VARCHAR(' . max(1,min(4000,$length)) . ')'; $this->columns[] = $this->pending; return $this; }
    public function text(string $name): self { $this->pending = $this->identifier($name) . ' TEXT'; $this->columns[] = $this->pending; return $this; }
    public function integer(string $name): self { $this->pending = $this->identifier($name) . ' INT'; $this->columns[] = $this->pending; return $this; }
    public function boolean(string $name): self { $this->pending = $this->identifier($name) . ' TINYINT(1)'; $this->columns[] = $this->pending; return $this; }
    public function timestamps(): self { $this->columns[] = '`created_at` TIMESTAMP NULL DEFAULT NULL'; $this->columns[] = '`updated_at` TIMESTAMP NULL DEFAULT NULL'; $this->pending = null; return $this; }
    public function nullable(): self { $this->modifyLast(' NULL'); return $this; }
    public function default(mixed $value): self { $v = is_numeric($value) ? (string)$value : "'" . str_replace("'", "''", (string)$value) . "'"; $this->modifyLast(' DEFAULT ' . $v); return $this; }
    public function unique(): self { $this->modifyLast(' UNIQUE'); return $this; }
    public function index(): self { $this->modifyLast(' INDEX'); return $this; }
    public function definitions(): array { return $this->columns; }
    private function modifyLast(string $suffix): void { $i = count($this->columns)-1; if ($i < 0) throw new \LogicException('Define a column first'); $this->columns[$i] .= $suffix; }
    private function identifier(string $name): string { if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) throw new \InvalidArgumentException('Invalid SQL identifier'); return '`' . $name . '`'; }
}
