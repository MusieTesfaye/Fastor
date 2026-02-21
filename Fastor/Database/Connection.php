<?php

namespace Fastor\Database;

use Cycle\Database\Config;
use Cycle\Database\DatabaseManager;
use Cycle\Database\Driver\SQLite\SQLiteDriver;
use Cycle\ORM\ORM;
use Cycle\ORM\Schema;
use Cycle\ORM\Factory;

use Cycle\Database\Config\SQLiteDriverConfig;
use Cycle\Database\Config\SQLite\FileConnectionConfig;
use Fastor\Database\Attributes\Entity;
use Fastor\Database\Attributes\Column;

class Connection
{
    private static ?ORM $orm = null;
    private static ?DatabaseManager $dbal = null;
    private static ?string $dsn = null;
    private static array $syncedEntities = [];

    public static function reset(): void
    {
        self::$orm = null;
        self::$dbal = null;
        self::$dsn = null;
        self::$syncedEntities = [];
    }

    public static function connect(string $dsn): void
    {
        if (str_starts_with($dsn, 'sqlite:')) {
            $dbFile = str_replace('sqlite:', '', $dsn);
            $dbFile = ltrim($dbFile, '/');
            if ($dbFile === ':memory:' || $dbFile === 'memory:') {
                 $dbFile = ':memory:';
            }
            $driverConfig = new SQLiteDriverConfig(
                connection: new FileConnectionConfig(database: $dbFile)
            );
        } else {
            throw new \Exception("Unsupported database driver in DSN: $dsn");
        }

        $dbConfig = new Config\DatabaseConfig([
            'default' => 'default',
            'databases' => [
                'default' => ['connection' => 'main']
            ],
            'connections' => [
                'main' => $driverConfig
            ]
        ]);

        self::$dsn = $dsn;
        $dbal = new DatabaseManager($dbConfig);
        self::$dbal = $dbal;
        self::$orm = new ORM(new Factory($dbal), new Schema([]));
    }

    /**
     * Re-connect to the database (called in each worker after fork).
     */
    public static function reconnect(): void
    {
        if (self::$dsn === null) return;
        self::connect(self::$dsn);
        if (!empty(self::$syncedEntities)) {
            self::sync(self::$syncedEntities);
        }
    }

    public static function orm(): ORM
    {
        if (self::$orm === null) {
            $dsn = env('DATABASE_URL');
            if ($dsn) {
                self::connect($dsn);
            } else {
                throw new \Exception("Database not connected and DATABASE_URL not set.");
            }
        }
        return self::$orm;
    }

    /**
     * Synchronize database schema based on Entity attributes.
     * 
     * @param string[] $classes
     */
    public static function sync(array $classes): void
    {
        if (self::$orm === null) {
            self::orm(); // Trigger auto-connect
        }
        if (self::$dbal === null) {
            throw new \Exception("Database not connected.");
        }
        $db = self::$dbal->database();
        $ormSchema = [];

        foreach ($classes as $class) {
            $reflection = new \ReflectionClass($class);
            $entityAttr = $reflection->getAttributes(Entity::class);
            
            if (empty($entityAttr)) continue;

            $entity = $entityAttr[0]->newInstance();
            $tableName = $entity->table ?? strtolower($reflection->getShortName()) . 's';
            $table = $db->table($tableName)->getSchema();
            $mapping = [
                \Cycle\ORM\Schema::ROLE    => $reflection->getName(),
                \Cycle\ORM\Schema::ENTITY  => $reflection->getName(),
                \Cycle\ORM\Schema::MAPPER  => \Cycle\ORM\Mapper\Mapper::class,
                \Cycle\ORM\Schema::DATABASE => 'default',
                \Cycle\ORM\Schema::TABLE   => $tableName,
                \Cycle\ORM\Schema::PRIMARY_KEY => 'id',
                \Cycle\ORM\Schema::COLUMNS => [],
                \Cycle\ORM\Schema::RELATIONS => [],
            ];

            foreach ($reflection->getProperties() as $property) {
                $columnAttr = $property->getAttributes(Column::class);
                if (empty($columnAttr)) continue;

                $column = $columnAttr[0]->newInstance();
                $columnName = $property->getName();
                $propertyType = $property->getType();
                $isNullable = $column->nullable;

                if ($isNullable === null && $propertyType) {
                    $isNullable = $propertyType->allowsNull();
                }
                $isNullable = $isNullable ?? false;

                $mapping[\Cycle\ORM\Schema::COLUMNS][$columnName] = $columnName;

                if ($column->primary || $column->type === 'primary') {
                    $table->primary($columnName);
                    $mapping[\Cycle\ORM\Schema::PRIMARY_KEY] = $columnName;
                }

                $columnType = $column->type;
                
                switch ($columnType) {
                    case 'primary':
                        break; // Already handled
                    case 'string':
                        $table->string($columnName)->nullable($isNullable);
                        break;
                    case 'text':
                        $table->text($columnName)->nullable($isNullable);
                        break;
                    case 'integer':
                        $table->integer($columnName)->nullable($isNullable);
                        break;
                    case 'float':
                        $table->float($columnName)->nullable($isNullable);
                        break;
                    case 'boolean':
                        $table->boolean($columnName)->nullable($isNullable);
                        break;
                    case 'json':
                        $table->json($columnName)->nullable($isNullable);
                        break;
                    case 'datetime':
                        $table->datetime($columnName)->nullable($isNullable);
                        break;
                    default:
                        $table->string($columnName)->nullable($isNullable);
                }
                
                if ($column->default !== null) {
                    $table->column($columnName)->defaultValue($column->default);
                }
            }
            $table->save();
            $ormSchema[$reflection->getName()] = $mapping;
        }

        // Update ORM with new schema
        self::$orm = self::$orm->withSchema(new \Cycle\ORM\Schema($ormSchema));
        // Track synced entities
        self::$syncedEntities = array_unique(array_merge(self::$syncedEntities, $classes));
    }
}
