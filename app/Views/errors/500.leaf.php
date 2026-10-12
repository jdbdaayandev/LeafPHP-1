<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Server error · {{ config('app.name', 'LeafPHP') }}</title><link rel="stylesheet" href="{{ asset('css/app.css') }}"></head>
<body><main class="error-shell"><p class="eyebrow">ERROR {{ $status ?? 500 }}</p><h1>{{ $title ?? 'Something went wrong' }}</h1><p>{{ $message ?? 'An unexpected error occurred. Please try again later.' }}</p><a class="plain-link" href="{{ base_url() }}">Return home</a></main></body></html>
