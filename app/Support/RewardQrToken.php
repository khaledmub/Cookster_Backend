<?php

namespace App\Support;

class RewardQrToken
{
    public const TYPE = 'cookster_redeem';

    public const PREFIX = 'cookster_redeem:';

    public const TTL_SECONDS = 60;

    /**
     * @return array{payload: string, expires_in: int}
     */
    public static function issue(string $userId): array
    {
        $body = self::encodePayload([
            'type' => self::TYPE,
            'user_id' => $userId,
            'exp' => time() + self::TTL_SECONDS,
            'jti' => bin2hex(random_bytes(16)),
        ]);

        return [
            'payload' => self::PREFIX.$body.'.'.self::sign($body),
            'expires_in' => self::TTL_SECONDS,
        ];
    }

    /**
     * @return array{type: string, user_id: string, exp: int, jti: string}|null
     */
    public static function parse(string $raw): ?array
    {
        $token = trim($raw);
        if ($token === '') {
            return null;
        }

        if (str_starts_with($token, self::PREFIX)) {
            $token = substr($token, strlen(self::PREFIX));
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$body, $sig] = $parts;
        if (! hash_equals(self::sign($body), $sig)) {
            return null;
        }

        $json = base64_decode(strtr($body, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }

        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($data)
            || ($data['type'] ?? null) !== self::TYPE
            || empty($data['user_id'])
            || empty($data['exp'])
        ) {
            return null;
        }

        if ((int) $data['exp'] < time()) {
            return null;
        }

        return [
            'type' => self::TYPE,
            'user_id' => (string) $data['user_id'],
            'exp' => (int) $data['exp'],
            'jti' => (string) ($data['jti'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function encodePayload(array $payload): string
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private static function sign(string $body): string
    {
        return hash_hmac('sha256', $body, self::secret());
    }

    private static function secret(): string
    {
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return $key;
    }
}
