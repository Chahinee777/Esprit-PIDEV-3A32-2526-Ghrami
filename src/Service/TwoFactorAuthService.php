<?php

namespace App\Service;

use App\Entity\User;
use OTPHP\TOTP;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Two-Factor Authentication service using TOTP (Time-based One-Time Password)
 * Generates QR codes for authenticator apps (Google Authenticator, Authy, Microsoft Authenticator, etc.)
 */
final class TwoFactorAuthService
{
    private const APP_NAME = 'Ghrami';
    private const TOTP_PERIOD_SECONDS = 30;
    private const TOTP_DEFAULT_WINDOW_STEPS = 1; // Default login tolerance: +/- 30s

    /**
     * Generate TOTP secret and QR code for user
     * 
     * @return array{secret: string, qrCode: string}
     */
    public function generateTwoFactorSecret(User $user): array
    {
        // Generate TOTP secret (base32 encoded random bytes)
        $totp = TOTP::create();
        $secret = $totp->getSecret();

        // Set label and issuer on the TOTP object
        $totp->setLabel($user->email);
        $totp->setIssuer(self::APP_NAME);

        // Build provisioning URI (otpauth://totp/...)
        $uri = $totp->getProvisioningUri();

        // Generate QR code from URI
        $qrCode = new QrCode($uri);

        $writer = new SvgWriter();
        $qrCodeImage = $writer->write($qrCode);
        
        return [
            'secret' => $secret,
            'qrCode' => $qrCodeImage->getString(),
            'uri' => $uri,
        ];
    }

    /**
     * Verify a TOTP code against stored secret
     * 
     * @param string $secret The Base32 encoded TOTP secret
     * @param string $code The 6-digit code from authenticator app
     * @param int $now Current time (for testing)
     * @return bool True if code is valid
     */
    public function verifyTotp(string $secret, string $code, ?int $now = null, int $windowSteps = self::TOTP_DEFAULT_WINDOW_STEPS): bool
    {
        if (empty($secret) || empty($code)) {
            return false;
        }

        $normalizedCode = preg_replace('/\D+/', '', $code);
        if (!is_string($normalizedCode) || strlen($normalizedCode) !== 6 || !ctype_digit($normalizedCode)) {
            return false;
        }

        $windowSteps = max(0, $windowSteps);

        try {
            $totp = TOTP::createFromSecret($secret);
            $timestamp = $now ?? time();

            // Explicitly verify across multiple 30-second windows to avoid library-specific leeway pitfalls.
            for ($step = -$windowSteps; $step <= $windowSteps; $step++) {
                $candidate = $totp->at($timestamp + ($step * self::TOTP_PERIOD_SECONDS));
                if (hash_equals($candidate, $normalizedCode)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            error_log('[2FA] verifyTotp exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Dev helper: return neighboring TOTP codes around current time for troubleshooting.
     *
     * @return array{previous: string, current: string, next: string}
     */
    public function getDebugTotpCodes(string $secret, ?int $now = null): array
    {
        $timestamp = $now ?? time();
        $totp = TOTP::createFromSecret($secret);

        return [
            'previous' => $totp->at($timestamp - self::TOTP_PERIOD_SECONDS),
            'current' => $totp->at($timestamp),
            'next' => $totp->at($timestamp + self::TOTP_PERIOD_SECONDS),
        ];
    }

    /**
     * Enable 2FA for user
     */
    public function enableTwoFactor(User $user, string $secret): void
    {
        $user->twoFactorSecret = $secret;
        $user->isTwoFactorEnabled = true;
        $user->twoFactorEnabledAt = new \DateTime();
    }

    /**
     * Disable 2FA for user
     */
    public function disableTwoFactor(User $user): void
    {
        $user->twoFactorSecret = null;
        $user->isTwoFactorEnabled = false;
    }

    /**
     * Generate backup codes for recovery (in case authenticator app is lost)
     * 
     * @return array<int, string> Array of 8 backup codes
     */
    public function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            // Generate codes like: XXXX-XXXX-XXXX format
            $code = strtoupper(
                substr(bin2hex(random_bytes(6)), 0, 12)
            );
            $codes[] = substr($code, 0, 4) . '-' . substr($code, 4, 4) . '-' . substr($code, 8, 4);
        }
        return $codes;
    }

    /**
     * Hash backup codes for storage
     * 
     * @param array<string> $codes
     * @return array<string>
     */
    public function hashBackupCodes(array $codes): array
    {
        return array_map(fn($code) => password_hash(str_replace('-', '', $code), PASSWORD_BCRYPT), $codes);
    }

    /**
     * Verify and consume a backup code
     * 
     * @param string $code The backup code entered by user
     * @param array<string> $hashedCodes Stored hashed backup codes
     * @return bool True if code was valid and consumed
     */
    public function verifyBackupCode(string $code, array &$hashedCodes): bool
    {
        $cleanCode = str_replace('-', '', $code);
        
        foreach ($hashedCodes as $index => $hashedCode) {
            if (password_verify($cleanCode, $hashedCode)) {
                // Consume the code (remove it)
                unset($hashedCodes[$index]);
                return true;
            }
        }
        
        return false;
    }
}
