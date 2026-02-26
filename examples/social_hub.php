<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/SocialHub/DTOs.php';
require_once __DIR__ . '/SocialHub/user_service.php';
require_once __DIR__ . '/SocialHub/post_service.php';

use Examples\SocialHub\RegisterRequest;
use Examples\SocialHub\PostRequest;
use Fastor\WebSocket\Broadcast;

$app = app();
$app->enableDebug();

// Initialize Social Hub Services
registerSocialHubServices($app);
registerPostServices($app);

// 1. User Routes (PDO based)
$app->post("/authors/register", function(RegisterRequest $req, UserService $service) {
    return $service->register($req);
});

$app->get("/authors/{id}", function(int $id, UserService $service) {
    return $service->getById($id);
});

// 2. Post Routes (Cycle ORM based)
$app->post("/posts", function(PostRequest $req, PostService $service, Broadcast $broadcast) {
    $post = $service->create($req);
    
    // Notify all WebSocket clients about the new post
    $broadcast->emit('new_post', [
        'title' => $post->title,
        'author' => $post->author->username
    ]);

    return $post;
});

// 3. WebSocket Real-time Feed
$app->websocket("/feed", function($frame, Broadcast $broadcast) {
    if ($frame->data === 'ping') {
        return 'pong';
    }
});

echo "Starting Social Hub Example...\n";
echo "Try:\n";
echo "  POST /authors/register  - Register a new author\n";
echo "  POST /posts             - Create a post & notify WS clients\n";
echo "  WS   /feed              - Listen for new post notifications\n";

$app->run();
