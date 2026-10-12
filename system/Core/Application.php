<?php
declare(strict_types=1);
namespace LeafPHP\Core;

use Throwable;

final class Application
{
    public function __construct(private string $basePath) {}
    public function basePath(string $path = ''): string { return $this->basePath . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : ''); }

    public function runHttp(): void
    {
        date_default_timezone_set((string)config('app.timezone', 'UTC'));
        $startedAt = microtime(true);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (config('app.session_driver', 'file') === 'database') session_set_save_handler(new DatabaseSessionHandler(), true);
            session_start();
        }
        $this->serveAssetIfRequested();
        session()->ageFlashData();
        $request = request();
        $method = $request->method();
        if ($method === 'POST' && isset($_POST['_method'])) { $spoof = strtoupper((string)$_POST['_method']); if (in_array($spoof, ['PUT','PATCH','DELETE'], true)) $method = $spoof; }
        if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
            $token = (string)$request->input('_token', $request->header('X-CSRF-TOKEN', ''));
            if (!hash_equals(csrf_token(), $token)) { http_response_code(419); echo $request->isJson() ? json_encode(['message'=>'CSRF token mismatch.'], JSON_UNESCAPED_SLASHES) : $this->errorPage(419, 'Page Expired', 'CSRF token mismatch.'); return; }
        }
        ob_start();
        require $this->basePath('routes/web.php');
        try {
            $result = Router::dispatch($method, $request->path());
            if ($result instanceof Response || $result instanceof RedirectResponse) $result->send();
            elseif (is_array($result) || is_object($result)) response()->json($result)->send();
            elseif ($result !== null) echo (string)$result;
        } catch (Throwable $e) {
            $id = $this->logException($e);
            http_response_code(500);
            if ($request->isJson()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['message'=>config('app.debug', false) ? $e->getMessage() : 'Server Error', 'error_id'=>$id], JSON_UNESCAPED_SLASHES);
            } else echo $this->exceptionPage($e, $id);
        }
        $output = (string) ob_get_clean();
        if (config('app.env', 'local') !== 'production' && config('app.debug', false)) {
            $output = DebugToolbar::inject($output, $startedAt, $request);
        }
        echo $output;
    }

    private function errorPage(int $status, string $title, string $message = ''): string
    {
        try { return view('errors.' . $status, ['status'=>$status,'title'=>$title,'message'=>$message]); }
        catch (Throwable) { return '<!doctype html><title>'.e($title).'</title><h1>'.e($title).'</h1><p>'.e($message).'</p>'; }
    }
    private function exceptionPage(Throwable $e, string $id): string
    {
        if (config('app.env', 'local') !== 'production' && config('app.debug', false)) {
            try { return view('errors.debug', ['exception'=>$e::class,'message'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine(),'trace'=>$e->getTraceAsString(),'errorId'=>$id]); }
            catch (Throwable) { return '<h1>LeafPHP debug error</h1><pre>'.e($e::class . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString()).'</pre>'; }
        }
        return $this->errorPage(500, 'Something went wrong', 'An unexpected error occurred. Please try again later. Reference: '.$id);
    }
    private function logException(Throwable $e): string
    {
        $id = bin2hex(random_bytes(6)); $directory = $this->basePath('storage/logs');
        if (!is_dir($directory)) @mkdir($directory, 0775, true);
        $line = sprintf("[%s] [%s] %s: %s in %s:%d\n%s\n\n", date(DATE_ATOM), $id, $e::class, $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
        if (is_dir($directory) && is_writable($directory)) @file_put_contents($directory.'/'.date('Y-m-d').'.log', $line, FILE_APPEND | LOCK_EX);
        error_log(sprintf('LeafPHP error [%s]: %s', $id, $e->getMessage()));
        return $id;
    }
    private function serveAssetIfRequested(): void
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (function_exists('base_url_path')) { $prefix=base_url_path(); if($prefix!==''&&($uri===$prefix||str_starts_with($uri,$prefix.'/'))) $uri=substr($uri,strlen($prefix))?:'/'; }
        if (!str_starts_with($uri, '/assets/')) return;
        $relative=substr($uri,strlen('/assets/')); $root=realpath($this->basePath('app/Assets')); $file=realpath($this->basePath('app/Assets/'.$relative));
        if (!$root||!$file||!str_starts_with($file,$root.DIRECTORY_SEPARATOR)||!is_file($file)) { http_response_code(404); exit('Not Found'); }
        $types=['css'=>'text/css','js'=>'application/javascript','svg'=>'image/svg+xml','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','ico'=>'image/x-icon','woff2'=>'font/woff2'];
        header('Content-Type: '.($types[strtolower(pathinfo($file,PATHINFO_EXTENSION))]??'application/octet-stream')); header('X-Content-Type-Options: nosniff'); readfile($file); exit;
    }
}
