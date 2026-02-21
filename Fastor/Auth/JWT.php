<?php

namespace Fastor\Auth;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Fastor\Http\Request;

class JWT
{
    private string $secret;
    private string $algo;

    public function __construct(?string $secret = null, string $algo = 'HS256')
    {
        $this->secret = $secret ?? env('FASTOR_SECRET', 'change_me_pronto');
        $this->algo = $algo;
    }

    /**
     * Generate a token for a payload.
     */
    public function encode(array $payload, int $expiresIn = 3600): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiresIn;
        
        return FirebaseJWT::encode($payload, $this->secret, $this->algo);
    }

    /**
     * Decode and validate a token.
     */
    public function decode(string $token): array
    {
        try {
            $decoded = FirebaseJWT::decode($token, new Key($this->secret, $this->algo));
            return (array)$decoded;
        } catch (\Throwable $e) {
            throw new \Fastor\Exceptions\HttpException(401, "Invalid or expired token");
        }
    }

    /**
     * Extract token from request and validate.
     */
    public function handle(Request $request): array
    {
        $auth = $request->header('Authorization');
        if (!$auth || !str_starts_with($auth, 'Bearer ')) {
            throw new \Fastor\Exceptions\HttpException(401, "Missing Authorization Bearer Header");
        }

        $token = substr($auth, 7);
        return $this->decode($token);
    }
}
