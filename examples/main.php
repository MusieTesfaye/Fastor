<?php

$app = app();

class User {
    public function __construct(
        public string $name,
        public int $age
    ) {}
}

$app->get('/', function() {
    return ["message" => "Fastor Optimized!"];
});

$app->get('/user/{name}', function(string $name, int $age = 25) {
    return new User($name, $age);
});

$app->post('/echo', function(User $user) {
    return $user;
});
