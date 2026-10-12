# LeafPHP Template Engine

LeafPHP uses `.leaf.php` templates by default so you can display values with `{{ }}` instead of writing PHP echo statements such as `<?= e(...) ?>`. Ordinary `.php` views remain supported for backward compatibility, but use `.leaf.php` for new views.

## Supported syntax

```php
@extends('layouts.app')
@section('title')My Page@endsection
@section('content')
<h1>{{ $title }}</h1>
{!! $trustedHtml !!}
@if ($isReady)
  <p>Ready</p>
@elseif ($isPending)
  <p>Pending</p>
@else
  <p>Not ready</p>
@endif
@foreach ($items as $item)
  <p>{{ $item }}</p>
@endforeach
@include('partials.footer')
@csrf
@endsection
```

- `{{ expression }}` safely escapes HTML output automatically. For example, write `{{ $title }}` instead of `<?= e($title) ?>`.
- `{!! expression !!}` emits raw HTML. Only use with trusted/sanitized content.
- `@extends`, `@section`, `@yield` provide basic layout composition.
- `@include` includes a partial using the current view data.
- `@csrf` emits a hidden CSRF token input.
- `old('field')` retrieves flashed old input.
- `errors()` returns validation errors for the current request.

The template engine is intentionally small and is named LeafPHP templates; it is not a full Blade implementation. Complex nested directive expressions, components, slots, and compiled-template caching are not provided yet. Keep complex application logic in controllers/services and use template directives for presentation. Existing native PHP views continue to work, but new views should use the `.leaf.php` extension and `{{ expression }}` for escaped output.
