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
        $this->host = $host ?? '0.0.0.0';
        $this->port = $port ?? 8000;
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
            $pid = $status['pid'];

            if ($status['running']) {
                // 1. Try to kill the process group (including all Swoole workers)
                if (function_exists('posix_kill')) {
                    @posix_kill(-$pid, 15); // SIGTERM to process group
                }
                
                // 2. Kill the main process
                proc_terminate($this->process, 15);
                
                // 3. Wait up to 3 seconds for it to exit
                $start = microtime(true);
                while (microtime(true) - $start < 3.0) {
                    $status = proc_get_status($this->process);
                    if (!$status['running']) {
                        break;
                    }
                    usleep(200000); // 200ms
                }

                // 4. Force kill group and process if still running
                if ($status['running']) {
                    if (function_exists('posix_kill')) {
                        @posix_kill(-$pid, 9); // SIGKILL to process group
                    }
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
