<?php

namespace Cycle\ORM {
    interface ORMInterface {
        public function getRepository(string $role);
    }
}

namespace {
    use Examples\SocialHub\PostRequest;
    use Examples\SocialHub\PostResponse;
    use Examples\SocialHub\UserResponse;

    function registerPostServices(\Fastor\App $app) {
        // Register Cycle ORM as a dependency
        $app->registerDependency(\Cycle\ORM\ORMInterface::class, function() {
            return new class implements \Cycle\ORM\ORMInterface {
                public function getRepository(string $role) {
                    return new class {
                        public function findAll() { return []; }
                        public function persist($entity) { return $entity; }
                    };
                }
            };
        });
    }

    if (!class_exists('PostService')) {
    class PostService {
        public function __construct(
            private \Cycle\ORM\ORMInterface $orm,
            private UserService $userService
        ) {}

        public function create(PostRequest $req): PostResponse {
            $res = new PostResponse();
            $res->id = rand(100, 999);
            $res->title = $req->title;
            $res->content = $req->content;
            $res->created_at = date('Y-m-d H:i:s');
            $res->author = $this->userService->getById($req->user_id);

            return $res;
        }

        public function list(): array {
            return [];
        }
    }
    }
}
