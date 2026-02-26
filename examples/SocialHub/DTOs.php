<?php

namespace Examples\SocialHub;

use Fastor\Validation\Attributes\Email;
use Fastor\Validation\Attributes\Range;
use Fastor\Validation\Attributes\Min;

class RegisterRequest {
    #[Email]
    public string $email;

    public string $username;

    #[Range(13, 120)]
    public int $age;

    #[Min(8)]
    public string $password;
}

class UserResponse {
    public int $id;
    public string $username;
    public string $email;
    public string $joined_at;
}

class PostRequest {
    public string $title;

    #[Min(10)]
    public string $content;

    public int $user_id;
}

class PostResponse {
    public int $id;
    public string $title;
    public string $content;
    public string $created_at;
    public UserResponse $author;
}
