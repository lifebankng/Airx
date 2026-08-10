<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class AuthService
{
    private string $secretKey;

    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = $secretKey ?? $_ENV['JWT_SECRET'] ?? $_ENV['secretkey'] ?? 'your_super_secret_key_here';
    }

    /**
     * Generate a JWT token.
     */
    public function generateToken(array $data, int $expiryDays = 7): string
    {
        $issuedAt = time();
        $expire = $issuedAt + (60 * 60 * 24 * $expiryDays);

        $payload = array_merge([
            'iat' => $issuedAt,
            'exp' => $expire
        ], $data);

        return JWT::encode($payload, $this->secretKey, 'HS256');
    }

    /**
     * Decode and validate a JWT token.
     */
    public function decodeToken(string $token): array
    {
        $decoded = JWT::decode($token, new Key($this->secretKey, 'HS256'));
        return (array)$decoded;
    }

    /**
     * Verify password supporting password_hash (bcrypt/argon2), SHA-512, and legacy MD5.
     */
    public function verifyPassword(string $password, string $storedHash): bool
    {
        if (password_verify($password, $storedHash)) {
            return true;
        }

        if (hash('sha512', $password) === $storedHash) {
            return true;
        }

        if (md5($password) === $storedHash) {
            return true;
        }

        return false;
    }

    /**
     * Hash a password securely using PHP's native password_hash.
     */
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
