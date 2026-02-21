<?php

namespace Fastor\Logging;

class Logger
{
    public static function log(string $level, string $message, array $context = []): void
    {
        $log = [
            'timestamp' => date('Y-m-d\TH:i:sP'),
            'level' => strtoupper($level),
            'request_id' => 'none', // Placeholder
            'message' => $message
        ];

        if (!empty($context)) {
            $log['context'] = $context;
        }

        echo json_encode($log) . "\n";
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }
}
