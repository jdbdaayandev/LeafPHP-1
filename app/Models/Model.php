<?php
declare(strict_types=1);
namespace App\Models;
use LeafPHP\Database\DB;
use LeafPHP\Database\QueryBuilder;
abstract class Model
{
 protected string $table=''; protected string $primaryKey='id'; protected string $connection='mysql'; protected array $attributes=[];
 public function __construct(array $attributes=[]) {$this->attributes=$attributes;}
 public function table(): string { return $this->table; } public function connectionName(): string { return $this->connection; } public function getKey(): mixed { return $this->attributes[$this->primaryKey]??null; } public function getAttributes(): array { return $this->attributes; }
 public function __get(string $key): mixed { return $this->attributes[$key]??null; } public function __isset(string $key): bool { return isset($this->attributes[$key]); }
 public static function query(): QueryBuilder { $model=new static(); return DB::table($model->table,$model->connection); }
 public static function all(): array { return array_map(fn($row)=>new static($row),static::query()->get()); } public static function find(int|string $id): ?static { $m=new static();$row=DB::table($m->table,$m->connection)->where($m->primaryKey,$id)->first();return $row?new static($row):null; }
 public static function where(string $column,mixed $operator,mixed $value=null): QueryBuilder { $q=static::query();return func_num_args()===2?$q->where($column,$operator):$q->where($column,$operator,$value); }
 public static function create(array $attributes): static { $m=new static();$id=DB::table($m->table,$m->connection)->insertGetId($attributes);$row=DB::table($m->table,$m->connection)->where($m->primaryKey,$id)->first();return new static($row?:array_merge($attributes,[$m->primaryKey=>$id])); }
 public function hasMany(string $related,string $foreignKey,?string $localKey=null): QueryBuilder { $parentKey=$this->attributes[$localKey??$this->primaryKey]??null;return (new $related())->newQuery()->where($foreignKey,$parentKey); }
 public function hasOne(string $related,string $foreignKey,?string $localKey=null): ?array { return $this->hasMany($related,$foreignKey,$localKey)->first(); }
 public function belongsTo(string $related,string $foreignKey,?string $ownerKey=null): ?array { $value=$this->attributes[$foreignKey]??null;if($value===null)return null;$m=new $related();return $m->newQuery()->where($ownerKey??$m->primaryKey,$value)->first(); }
 public function belongsToMany(string $related,string $pivotTable,string $foreignPivotKey,string $relatedPivotKey,?string $localKey=null,?string $relatedKey=null): array { $key=$this->attributes[$localKey??$this->primaryKey]??null;$m=new $related();return DB::table($pivotTable,$this->connection)->join($m->table,$m->table.'.'.($relatedKey??$m->primaryKey),'=', $pivotTable.'.'.$relatedPivotKey)->where($pivotTable.'.'.$foreignPivotKey,$key)->select($m->table.'.*')->get(); }
 public function newQuery(): QueryBuilder { return DB::table($this->table,$this->connection); }
 public function save(): bool { $id=$this->getKey();if($id===null){$new=static::create($this->attributes);$this->attributes=$new->attributes;return true;} $attributes=$this->attributes;unset($attributes[$this->primaryKey]);return DB::table($this->table,$this->connection)->where($this->primaryKey,$id)->update($attributes)>0; }
 public function delete(): int { $id=$this->getKey();return $id===null?0:DB::table($this->table,$this->connection)->where($this->primaryKey,$id)->delete(); }
}
