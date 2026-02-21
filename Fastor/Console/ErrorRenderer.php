<?php

namespace Fastor\Console;

class ErrorRenderer
{
    public static function render(\Throwable $e, string $env): void
    {
        $type = strtoupper((new \ReflectionClass($e))->getShortName());
        echo "\n\033[41;37m {$type} \033[0m \033[1m" . $e->getMessage() . "\033[0m\n";
        echo "\033[33mIn {$e->getFile()}:{$e->getLine()}\033[0m\n\n";

        if (file_exists($e->getFile())) {
            $lines = explode("\n", file_get_contents($e->getFile()));
            $start = max(0, $e->getLine() - 3);
            $end = min(count($lines), $e->getLine() + 2);
            
            for ($i = $start; $i < $end; $i++) {
                $num = $i + 1;
                $line = $lines[$i] ?? '';
                if ($num === $e->getLine()) {
                    echo "\033[41;37m " . str_pad($num, 3, ' ', STR_PAD_LEFT) . " \033[0m \033[31m>\033[0m {$line}\n";
                } else {
                    echo "\033[2m " . str_pad($num, 3, ' ', STR_PAD_LEFT) . " | \033[0m {$line}\n";
                }
            }
        }
        echo "\n";

        if ($env === 'development' || $env === 'dev') {
            echo "\033[2m" . $e->getTraceAsString() . "\033[0m\n\n";
        }
    }
}
