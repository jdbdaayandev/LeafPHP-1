<?php
declare(strict_types=1);

namespace LeafPHP\Database;

use InvalidArgumentException;
use PDO;

/** Fluent, parameterized SQL builder for the configured PDO connection (MySQL). */
final class QueryBuilder
{
    private array $wheres = [];
    private array $bindings = [];
    private array $joins = [];
    private array $orders = [];
    private array $groups = [];
    private array $havings = [];
    private array $selects = ['*'];
    private array $unions = [];
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private bool $distinctValue = false;

    public function __construct(private string $table, private ?string $connectionName = null) { $this->table = Connection::tableName($table); $this->identifier($this->table, true); }

    public function select(string ...$columns): self
    {
        if ($columns === []) $columns = ['*'];
        foreach ($columns as $column) $this->identifier($column, true);
        $this->selects = $columns;
        return $this;
    }
    public function addSelect(string ...$columns): self { foreach ($columns as $c) $this->identifier($c, true); $this->selects = array_merge($this->selects, $columns); return $this; }
    public function distinct(bool $value = true): self { $this->distinctValue = $value; return $this; }

    public function where(string $column, mixed $operator, mixed $value = null): self { return func_num_args() === 2 ? $this->addWhere('AND', $column, '=', $operator) : $this->addWhere('AND', $column, (string)$operator, $value); }
    public function orWhere(string $column, mixed $operator, mixed $value = null): self { return func_num_args() === 2 ? $this->addWhere('OR', $column, '=', $operator) : $this->addWhere('OR', $column, (string)$operator, $value); }
    public function whereNull(string $column, string $boolean = 'AND'): self { $this->identifier($column, true); $this->wheres[] = [$boolean, "{$this->wrap($column)} IS NULL", []]; return $this; }
    public function whereNotNull(string $column, string $boolean = 'AND'): self { $this->identifier($column, true); $this->wheres[] = [$boolean, "{$this->wrap($column)} IS NOT NULL", []]; return $this; }
    public function orWhereNull(string $column): self { return $this->whereNull($column, 'OR'); }
    public function orWhereNotNull(string $column): self { return $this->whereNotNull($column, 'OR'); }
    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        $this->identifier($column, true);
        if ($values === []) return $this->whereRawSafeConstant($boolean, $not ? '1 = 1' : '1 = 0');
        $this->wheres[] = [$this->boolean($boolean), $this->wrap($column) . ($not ? ' NOT IN (' : ' IN (') . implode(', ', array_fill(0, count($values), '?')) . ')', array_values($values)];
        return $this;
    }
    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self { return $this->whereIn($column, $values, $boolean, true); }
    public function orWhereIn(string $column, array $values): self { return $this->whereIn($column, $values, 'OR'); }
    public function whereBetween(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        $this->identifier($column, true); if (count($values) !== 2) throw new InvalidArgumentException('whereBetween expects exactly two values.');
        $this->wheres[] = [$this->boolean($boolean), $this->wrap($column) . ($not ? ' NOT BETWEEN ? AND ?' : ' BETWEEN ? AND ?'), array_values($values)]; return $this;
    }
    public function whereNotBetween(string $column, array $values, string $boolean = 'AND'): self { return $this->whereBetween($column, $values, $boolean, true); }
    public function whereColumn(string $first, string $operator, string $second, string $boolean = 'AND'): self
    {
        $this->identifier($first, true); $this->identifier($second, true); $operator = $this->operator($operator);
        $this->wheres[] = [$this->boolean($boolean), $this->wrap($first) . " {$operator} " . $this->wrap($second), []]; return $this;
    }
    public function whereNested(callable $callback, string $boolean = 'AND'): self
    {
        $nested = new self($this->table, $this->connectionName); $callback($nested); [$sql, $bindings] = $nested->compileWheres();
        if ($sql !== '') $this->wheres[] = [$this->boolean($boolean), '(' . $sql . ')', $bindings]; return $this;
    }
    public function whereExists(string $sql, array $bindings = [], string $boolean = 'AND', bool $not = false): self
    {
        if (trim($sql) === '') throw new InvalidArgumentException('Subquery cannot be empty.');
        $this->wheres[] = [$this->boolean($boolean), ($not ? 'NOT ' : '') . 'EXISTS (' . $sql . ')', array_values($bindings)]; return $this;
    }
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        if (trim($sql) === '') throw new InvalidArgumentException('Raw condition cannot be empty.');
        $this->wheres[] = [$this->boolean($boolean), '(' . $sql . ')', array_values($bindings)]; return $this;
    }
    private function addWhere(string $boolean, string $column, mixed $operator, mixed $value): self
    {
        $this->identifier($column, true);
        if (func_num_args() >= 4) $op = $this->operator((string)$operator); else { $value = $operator; $op = '='; }
        if ($value === null && in_array($op, ['=', '!=', '<>'], true)) { $this->wheres[] = [$this->boolean($boolean), $this->wrap($column) . ($op === '=' ? ' IS NULL' : ' IS NOT NULL'), []]; return $this; }
        $this->wheres[] = [$this->boolean($boolean), $this->wrap($column) . " {$op} ?", [$value]]; return $this;
    }
    private function whereRawSafeConstant(string $boolean, string $sql): self { $this->wheres[] = [$this->boolean($boolean), $sql, []]; return $this; }

    public function join(string $table, string $first, string $operator, string $second): self { return $this->addJoin('INNER', $table, $first, $operator, $second); }
    public function innerJoin(string $table, string $first, string $operator, string $second): self { return $this->join($table, $first, $operator, $second); }
    public function leftJoin(string $table, string $first, string $operator, string $second): self { return $this->addJoin('LEFT', $table, $first, $operator, $second); }
    public function rightJoin(string $table, string $first, string $operator, string $second): self { return $this->addJoin('RIGHT', $table, $first, $operator, $second); }
    public function crossJoin(string $table): self { $table = Connection::tableName($table); $this->identifier($table, true); $this->joins[] = ['CROSS', $table, '', '', '']; return $this; }
    private function addJoin(string $type, string $table, string $first, string $operator, string $second): self
    { $table = Connection::tableName($table); $this->identifier($table, true); $this->identifier($first, true); $this->identifier($second, true); $this->joins[] = [$type, $table, $first, $this->operator($operator), $second]; return $this; }

    public function orderBy(string $column, string $direction = 'asc'): self { $this->identifier($column, true); $direction = strtolower($direction); if (!in_array($direction, ['asc','desc'], true)) throw new InvalidArgumentException('Order direction must be asc or desc.'); $this->orders[] = [$column, strtoupper($direction)]; return $this; }
    public function latest(string $column = 'created_at'): self { return $this->orderBy($column, 'desc'); }
    public function oldest(string $column = 'created_at'): self { return $this->orderBy($column, 'asc'); }
    public function inRandomOrder(): self { $this->orders[] = ['__RAW_RAND__', '']; return $this; }
    public function groupBy(string ...$columns): self { foreach ($columns as $c) $this->identifier($c, true); $this->groups = array_merge($this->groups, $columns); return $this; }
    public function having(string $column, string $operator, mixed $value, string $boolean = 'AND'): self { $this->identifier($column, true); $this->havings[] = [$this->boolean($boolean), $this->wrap($column) . ' ' . $this->operator($operator) . ' ?', [$value]]; return $this; }
    public function orHaving(string $column, string $operator, mixed $value): self { return $this->having($column, $operator, $value, 'OR'); }
    public function limit(int $limit): self { if ($limit < 0) throw new InvalidArgumentException('Limit cannot be negative.'); $this->limitValue = $limit; return $this; }
    public function offset(int $offset): self { if ($offset < 0) throw new InvalidArgumentException('Offset cannot be negative.'); $this->offsetValue = $offset; return $this; }
    public function skip(int $offset): self { return $this->offset($offset); }
    public function take(int $limit): self { return $this->limit($limit); }

    public function get(): array { [$sql, $bindings] = $this->compileSelect(); return DB::select($sql, $bindings, $this->connectionName); }
    public function first(): ?array { return $this->limit(1)->get()[0] ?? null; }
    public function value(string $column): mixed { $row = $this->select($column)->first(); return $row ? (array_values($row)[0] ?? null) : null; }
    public function pluck(string $column, ?string $key = null): array
    { $this->identifier($column, true); if ($key !== null) $this->identifier($key, true); $rows = $this->select(...array_values(array_filter([$column, $key])))->get(); $out = []; foreach ($rows as $r) { $v = $r[$column] ?? array_values($r)[0] ?? null; if ($key === null) $out[] = $v; else $out[$r[$key] ?? null] = $v; } return $out; }
    public function count(string $column = '*'): int { return (int)$this->aggregate('COUNT', $column); }
    public function sum(string $column): int|float { return $this->numericAggregate($this->aggregate('SUM', $column)) ?? 0; }
    public function avg(string $column): int|float|null { return $this->numericAggregate($this->aggregate('AVG', $column)); }
    public function min(string $column): mixed { return $this->aggregate('MIN', $column); }
    public function max(string $column): mixed { return $this->aggregate('MAX', $column); }
    public function exists(): bool { return $this->limit(1)->count() > 0; }
    public function doesntExist(): bool { return !$this->exists(); }
    private function numericAggregate(mixed $value): int|float|null { if ($value === null || !is_numeric($value)) return null; $string = (string)$value; return str_contains($string, '.') ? (float)$string : (int)$string; }
    private function aggregate(string $function, string $column): mixed
    { if ($column !== '*') $this->identifier($column, true); $old = $this->selects; $this->selects = [$function . '(' . $column . ') AS aggregate']; $oldLimit=$this->limitValue; $oldOffset=$this->offsetValue; $this->limitValue=null; $this->offsetValue=null; try { return $this->get()[0]['aggregate'] ?? null; } finally { $this->selects=$old; $this->limitValue=$oldLimit; $this->offsetValue=$oldOffset; } }

    public function insert(array $values): bool
    { if ($values === []) throw new InvalidArgumentException('Insert values cannot be empty.'); if (array_is_list($values) && isset($values[0]) && is_array($values[0])) return $this->insertMany($values); $columns = array_keys($values); foreach ($columns as $c) $this->identifier((string)$c); $sql = 'INSERT INTO ' . $this->wrap($this->table) . ' (' . implode(', ', array_map(fn($c)=>$this->wrap((string)$c), $columns)) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')'; return DB::affectingStatement($sql, array_values($values), $this->connectionName) > 0; }
    public function insertMany(array $rows): bool
    { if ($rows === []) return true; $columns = array_keys($rows[0]); foreach ($columns as $c) $this->identifier((string)$c); $values=[]; $groups=[]; foreach ($rows as $row) { if (array_keys($row) !== $columns) throw new InvalidArgumentException('Bulk insert rows must have identical columns in the same order.'); $groups[]='(' . implode(', ', array_fill(0,count($columns),'?')) . ')'; array_push($values,...array_values($row)); } $sql='INSERT INTO '.$this->wrap($this->table).' ('.implode(', ',array_map(fn($c)=>$this->wrap((string)$c),$columns)).') VALUES '.implode(', ',$groups); return DB::affectingStatement($sql,$values,$this->connectionName) > 0; }
    public function insertOrIgnore(array $values): bool
    {
        if ($values === []) throw new InvalidArgumentException('Insert values cannot be empty.');
        $columns = array_keys($values); foreach ($columns as $c) $this->identifier((string)$c);
        $driver = DB::connection($this->connectionName)->getAttribute(PDO::ATTR_DRIVER_NAME);
        $verb = $driver === 'sqlite' ? 'INSERT OR IGNORE INTO ' : ($driver === 'pgsql' ? 'INSERT INTO ' : 'INSERT IGNORE INTO ');
        $sql = $verb . $this->wrap($this->table) . ' (' . implode(', ', array_map(fn($c) => $this->wrap((string)$c), $columns)) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')' . ($driver === 'pgsql' ? ' ON CONFLICT DO NOTHING' : '');
        return DB::affectingStatement($sql, array_values($values), $this->connectionName) > 0;
    }
    /** MySQL upsert. $uniqueBy must match a database UNIQUE/PRIMARY KEY constraint. */
    public function upsert(array $values, array $uniqueBy, ?array $update = null): int
    {
        if ($values === [] || $uniqueBy === []) throw new InvalidArgumentException('Upsert values and unique columns cannot be empty.');
        $rows = array_is_list($values) && isset($values[0]) && is_array($values[0]) ? $values : [$values];
        $columns = array_keys($rows[0]); foreach ($columns as $c) $this->identifier((string)$c);
        foreach ($uniqueBy as $c) { $this->identifier((string)$c); if (!in_array($c, $columns, true)) throw new InvalidArgumentException('Unique columns must be present in the values.'); }
        $update ??= array_values(array_diff($columns, $uniqueBy));
        if ($update === []) throw new InvalidArgumentException('Upsert needs at least one update column.');
        foreach ($update as $c) { $this->identifier((string)$c); if (!in_array($c, $columns, true)) throw new InvalidArgumentException('Update columns must be present in the values.'); }
        $groups=[]; $bindings=[];
        foreach ($rows as $row) { if (array_keys($row) !== $columns) throw new InvalidArgumentException('Upsert rows must have identical columns in the same order.'); $groups[]='('.implode(', ',array_fill(0,count($columns),'?')).')'; array_push($bindings,...array_values($row)); }
        $driver = DB::connection($this->connectionName)->getAttribute(PDO::ATTR_DRIVER_NAME);
        $sql='INSERT INTO '.$this->wrap($this->table).' ('.implode(', ',array_map(fn($c)=>$this->wrap((string)$c),$columns)).') VALUES '.implode(', ',$groups);
        if ($driver === 'sqlite' || $driver === 'pgsql') {
            $assignments = array_map(fn($c) => $this->wrap((string)$c).' = excluded.'.$this->wrap((string)$c), $update);
            $sql .= ' ON CONFLICT (' . implode(', ', array_map(fn($c) => $this->wrap((string)$c), $uniqueBy)) . ') DO UPDATE SET ' . implode(', ', $assignments);
        } else {
            $assignments = array_map(fn($c) => $this->wrap((string)$c).' = VALUES('.$this->wrap((string)$c).')', $update);
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
        }
        return DB::affectingStatement($sql,$bindings,$this->connectionName);
    }
    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        if ($attributes === []) throw new InvalidArgumentException('Update-or-insert conditions cannot be empty.');
        $query = new self($this->table, $this->connectionName); foreach ($attributes as $column => $value) $query->where((string)$column, $value);
        if ($query->exists()) { if ($values === []) return true; foreach ($attributes as $column => $value) $this->where((string)$column, $value); return $this->update($values) > 0; }
        return $this->insert(array_merge($attributes, $values));
    }
    public function insertGetId(array $values, ?string $sequence = null): string|false { $this->insert($values); return DB::connection($this->connectionName)->lastInsertId($sequence); }
    public function update(array $values): int
    { if ($values === []) throw new InvalidArgumentException('Update values cannot be empty.'); $columns=array_keys($values); foreach($columns as $c)$this->identifier((string)$c); [$where,$whereBindings]=$this->compileWheres(); $sql='UPDATE '.$this->wrap($this->table).' SET '.implode(', ',array_map(fn($c)=>$this->wrap((string)$c).' = ?',$columns)); if($where!=='')$sql.=' WHERE '.$where; return DB::affectingStatement($sql,[...array_values($values),...$whereBindings],$this->connectionName); }
    public function delete(): int { [$where,$bindings]=$this->compileWheres(); $sql='DELETE FROM '.$this->wrap($this->table).($where!==''?' WHERE '.$where:''); return DB::affectingStatement($sql,$bindings,$this->connectionName); }
    public function increment(string $column, int|float $amount = 1, array $extra = []): int { return $this->adjust($column, $amount, '+', $extra); }
    public function decrement(string $column, int|float $amount = 1, array $extra = []): int { return $this->adjust($column, $amount, '-', $extra); }
    private function adjust(string $column, int|float $amount, string $operator, array $extra): int
    { $this->identifier($column); $sets=[$this->wrap($column).' = '.$this->wrap($column).' '.$operator.' ?']; $bindings=[$amount]; foreach($extra as $c=>$v){$this->identifier((string)$c);$sets[]=$this->wrap((string)$c).' = ?';$bindings[]=$v;} [$where,$wb]=$this->compileWheres(); $sql='UPDATE '.$this->wrap($this->table).' SET '.implode(', ',$sets).($where!==''?' WHERE '.$where:''); return DB::affectingStatement($sql,[...$bindings,...$wb],$this->connectionName); }

    public function paginate(int $perPage = 15, ?int $page = null): array
    { if ($perPage < 1) throw new InvalidArgumentException('Page size must be at least 1.'); $page = max(1, $page ?? (int)($_GET['page'] ?? 1)); $total = $this->count(); $last = max(1, (int)ceil($total / $perPage)); $oldLimit=$this->limitValue; $oldOffset=$this->offsetValue; $this->limit($perPage)->offset(($page-1)*$perPage); try { $data=$this->get(); } finally { $this->limitValue=$oldLimit; $this->offsetValue=$oldOffset; } return ['data'=>$data,'total'=>$total,'per_page'=>$perPage,'current_page'=>$page,'last_page'=>$last,'from'=>$total ? (($page-1)*$perPage+1) : null,'to'=>$total ? min($page*$perPage,$total) : null,'has_more_pages'=>$page<$last,'prev_page'=>$page>1?$page-1:null,'next_page'=>$page<$last?$page+1:null]; }
    public function simplePaginate(int $perPage = 15, ?int $page = null): array
    { if ($perPage < 1) throw new InvalidArgumentException('Page size must be at least 1.'); $page=max(1,$page??(int)($_GET['page']??1)); $old=$this->limitValue; $off=$this->offsetValue; $this->limit($perPage+1)->offset(($page-1)*$perPage); try{$rows=$this->get();}finally{$this->limitValue=$old;$this->offsetValue=$off;} $more=count($rows)>$perPage; if($more)array_pop($rows); return ['data'=>$rows,'per_page'=>$perPage,'current_page'=>$page,'has_more_pages'=>$more,'prev_page'=>$page>1?$page-1:null,'next_page'=>$more?$page+1:null]; }

    public function toSql(): string { return $this->compileSelect()[0]; }
    public function getBindings(): array { return $this->compileSelect()[1]; }
    public function toRawSql(): string { $sql=$this->toSql(); foreach($this->getBindings() as $v){$replacement=is_numeric($v)?(string)$v:( $v===null?'NULL':"'".str_replace("'","''",(string)$v)."'"); $sql=preg_replace('/\?/', $replacement, $sql, 1) ?? $sql;} return $sql; }

    private function compileSelect(): array
    {
        $columns = array_map(function ($c) { if ($c === '*') return '*'; if (preg_match('/^(COUNT|SUM|AVG|MIN|MAX)\((\*|[A-Za-z_][A-Za-z0-9_.]*)\) AS aggregate$/i', $c, $m)) return strtoupper($m[1]) . '(' . ($m[2] === '*' ? '*' : $this->wrap($m[2])) . ') AS aggregate'; return $this->wrap($c); }, $this->selects);
        $sql = 'SELECT ' . ($this->distinctValue ? 'DISTINCT ' : '') . implode(', ', $columns) . ' FROM ' . $this->wrap($this->table);
        foreach ($this->joins as [$type,$table,$first,$operator,$second]) $sql .= $type === 'CROSS' ? ' CROSS JOIN '.$this->wrap($table) : " {$type} JOIN ".$this->wrap($table).' ON '.$this->wrap($first)." {$operator} ".$this->wrap($second);
        [$where,$bindings] = $this->compileWheres(); if ($where !== '') $sql .= ' WHERE '.$where;
        if ($this->groups) $sql .= ' GROUP BY '.implode(', ',array_map(fn($c)=>$this->wrap($c),$this->groups));
        if ($this->havings) { $parts=[]; $hb=[]; foreach($this->havings as $i=>[$b,$s,$v]){$parts[]=($i?$b.' ':'').$s;array_push($hb,...$v);} $sql.=' HAVING '.implode(' ',$parts); array_push($bindings,...$hb); }
        if ($this->orders) $sql .= ' ORDER BY '.implode(', ',array_map(fn($o)=>$o[0]==='__RAW_RAND__'?(DB::connection($this->connectionName)->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'RANDOM()':'RAND()'):$this->wrap($o[0]).' '.$o[1],$this->orders));
        if ($this->limitValue !== null) $sql .= ' LIMIT '.$this->limitValue;
        if ($this->offsetValue !== null) { if ($this->limitValue === null) $sql .= DB::connection($this->connectionName)->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' ? ' LIMIT -1' : ' LIMIT 18446744073709551615'; $sql .= ' OFFSET '.$this->offsetValue; }
        return [$sql,$bindings];
    }
    private function compileWheres(): array { $parts=[]; $bindings=[]; foreach($this->wheres as $i=>[$boolean,$sql,$values]){$parts[]=($i?$boolean.' ':'').$sql;array_push($bindings,...$values);} return [implode(' ',$parts),$bindings]; }
    private function operator(string $operator): string { $operator=strtoupper(trim($operator)); if(!in_array($operator,['=','!=','<>','>','<','>=','<=','LIKE','NOT LIKE','REGEXP'],true)) throw new InvalidArgumentException('Unsupported SQL operator.'); return $operator; }
    private function boolean(string $boolean): string { $boolean=strtoupper($boolean); if(!in_array($boolean,['AND','OR'],true)) throw new InvalidArgumentException('Boolean connector must be AND or OR.'); return $boolean; }
    private function identifier(string $name, bool $allowWildcard = false): void { $pattern=$allowWildcard?'/^[A-Za-z_*][A-Za-z0-9_*.]*$/':'/^[A-Za-z_][A-Za-z0-9_]*$/'; if(!preg_match($pattern,$name) || str_contains($name,'..') || str_contains($name,'***')) throw new InvalidArgumentException('Invalid SQL identifier: '.$name); }
    private function wrap(string $name): string { if($name==='*')return '*'; $this->identifier($name,true); $parts=explode('.',$name); if(count($parts)>1 && $parts[0] !== '*') $parts[0]=Connection::tableName($parts[0]); return implode('.',array_map(static fn($part)=>$part==='*'?'*':'`'.$part.'`',$parts)); }
}
