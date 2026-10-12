<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <title>{{ $title ?? 'LeafPHP' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<main class="welcome-shell">
    <header class="welcome-top"><span class="brand-mark">L</span><span>LeafPHP</span><span class="version">PHP framework</span></header>
    <section class="welcome-hero">
        <p class="eyebrow">SMALL CORE. CLEAR CODE.</p>
        <h1>{{ $title ?? 'LeafPHP' }}<span class="period">.</span></h1>
        <p class="lead">A lightweight PHP framework with practical tools and room for your application to grow.</p>
        <div class="actions"><a class="primary-link" href="{{ route('health') }}">Check application health <span aria-hidden="true">↗</span></a><a class="text-link" href="{{ route('templates.demo') }}">Explore template examples</a></div>
    </section>
    <section class="feature-row" aria-label="Framework highlights">
        <article><span class="feature-index">01</span><h2>Simple by design</h2><p>Readable routes, controllers, and views without unnecessary ceremony.</p></article>
        <article><span class="feature-index">02</span><h2>Ready to build</h2><p>Query builder, migrations, validation, sessions, and authentication helpers.</p></article>
        <article><span class="feature-index">03</span><h2>Developer visibility</h2><p>Inspect requests and queries with the development debug toolbar.</p></article>
    </section>
    <footer class="welcome-footer"><span>Built with PHP {{ PHP_VERSION }}</span><span>Edit <code>app/Views/home.leaf.php</code> to customize this page.</span></footer>
</main>
</body>
</html>
