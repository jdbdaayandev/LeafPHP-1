<?php
declare(strict_types=1);
namespace LeafPHP\Security;
use LeafPHP\Database\DB;
use InvalidArgumentException;
final class Auth
{
 private const SESSION_KEY='_leafphp_auth_user_id'; private static ?array $cachedUser=null;
 public static function attempt(array $credentials, string $table='users', string $usernameColumn='email', string $passwordColumn='password', ?string $connection=null): bool { $username=$credentials[$usernameColumn]??null; $password=$credentials['password']??null; if(!is_string($username)||!is_string($password))return false; self::safeIdentifier($table);self::safeIdentifier($usernameColumn);self::safeIdentifier($passwordColumn); $row=DB::table($table,$connection)->where($usernameColumn,$username)->first(); if(!$row||!isset($row[$passwordColumn])||!Hash::check($password,(string)$row[$passwordColumn]))return false; self::login($row); return true; }
 public static function login(array|object $user): void { $u=(array)$user; if(!isset($u['id']))throw new InvalidArgumentException('Authenticated user must have an id field.'); if(session_status()!==PHP_SESSION_ACTIVE)session_start(); session_regenerate_id(true); $_SESSION[self::SESSION_KEY]=$u['id']; unset($u['password'], $u['remember_token']); self::$cachedUser=$u; }
 public static function id(): int|string|null { if(session_status()!==PHP_SESSION_ACTIVE)session_start(); return $_SESSION[self::SESSION_KEY]??null; }
 public static function check(): bool { return self::id()!==null; } public static function guest(): bool { return !self::check(); }
 public static function user(string $table='users', ?string $connection=null): ?array { if(!self::check())return null; if(self::$cachedUser!==null)return self::$cachedUser; $user=DB::table($table,$connection)->where('id',self::id())->first(); if(!$user){self::logout();return null;} return self::$cachedUser=$user; }
 public static function logout(): void { if(session_status()!==PHP_SESSION_ACTIVE)session_start(); unset($_SESSION[self::SESSION_KEY]); session_regenerate_id(true); self::$cachedUser=null; }
 private static function safeIdentifier(string $value): void { if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/',$value))throw new InvalidArgumentException('Invalid authentication table/column name.'); }
}
