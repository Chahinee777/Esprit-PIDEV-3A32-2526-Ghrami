<?php

namespace App\Service;

class PasswordResetTokenService
{
    public function __construct(
        private readonly string $appSecret,
    ) {
    }

    public function createToken(int $userId, int $ttlSeconds = 3600): string
    {
        $payload = [
            'uid' => $userId,
            'exp' => time() + $ttlSeconds,
            'rnd' => bin2hex(random_bytes(12)),
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $body = $this->base64UrlEncode($json);
        $sig = $this->base64UrlEncode(hash_hmac('sha256', $body, $this->appSecret, true));

        return $body . '.' . $sig;
    }

    public function parseToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return ['valid' => false, 'reason' => 'invalid_format'];
        }

        [$body, $signature] = $parts;
        $expectedSig = $this->base64UrlEncode(hash_hmac('sha256', $body, $this->appSecret, true));

        if (!hash_equals($expectedSig, $signature)) {
            return ['valid' => false, 'reason' => 'invalid_signature'];
        }

        $decoded = $this->base64UrlDecode($body);
        if ($decoded === false) {
            return ['valid' => false, 'reason' => 'invalid_payload'];
        }

        try {
            $payload = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['valid' => false, 'reason' => 'invalid_json'];
        }

        $uid = isset($payload['uid']) ? (int) $payload['uid'] : 0;
        $exp = isset($payload['exp']) ? (int) $payload['exp'] : 0;

        if ($uid <= 0 || $exp <= 0) {
            return ['valid' => false, 'reason' => 'invalid_claims'];
        }

        if (time() > $exp) {
            return ['valid' => false, 'reason' => 'expired'];
        }

        return ['valid' => true, 'user_id' => $uid, 'expires_at' => $exp];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string|false
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(strtr($value, '-_', '+/'), true);
    }
}
