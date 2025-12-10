<?php
/**
 * UniPanel Password Manager
 *
 * Güçlü şifre hashing ve doğrulama yardımcı sınıfı.
 */

namespace UniPanel\General;

class PasswordManager
{
    private const DEFAULT_ALGO = PASSWORD_BCRYPT;
    private const DEFAULT_OPTIONS = [
        'cost' => 12,
    ];

    /**
     * Güvenli şifre hash üretir.
     */
    public static function hash(string $password, array $options = []): string
    {
        $opts = array_replace(self::DEFAULT_OPTIONS, $options);

        $hash = password_hash($password, self::DEFAULT_ALGO, $opts);

        if ($hash === false) {
            throw new \RuntimeException('Şifre hashlenemedi');
        }

        return $hash;
    }

    /**
     * Şifre-hash eşleşmesini doğrular.
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Hash güncellenmeli mi kontrol eder.
     */
    public static function needsRehash(string $hash, array $options = []): bool
    {
        $opts = array_replace(self::DEFAULT_OPTIONS, $options);

        return password_needs_rehash($hash, self::DEFAULT_ALGO, $opts);
    }

    /**
     * Rastgele güçlü şifre üretir (opsiyonel yardımcı).
     */
    public static function generate(int $length = 16): string
    {
        $length = max(8, $length);

        $bytes = random_bytes($length);
        $encoded = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return substr($encoded, 0, $length);
    }
}


