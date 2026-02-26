<?php

namespace Tests\Integration;

use Examples\SocialHub\RegisterRequest;
use Fastor\App;
use UserService;
use PostService;

class SocialHubTest {
    private App $app;

    public function run(): void {
        echo "Testing User Registration (Success)... ";
        $this->setUp();
        $this->testUserRegistrationValidationSuccess();
        echo "PASSED\n";

        echo "Testing User Registration (Failure)... ";
        $this->setUp();
        $this->testUserRegistrationValidationFailure();
        echo "PASSED\n";

        echo "Testing Post Creation Flow... ";
        $this->setUp();
        $this->testPostCreationFlow();
        echo "PASSED\n";
    }

    protected function setUp(): void {
        App::resetInstance();
        $this->app = App::getInstance();
        
        // Include the example services to setup dependencies
        require_once __DIR__ . '/../../examples/SocialHub/DTOs.php';
        require_once __DIR__ . '/../../examples/SocialHub/user_service.php';
        require_once __DIR__ . '/../../examples/SocialHub/post_service.php';
        
        \registerSocialHubServices($this->app);
        \registerPostServices($this->app);
    }

    public function testUserRegistrationValidationSuccess() {
        $app = $this->app;
        $app->post("/authors/register", function(RegisterRequest $req, UserService $service) {
            return $service->register($req);
        });

        $request = new \Fastor\Http\Request(new class {
            public int $fd = 1;
            public array $server = ['request_uri' => '/authors/register', 'request_method' => 'POST'];
            public array $post = [
                'email' => 'test@example.com',
                'username' => 'testuser',
                'age' => 25,
                'password' => 'securepassword123'
            ];
            public array $get = [];
            public array $header = [];
            public array $cookie = [];
            public array $files = [];
            public function rawContent() { return ''; }
        });

        $response = $app->handleVirtualRequest($request);
        $data = json_decode($response->getContent(), true);

        if ($response->getStatusCode() !== 200) throw new \Exception("Status code not 200: " . $response->getStatusCode());
        if ($data['username'] !== 'testuser') throw new \Exception("Username mismatch");
        if ($data['email'] !== 'test@example.com') throw new \Exception("Email mismatch");
    }

    public function testUserRegistrationValidationFailure() {
        $app = $this->app;
        $app->post("/authors/register", function(RegisterRequest $req, UserService $service) {
            return $service->register($req);
        });

        $request = new \Fastor\Http\Request(new class {
            public int $fd = 1;
            public array $server = ['request_uri' => '/authors/register', 'request_method' => 'POST'];
            public array $post = [
                'email' => 'invalid-email',
                'username' => 'testuser',
                'age' => 10,
                'password' => 'short'
            ];
            public array $get = [];
            public array $header = [];
            public array $cookie = [];
            public array $files = [];
            public function rawContent() { return ''; }
        });

        $response = $app->handleVirtualRequest($request);
        $data = json_decode($response->getContent(), true);

        if ($response->getStatusCode() !== 422) throw new \Exception("Status code not 422: " . $response->getStatusCode());
        if (count($data['details']) !== 3) {
            echo "\nErrors found: " . json_encode($data['details']) . "\n";
            throw new \Exception("Expected 3 validation errors, got " . count($data['details']));
        }
    }

    public function testPostCreationFlow() {
        $app = $this->app;
        
        // Setup a user first
        $pdo = $app->resolve(\PDO::class);
        $pdo->exec("INSERT INTO users (username, email, password) VALUES ('author1', 'a1@test.com', 'pwd')");
        $userId = (int)$pdo->lastInsertId();

        $app->post("/posts", function(\Examples\SocialHub\PostRequest $req, PostService $service) {
            return $service->create($req);
        });

        $request = new \Fastor\Http\Request(new class($userId) {
            public int $fd = 1;
            public array $server = ['request_uri' => '/posts', 'request_method' => 'POST'];
            public array $post;
            public array $get = [];
            public array $header = [];
            public array $cookie = [];
            public array $files = [];
            public function __construct(private $id) {
                $this->post = [
                    'title' => 'My First Post',
                    'content' => 'This is a long enough content for the post.',
                    'user_id' => $this->id
                ];
            }
            public function rawContent() { return ''; }
        });

        $response = $app->handleVirtualRequest($request);
        $data = json_decode($response->getContent(), true);

        if ($response->getStatusCode() !== 200) {
            echo "\nStatus: " . $response->getStatusCode() . "\n";
            echo "Body: " . $response->getContent() . "\n";
            throw new \Exception("Status code not 200");
        }
        if ($data['title'] !== 'My First Post') throw new \Exception("Title mismatch");
        if ($data['author']['username'] !== 'author1') throw new \Exception("Author mismatch");
    }
}
