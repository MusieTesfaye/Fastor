<?php

namespace Fastor\HotReload;

class Watcher
{
    private string $directory;
    private array $lastHashes = [];
    private $process = null;

    private ?string $host = null;
    private ?int $port = null;

    public function __construct(string $directory, ?string $host = '0.0.0.0', ?int $port = 8000)
    {
        $this->directory = $directory;
        $this->host = $host;
        $this->port = $port;
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
                
                // Wait for port to be free
                $this->waitForPortToBeFree();
                
                // Small extra buffer for OS cleanup
                usleep(100000); 
                
                $this->startProcess($starter);
            }
        }
    }

    private function waitForPortToBeFree(): void
    {
        $start = microtime(true);
        while (microtime(true) - $start < 5.0) {
            if ($this->isPortFree()) {
                return;
            }
            usleep(200000); // 200ms
        }
    }

    private function isPortFree(): bool
    {
        $connection = @fsockopen($this->host, $this->port);
        if (is_resource($connection)) {
            fclose($connection);
            return false;
        }
        return true;
    }

    private function startProcess(callable $starter): void
    {
        $this->process = $starter();
    }

    private function stopProcess(): void
    {
        if ($this->process && is_resource($this->process)) {
            $status = proc_get_status($this->process);
            if ($status['running']) {
                // 1. Try SIGTERM first
                proc_terminate($this->process, 15);
                
                // 2. Wait up to 2 seconds for it to exit
                $start = microtime(true);
                while (microtime(true) - $start < 2.0) {
                    $status = proc_get_status($this->process);
                    if (!$status['running']) {
                        break;
                    }
                    usleep(100000); // 100ms
                }

                // 3. Fallback to SIGKILL if still running
                if ($status['running']) {
                    proc_terminate($this->process, 9);
                }
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
