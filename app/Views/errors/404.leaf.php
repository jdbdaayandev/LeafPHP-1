<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>404 · {{ config('app.name', 'LeafPHP') }}</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body><main class="error-shell"><p class="eyebrow">ERROR {{ $status ?? 404 }}</p><h1>{{ $title ?? 'Page not found' }}</h1><p>{{ $message ?? 'The page you requested could not be found.' }}</p><a class="plain-link" href="{{ base_url() }}">Return home</a></main></body></html>
