<?php

namespace App\Services;

class FirebaseJWT
{
    public static function createJwt($clientEmail, $privateKey)
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $now = time();
        $exp = $now + 3600;

        $claims = [
            "iss" => $clientEmail,
            "scope" => "https://www.googleapis.com/auth/firebase.messaging",
            "aud" => "https://oauth2.googleapis.com/token",
            "iat" => $now,
            "exp" => $exp,
        ];

        $segments = [];
        foreach ([$header, $claims] as $part) {
            $segments[] = rtrim(strtr(base64_encode(json_encode($part)), '+/', '-_'), '=');
        }

        $input = implode('.', $segments);

        openssl_sign($input, $signature, $privateKey, 'SHA256');
        $segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return implode('.', $segments);
    }
}
