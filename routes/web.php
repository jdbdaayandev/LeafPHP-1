<?php
use LeafPHP\Core\Router;

Router::get('/', [App\Controllers\HomeController::class, 'index'], 'home');
Router::get('/health', static fn () => response()->json(['status' => 'ok', 'framework' => 'LeafPHP']), 'health');
Router::get('/api/example', static fn () => response()->json(['data' => ['framework' => 'LeafPHP'], 'message' => 'OK']), 'api.example');
Router::get('/template-demo', static fn () => view('examples', [
    'heading' => 'LeafPHP templates',
    'items' => ['Escaped output', 'Layouts and sections', 'CSRF fields'],
]), 'templates.demo');
Router::get('/books/{id}', static fn ($id) => response()->json(['book_id' => $id]), 'books.show');

// Example protected route. Add a login route/controller before enabling this in your app.
// Router::get('/dashboard', [App\Controllers\DashboardController::class, 'index'], 'dashboard', ['auth']);
