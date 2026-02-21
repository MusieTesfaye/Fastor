<?php

namespace Fastor;

class Autoloader
{
    private string $baseDir;

    public function __construct(string $baseDir)
    {
        $this->baseDir = rtrim($baseDir, '/') . '/';
    }

    public function register(): void
    {
        spl_autoload_register([$this, 'load']);
    }

    public function load(string $class): void
    {
        if (str_starts_with($class, 'Fastor\\')) return;

        $file = $this->baseDir . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }

        // Try lowercase first segment (App\User -> app/User.php)
        $parts = explode('\\', $class);
        $parts[0] = strtolower($parts[0]);
        $fileLower = $this->baseDir . implode('/', $parts) . '.php';
        if (file_exists($fileLower)) {
            require_once $fileLower;
        }
    }
}
