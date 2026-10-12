<?php
declare(strict_types=1);

namespace LeafPHP\Core;

use LeafPHP\Database\DB;

/** Practical rule-based validator for common local application forms. */
final class Validator
{
    private array $errors = [];
    private array $validated = [];
    private function __construct(private array $data, private array $rules, private array $messages = []) { $this->run(); }

    public static function make(array $data, array $rules, array $messages = []): self { return new self($data, $rules, $messages); }
    public function fails(): bool { return $this->errors !== []; }
    public function passes(): bool { return !$this->fails(); }
    public function errors(): array { return $this->errors; }
    public function first(?string $field = null): ?string {
        if ($field !== null) return $this->errors[$field][0] ?? null;
        foreach ($this->errors as $messages) if ($messages) return $messages[0];
        return null;
    }
    public function validated(): array { return $this->validated; }

    /** Return validated data, or flash errors/input and redirect back (or to a given URL). */
    public function validateOrRedirect(?string $redirectTo = null): array
    {
        if ($this->fails()) {
            $response = $redirectTo === null ? back() : redirect($redirectTo);
            $response->withErrors($this->errors())->withInput()->send();
        }
        return $this->validated();
    }
    private function run(): void {
        foreach ($this->rules as $field => $rules) {
            $rules = is_array($rules) ? $rules : explode('|', (string)$rules);
            $value = $this->data[$field] ?? null;
            $present = array_key_exists($field, $this->data);
            $skip = false;
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', (string)$rule, 2), 2, null);
                if ($name === 'nullable' && ($value === null || $value === '')) { $skip = true; break; }
                if ($name === 'sometimes' && !$present) { $skip = true; break; }
            }
            if ($skip) continue;
            foreach ($rules as $rule) {
                [$name, $arg] = array_pad(explode(':', (string)$rule, 2), 2, null);
                if (in_array($name, ['nullable','sometimes'], true)) continue;
                $ok = match ($name) {
                    'required' => $present && $value !== null && $value !== '' && $value !== [],
                    'string' => $value === null || is_string($value),
                    'integer' => $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_INT) !== false,
                    'numeric' => $value === null || $value === '' || is_numeric($value),
                    'email' => $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                    'boolean' => in_array($value, [true,false,0,1,'0','1'], true) || $value === null || $value === '',
                    'array' => is_array($value) || $value === null,
                    'min' => $value === null || $value === '' || $this->measure($value) >= (float)$arg,
                    'max' => $value === null || $value === '' || $this->measure($value) <= (float)$arg,
                    'between' => $value === null || $value === '' || ($this->measure($value) >= (float)explode(',', (string)$arg)[0] && $this->measure($value) <= (float)(explode(',', (string)$arg)[1] ?? INF)),
                    'in' => $value === null || $value === '' || in_array((string)$value, explode(',', (string)$arg), true),
                    'not_in' => $value === null || $value === '' || !in_array((string)$value, explode(',', (string)$arg), true),
                    'confirmed' => ($this->data[$field . '_confirmation'] ?? null) === $value,
                    'same' => ($this->data[(string)$arg] ?? null) === $value,
                    'different' => ($this->data[(string)$arg] ?? null) !== $value,
                    'alpha' => $value === null || $value === '' || (is_string($value) && preg_match('/^[\pL]+$/u', $value)),
                    'alpha_num' => $value === null || $value === '' || (is_string($value) && preg_match('/^[\pL\pN]+$/u', $value)),
                    'alpha_dash' => $value === null || $value === '' || (is_string($value) && preg_match('/^[\pL\pN_-]+$/u', $value)),
                    'url' => $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_URL) !== false,
                    'date' => $value === null || $value === '' || strtotime((string)$value) !== false,
                    'regex' => $value === null || $value === '' || (@preg_match((string)$arg, (string)$value) === 1),
                    'unique' => $this->uniqueRule($value, (string)$arg),
                    'exists' => $this->existsRule($value, (string)$arg),
                    default => throw new \InvalidArgumentException("Unknown validation rule: {$name}"),
                };
                if (!$ok) $this->errors[$field][] = $this->messages[$field . '.' . $name] ?? $this->message($field, $name, $arg);
            }
            if (!isset($this->errors[$field]) && $present) $this->validated[$field] = $value;
        }
    }
    private function measure(mixed $v): float { return is_numeric($v) ? (float)$v : (is_array($v) ? count($v) : mb_strlen((string)$v)); }
    private function uniqueRule(mixed $value, string $arg): bool {
        if ($value === null || $value === '') return true;
        $p = explode(',', $arg); if (count($p) < 2) throw new \InvalidArgumentException('unique rule format: unique:table,column');
        [$table,$column] = $p; $ignore = $p[2] ?? null;
        $this->identifier($table); $this->identifier($column);
        $sql = "SELECT 1 FROM `$table` WHERE `$column` = ?";
        $bindings = [$value];
        if ($ignore !== null && $ignore !== '') { $sql .= ' AND `id` != ?'; $bindings[] = $ignore; }
        $sql .= ' LIMIT 1';
        return DB::select($sql, $bindings) === [];
    }
    private function existsRule(mixed $value, string $arg): bool {
        if ($value === null || $value === '') return true;
        $p = explode(',', $arg); if (count($p) < 2) throw new \InvalidArgumentException('exists rule format: exists:table,column');
        [$table,$column] = $p; $this->identifier($table); $this->identifier($column);
        return DB::select("SELECT 1 FROM `$table` WHERE `$column` = ? LIMIT 1", [$value]) !== [];
    }
    private function identifier(string $name): void { if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) throw new \InvalidArgumentException('Invalid SQL identifier in validation rule'); }
    private function message(string $field, string $rule, ?string $arg): string {
        $label = ucfirst(str_replace('_',' ',$field));
        return match ($rule) {
            'required' => "$label is required.", 'string' => "$label must be text.",
            'integer' => "$label must be an integer.", 'numeric' => "$label must be numeric.",
            'email' => "$label must be a valid email address.", 'boolean' => "$label must be true or false.",
            'min' => "$label must be at least $arg.", 'max' => "$label may not be greater than $arg.",
            'between' => "$label must be within the allowed range.", 'in' => "$label selection is invalid.",
            'not_in' => "$label selection is not allowed.", 'confirmed' => "$label confirmation does not match.",
            'same' => "$label must match $arg.", 'different' => "$label must be different from $arg.",
            'alpha' => "$label may contain letters only.", 'alpha_num' => "$label may contain letters and numbers only.",
            'alpha_dash' => "$label may contain letters, numbers, dashes and underscores only.",
            'url' => "$label must be a valid URL.", 'date' => "$label must be a valid date.",
            'unique' => "$label has already been taken.", 'exists' => "The selected $label is invalid.",
            'regex' => "$label format is invalid.", 'array' => "$label must be an array.",
            default => "$label is invalid.",
        };
    }
}
