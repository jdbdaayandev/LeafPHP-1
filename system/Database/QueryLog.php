<?php
declare(strict_types=1);
namespace LeafPHP\Database;
final class QueryLog
{
    private static array $queries=[];
    public static function add(string $sql, array $bindings, float $milliseconds, string $connection): void { $sensitive=(bool)preg_match('/sessions|password|secret|token|authorization|api.?key/i',$sql); self::$queries[]=['sql'=>$sql,'bindings'=>$sensitive?array_fill(0,count($bindings),'[redacted]'):self::redactBindings($bindings),'time_ms'=>round($milliseconds,3),'connection'=>$connection]; }
    public static function all(): array { return self::$queries; }
    public static function reset(): void { self::$queries=[]; }
    private static function redactBindings(array $bindings): array { return array_map(static fn($v)=>is_string($v)&&strlen($v)>160?substr($v,0,157).'...':$v,$bindings); }
}
