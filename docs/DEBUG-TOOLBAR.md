# Development Debug Toolbar

When `APP_ENV` is not `production` and `APP_DEBUG=true`, LeafPHP injects a floating toolbar into HTML responses. It includes overview/performance, SQL query and binding inspection, registered routes, request metadata, environment details, and a session inspector. The session panel redacts keys containing password, secret, token, CSRF, cookie, authorization, or API-key terms. Database-session payload bindings are redacted from the query panel.

The toolbar is omitted from JSON and plain-text responses and is disabled in production. Set `APP_DEBUG=false` to disable it in other environments. Never enable debug mode on a publicly accessible production deployment.
