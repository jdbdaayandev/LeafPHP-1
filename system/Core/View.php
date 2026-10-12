<?php
declare(strict_types=1);

namespace LeafPHP\Core;

/**
 * Small PHP/Blade-inspired template renderer.
 * Supports escaped {{ }}, raw {!! !!}, @if/@elseif/@else/@endif,
 * @foreach/@endforeach, @forelse/@empty/@endforelse, @csrf, @include,
 * @extends, @section/@endsection and @yield.
 */
final class View
{
    private static array $sections = [];
    public static function render(string $name, array $data = []): string {
        $path = self::path($name);
        $source = file_get_contents($path);
        if ($source === false) throw new \RuntimeException("Unable to read view: {$name}");
        self::$sections = [];
        $layout = null;
        if (preg_match('/@extends\([\'"]([^\'"]+)[\'"]\)/', $source, $m)) {
            $layout = $m[1];
            $source = preg_replace('/@extends\([\'"][^\'"]+[\'"]\)\s*/', '', $source, 1);
        }
        $source = preg_replace_callback('/@section\([\'"]([^\'"]+)[\'"]\)(.*?)@endsection/s', function ($m) use ($data) {
            self::$sections[$m[1]] = self::evaluate($m[2], $data);
            return '';
        }, $source);
        if ($layout !== null) return self::renderLayout($layout, $data);
        return self::evaluate($source, $data);
    }
    private static function renderLayout(string $name, array $data): string {
        $source = file_get_contents(self::path($name));
        if ($source === false) throw new \RuntimeException("Unable to read layout: {$name}");
        $source = preg_replace_callback('/@yield\([\'"]([^\'"]+)[\'"](?:\s*,\s*(.*?))?\)/', function ($m) use ($data) {
            return self::$sections[$m[1]] ?? (isset($m[2]) ? trim($m[2], " '\"") : '');
        }, $source);
        return self::evaluate($source, $data);
    }
    private static function evaluate(string $source, array $data): string {
        $source = preg_replace_callback('/@include\([\'"]([^\'"]+)[\'"]\)/', fn($m) => self::render($m[1], $data), $source);
        $source = preg_replace('/\\{!!\\s*(.*?)\\s*!!\\}/s', '<?= $1 ?>', $source);
        $source = preg_replace('/\\{\\{\\s*(.*?)\\s*\\}\\}/s', '<?= e($1) ?>', $source);
        $source = preg_replace('/@csrf\\b/', '<?= csrf_field() ?>', $source);
        $source = preg_replace('/@if\\s*\\(([^\\n]*)\\)/', '<?php if ($1): ?>', $source);
        $source = preg_replace('/@elseif\\s*\\(([^\\n]*)\\)/', '<?php elseif ($1): ?>', $source);
        $source = preg_replace('/@else\\b/', '<?php else: ?>', $source);
        $source = preg_replace('/@endif\\b/', '<?php endif; ?>', $source);
        $source = preg_replace('/@foreach\\s*\\(([^\\n]*)\\)/', '<?php foreach ($1): ?>', $source);
        $source = preg_replace('/@endforeach\\b/', '<?php endforeach; ?>', $source);
        $source = preg_replace('/@for\\s*\\(([^\\n]*)\\)/', '<?php for ($1): ?>', $source);
        $source = preg_replace('/@endfor\\b/', '<?php endfor; ?>', $source);
        $source = preg_replace('/@while\\s*\\(([^\\n]*)\\)/', '<?php while ($1): ?>', $source);
        $source = preg_replace('/@endwhile\\b/', '<?php endwhile; ?>', $source);
        $source = preg_replace('/@php\\b/', '<?php ', $source);
        $source = preg_replace('/@endphp\\b/', ' ?>', $source);
        $file = tempnam(sys_get_temp_dir(), 'leaf_view_');
        if ($file === false) throw new \RuntimeException('Unable to create temporary view file.');
        try {
            file_put_contents($file, $source);
            extract($data, EXTR_SKIP);
            ob_start();
            try { include $file; return (string)ob_get_clean(); }
            catch (\Throwable $e) { ob_end_clean(); throw $e; }
        } finally { @unlink($file); }
    }
    private static function path(string $name): string {
        if (str_contains($name, '..') || str_contains($name, '\\')) throw new \InvalidArgumentException('Invalid view name.');
        $path = dirname(__DIR__, 2) . '/app/Views/' . str_replace('.', '/', $name);
        if (!str_ends_with($path, '.php')) $path .= '.leaf.php';
        if (!is_file($path)) $path = dirname(__DIR__, 2) . '/app/Views/' . str_replace('.', '/', $name) . '.php';
        if (!is_file($path)) throw new \RuntimeException("View not found: {$name}");
        return $path;
    }
}
