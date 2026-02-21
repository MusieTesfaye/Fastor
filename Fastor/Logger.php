<?php

namespace Fastor;

use OpenSwoole\Coroutine;

class Logger
{
    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    private static function log(string $level, string $message, array $context): void
    {
        $requestId = Coroutine::getContext()['request_id'] ?? 'none';
        
        $log = [
            'timestamp' => date('c'),
            'level' => $level,
            'request_id' => $requestId,
            'message' => $message,
        ];

        if (!empty($context)) {
            $log['context'] = $context;
        }

        echo json_encode($log) . "\n";
    }
}
