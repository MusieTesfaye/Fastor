<?php

namespace Tests\Integration;

use Fastor\App;
use Fastor\Database\Attributes\Entity;
use Fastor\Database\Attributes\Column;

#[Entity]
class TestUser
{
    #[Column(type: 'primary')]
    public int $id;

    #[Column(type: 'string')]
    public string $username;
}

class DatabaseIntegrationTest
{
    public function testDbSyncAndQuery()
    {
        // Override DB for testing
        putenv("DATABASE_URL=sqlite://:memory:");
        putenv("FASTOR_ENV=development");

        \Fastor\App::resetInstance();
        $app = App::getInstance();
        $app->sync([TestUser::class]);

        $user = new TestUser();
        $user->username = "tester";
        $app->save($user);

        $found = $app->find(TestUser::class, 1);
        if (!$found || $found->username !== 'tester') {
            throw new \Exception("Database find failed");
        }

        $all = $app->all(TestUser::class);
        if (count($all) !== 1) {
            throw new \Exception("Database all() failed");
        }

        $selected = $app->select(TestUser::class)->where('username', 'tester')->fetchOne();
        if (!$selected || $selected->id !== 1) {
            throw new \Exception("Database select/where failed");
        }
    }

    public function run()
    {
        echo "Running DatabaseIntegrationTest... ";
        $this->testDbSyncAndQuery();
        echo "PASSED\n";
    }
}
