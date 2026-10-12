# Validation

```php
$data = validator(request()->all(), [
    'title' => 'required|string|max:255',
    'email' => 'required|email',
    'password' => 'required|string|min:8|confirmed',
])->validateOrRedirect();

// Or choose an explicit form URL:
// $data = validator(request()->all(), $rules)->validateOrRedirect('/register');
```

On failure, `validateOrRedirect()` flashes field errors and safe old input, then redirects to the same-host referrer (or `/` if there is no safe referrer). Passwords, password confirmations, current passwords, and CSRF tokens are excluded from flashed input. In views, use `errors()` and `old('field')` to render messages and repopulate fields. You can also handle the result manually:

```php
$validator = validator(request()->all(), ['email' => 'required|email']);
if ($validator->fails()) {
    return back()->withErrors($validator->errors())->withInput()->send();
}
$data = $validator->validated();
```

Supported rules: `required`, `nullable`, `sometimes`, `string`, `integer`, `numeric`, `email`, `boolean`, `array`, `min`, `max`, `between`, `in`, `not_in`, `confirmed`, `same`, `different`, `alpha`, `alpha_num`, `alpha_dash`, `url`, `date`, `regex`, `unique:table,column[,ignoreId]`, and `exists:table,column`.

`unique` and `exists` require a working database connection. Use CSRF fields in state-changing forms (`@csrf`), and test validation rules against application-specific requirements.
