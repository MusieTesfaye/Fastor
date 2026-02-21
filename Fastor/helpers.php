<?php

use Fastor\App;

if (!function_exists('app')) {
    /**
     * Get the App instance.
     *
     * @return App
     */
    function app(): \Fastor\App
    {
        return \Fastor\App::getInstance();
    }


    /**
 * Handle a database transaction.
 */
function transaction(?callable $callback = null): mixed
{
    $transaction = new \Fastor\Database\Transaction(app()->orm());
    
    if ($callback) {
        try {
            $result = $callback($transaction);
            $transaction->run();
            return $result;
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    return $transaction;
}
}

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (($valueLength = strlen($value)) > 1 && $value[0] === '"' && $value[$valueLength - 1] === '"') {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

if (!function_exists('abort')) {
    /**
     * Throw an exception or return a response (to be implemented).
     */
    function abort(int $code, string $message = '')
    {
        // For now, just throw a generic exception
        throw new \Exception($message, $code);
    }
}
if (!function_exists('request')) {
    /**
     * Get the current Request instance.
     */
    function request(): \Fastor\Http\Request
    {
        return \OpenSwoole\Coroutine::getContext()['request'];
    }
}

if (!function_exists('response')) {
    /**
     * Get the current Response instance.
     */
    function response(): \Fastor\Http\Response
    {
        return \OpenSwoole\Coroutine::getContext()['response'];
    }
}

if (!function_exists('cpu_count')) {
    /**
     * Get the number of CPU cores.
     */
    function cpu_count(): int
    {
        return \OpenSwoole\Util::getCPUNum();
    }
}
