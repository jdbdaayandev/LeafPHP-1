<?php
declare(strict_types=1);
namespace LeafPHP\Core;

/** Redirect response with flash-data, validation errors and safe old-input support. */
final class RedirectResponse
{
    public function __construct(private string $target, private int $status = 302) {}
    public function with(string $key, mixed $value): self { session()->flash($key, $value); return $this; }
    public function withErrors(array $errors): self { session()->flash('_errors', $errors); return $this; }
    public function withInput(?array $input = null): self { $input ??= request()->except(['password','password_confirmation','current_password','_token']); session()->flash('_old_input', $input); return $this; }
    public function send(): never { http_response_code($this->status); header('Location: ' . $this->target); exit; }
}
