<?php

namespace Fastor\Auth;

use Fastor\Http\Request;
use Fastor\Exceptions\HttpException;

class Bearer
{
    /**
     * Extracts the Bearer token from the Authorization header.
     * Returns the token string or throws 401.
     */
    public function __invoke(Request $request): string
    {
        $auth = $request->header('Authorization');
        
        if (!$auth || !str_starts_with($auth, 'Bearer ')) {
            throw new HttpException(401, "Missing or invalid Authorization Bearer header");
        }

        return substr($auth, 7);
    }
}
