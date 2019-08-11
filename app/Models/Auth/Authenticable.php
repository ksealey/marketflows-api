<?php

namespace App\Models\Auth;

use Illuminate\Database\Eloquent\Model;
use \Firebase\JWT\JWT;
use Illuminate\Foundation\Auth\User as Authenticatable;

abstract class Authenticable extends Authenticatable
{
    /**
     * Return a bearer token with the user as the subject
     *
     * @return \Firebase\JWT\JWT
     */
    public function getBearerToken()
    {
        return $this->token(
            $this->id, 
            strtotime('now +5 minutes'),
            'bearer'
        );
    }

    /**
     * Get a refresh token that expires in 90 days
     * 
     * @return \Firebase\JWT\JWT
     */
    public function getRefreshToken()
    {
        return $this->token(
            $this->id, 
            strtotime('now +30 days'),
            'refresh'
        );
    }

    /**
     * Return a JWT
     * 
     * @param mixed  $sub            The token subject
     * @param int    $exipiresAtTS   The token expiration as unix timestamp
     * @param string $type           The token type
     * @return \Firebase\JWT\JWT
     */
    private function token($sub, int $exipiresAtTS, string $type)
    {
        return JWT::encode([
            'typ' => $type,
            'sub' => $sub,
            'iss' => env('APP_URL'),
            'aud' => env('APP_URL'),
            'iat' => date('U'),
            'nbf' => date('U'),
            'exp' => $exipiresAtTS
        ], env('APP_KEY'));
    }
}


