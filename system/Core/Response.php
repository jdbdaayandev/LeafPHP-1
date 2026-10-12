<?php
declare(strict_types=1);
namespace LeafPHP\Core;

/** Explicit HTTP response builder with JSON helpers. */
final class Response
{
    public function __construct(private mixed $data = '', private int $statusCode = 200, private array $headers = []) {}
    public function json(array|object $data, int $status = 200): self {
        $this->data = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->statusCode = $status;
        $this->headers['Content-Type'] = 'application/json; charset=utf-8';
        return $this;
    }
    public function text(string $data, int $status = 200): self { $this->data = $data; $this->statusCode = $status; $this->headers['Content-Type'] = 'text/plain; charset=utf-8'; return $this; }
    public function html(string $data, int $status = 200): self { $this->data = $data; $this->statusCode = $status; $this->headers['Content-Type'] = 'text/html; charset=utf-8'; return $this; }
    public function status(int $status): self { $this->statusCode = $status; return $this; }
    public function header(string $name, string $value): self { $this->headers[$name] = $value; return $this; }
    public function send(): void {
        http_response_code($this->statusCode);
        foreach ($this->headers as $k=>$v) header($k . ': ' . $v);
        echo is_string($this->data) ? $this->data : json_encode($this->data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
}
