# Fastor

High-performance, async-by-default PHP framework inspired by FastAPI.

## Features

- **Blazing Fast**: Built on OpenSwoole for maximum performance.
- **Async by Default**: Fully supports coroutines and non-blocking I/O.
- **Boutique DX**: Single-gateway architecture with zero-config setup.
- **Automatic OpenAPI**: Generates Swagger UI and OpenAPI JSON automatically.
- **Fluent Database**: Built-in CycleORM integration with boutique query builder.
- **Validation**: Strict, declarative DTO validation powered by Valinor.
- **Hot Reload**: Integrated watcher for seamless development.

## Installation

```bash
composer require fastor/fastor
```

After installation, initialize the CLI tool:

```bash
php vendor/bin/fastor init
```

## Quick Start

Create a `main.php`:

```php
<?php

$app = app();

$app->get("/hello/{name}", function(string $name) {
    return ["message" => "Hello, $name!"];
});

$app->run();
```

## WebSocket Support

Fastor makes WebSocket development as simple as HTTP.

```php
<?php

$app = app();

$app->websocket('/ws', function($frame) {
    if ($frame->data === 'ping') {
        return 'pong';
    }
});

$app->run();
```

Run your app normally with `./fastor start`.

Run your app:

```bash
./fastor start
```

## Documentation

Visit [localhost:8000/docs](http://localhost:8000/docs) after starting your app to see the automatic Swagger documentation.

## License 

MIT
