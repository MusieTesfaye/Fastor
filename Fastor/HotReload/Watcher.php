<?php

namespace Fastor\HotReload;

class Watcher
{
    private string $directory;
    private array $lastHashes = [];
    private $process = null;

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    public function watch(callable $starter): void
    {
        echo "Watching for changes in {$this->directory}...\n";
        
        $this->startProcess($starter);

        while (true) {
            sleep(1);
            if ($this->checkChanges()) {
                echo "\nChange detected! Restarting Fastor...\n";
                $this->stopProcess();
                $this->startProcess($starter);
            }
        }
    }

    private function startProcess(callable $starter): void
    {
        // We use pcntl_fork or simple background process?
        // For simplicity and to avoid pcntl dependency issues in all envs,
        // we can use proc_open to manage the child server.
        $this->process = $starter();
    }

    private function stopProcess(): void
    {
        if ($this->process && is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if ($status['running']) {
                // Terminate child and all its sub-processes (Swoole workers)
                // Sending SIGTERM to the pgid is often cleaner
                proc_terminate($this->process);
            }
            proc_close($this->process);
            $this->process = null;
        }
    }

    private function checkChanges(): bool
    {
        $changed = false;
        $files = $this->getFiles($this->directory);

        foreach ($files as $file) {
            $mtime = filemtime($file);
            if (!isset($this->lastHashes[$file]) || $this->lastHashes[$file] !== $mtime) {
                $this->lastHashes[$file] = $mtime;
                $changed = true;
            }
        }

        return $changed;
    }

    private function getFiles(string $dir): array
    {
        $files = [];
        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item === 'vendor' || $item === '.git') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $files = array_merge($files, $this->getFiles($path));
            } elseif (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
                $files[] = $path;
            }
        }

        return $files;
    }
}
