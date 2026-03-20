# Fastor

High-performance, async-by-default PHP framework inspired by FastAPI.

## Features

- **Blazing Fast**: Built on OpenSwoole for maximum performance (>15k RPS with full validation).
- **Async by Default**: Fully supports coroutines and non-blocking I/O.
- **Elite Caching**: Integrated Symfony Cache with boot-time warmup and native support for Redis, APCu, and Filesystem.
- **Pydantic-like Validation**: Extensible, type-safe request/response DTO validation using the `Constraint` system.
- **Automatic OpenAPI**: Generates Swagger UI and OpenAPI JSON automatically.
- **Pluggable Database**: Use any ORM (Eloquent, Cycle) or raw PDO via Dependency Injection.

## Installation

```bash
composer require fastor/fastor
```

After installation, the `fastor` CLI tool can be installed to your project root by running:

```bash
php vendor/bin/fastor init
```

This creates a `./fastor` executable in your root for easier access. It's also always available at `vendor/bin/fastor`.

## Quick Start

Create a `main.php`:

```php
<?php

$app = app();

$app->get("/hello/{name}", function(string $name) {
    return ["message" => "Hello, $name!"];
});

// Auto-validated DTO (No #[Body] needed!)
$app->post("/register", function(UserRequest $req): UserResponse {
    return $req; // Automatically mapped to UserResponse
});

$app->run();
```

Run your app:

```bash
./fastor run main.php
```

## CLI Usage

Fastor provides a powerful CLI for running your applications.

```bash
./fastor run [file.php] [options]
```

### Options:
- `--host <host>`: Specify the host (default: `0.0.0.0`).
- `--port <port>`: Specify the port (default: `8000`).
- `--env <env>`: Specify the environment (default: `production`).

## Server Control

Fastor handles both HTTP and WebSockets on the same port by default. You can disable either protocol if needed:

```php
$app = app();
$app->disableWs();   // Pure HTTP server
$app->disableHttp(); // Pure WebSocket server
```

## Authentication

Fastor uses a "Dependency Injection" pattern for authentication, similar to FastAPI.

```php
use Fastor\Attributes\Auth;
use Fastor\Auth\Bearer;

// 1. Register an auth dependency
$app->registerDependency('auth', new Bearer());

// 2. Use it in your routes
$app->get("/protected", function(#[Auth] string $token) {
    return ["token" => $token];
});
```

You can use `Fastor\Auth\Bearer` or `Fastor\Auth\ApiKey`, or create your own callable class.

## Database Integration

Fastor is database-agnostic. You can plug in any database layer using Dependency Injection.

### Using Raw PDO

```php
use Fastor\Depends\Depends;

$app->registerDependency('db', function() {
    return new PDO('sqlite:database.sqlite');
});

$app->get("/users", function(#[Depends('db')] PDO $db) {
    return $db->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC);
});
```

### Using Cycle ORM

Fastor works beautifully with Cycle ORM. Just register the ORM as a dependency:

```php
use Cycle\ORM\ORMInterface;

// 1. Setup and register Cycle 
$app->registerDependency(ORMInterface::class, function() {
    return $myConfiguredCycleInstance;
});

// 2. Use it in your routes
$app->get("/users", function(ORMInterface $orm) {
    return $orm->getRepository(User::class)->findAll();
});
```

### Using Eloquent

## Validation & DTOs

Fastor treats validation as a first-class citizen. Most of the time, you don't even need attributes:

```php
use Fastor\Attributes\Body;
use Fastor\Validation\Attributes\{Email, Range};

class UserRequest {
    #[Email]
    public string $email;

    #[Range(18, 99)]
    public int $age;
}

class UserResponse {
    public string $email;
    public bool $status = true;
}

// Automatic Request & Response Validation!
$app->post("/register", function(UserRequest $req): UserResponse {
    return $req; 
});
```

## Automatic Dependency Injection (Auto-wiring)

Fastor can automatically resolve your dependencies. No registration needed for concrete classes!

```php
class UserRepository {
    public function find(int $id) { /* ... */ }
}

class UserService {
    public function __construct(
        private UserRepository $repo
    ) {}

    public function getUser(int $id) {
        return $this->repo->find($id);
    }
}

// Fastor automatically instantiates UserService and UserRepository!
$app->get("/user/{id}", function(int $id, UserService $service) {
    return $service->getUser($id);
});
```

### Manual Registration
If you need to inject an interface or a pre-configured instance (like a DB connection), use `registerDependency`.

## Real-World Example: Social Hub

For a comprehensive showcase of Fastor's capabilities—including simultaneous HTTP/WebSocket handling, hybrid database usage (PDO & Cycle ORM), and complex DTO validation—see the [Social Hub Example](file:///home/blacker/Desktop/Fastor/examples/social_hub.php).

```php
// examples/social_hub.php snippet
$app->post("/posts", function(PostRequest $req, PostService $service, Broadcast $broadcast) {
    $post = $service->create($req);
    
    // Notify all WebSocket clients about the new post
    $broadcast->emit('new_post', [
        'title' => $post->title,
        'author' => $post->author->username
    ]);

    return $post;
});
```


Fastor is designed for elite performance in production environments.

### Framework Warmup
When you call `$app->run()`, Fastor enters a `boot()` phase. During this phase:
1. The **Valinor Mapper** is pre-initialized and cached.
2. All route handlers are scanned, and their **Reflection Metadata** is pre-calculated.
3. Every DTO property is analyzed, and a **Pre-compiled Validation Plan** is generated.

This ensures that during the request cycle, validation is just a series of high-speed method calls with zero reflective overhead.

### Integrated Symfony Cache
Fastor includes a unified caching layer out of the box:

```php
// Setup Redis cache
cache()->redis('redis://localhost');

// Or use the default high-performance file cache
cache()->file('storage/cache');
```

## Documentation

Visit [localhost:8000/docs](http://localhost:8000/docs) after starting your app to see the automatic Swagger documentation.

## License 

MIT
