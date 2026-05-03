<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\TwoFactorAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/security/2fa')]
final class TwoFactorAuthController extends AbstractController
{
    public function __construct(
        private TwoFactorAuthService $twoFactorService,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    /**
     * Show 2FA setup page with QR code
     */
    #[Route('/setup', name: 'app_2fa_setup', methods: ['GET'])]
    public function setupPage(SessionInterface $session): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->isTwoFactorEnabled) {
            $this->addFlash('warning', '2FA is already enabled on your account.');
            return $this->redirectToRoute('app_user_security_settings');
        }

        $pending = $session->get('pending_2fa_setup');

        if (!is_array($pending) || !isset($pending['secret'], $pending['qrCode'], $pending['backupCodes'], $pending['hashedBackupCodes'])) {
            // Generate pending setup data once and keep it stable across retries.
            $twoFactorData = $this->twoFactorService->generateTwoFactorSecret($user);
            $backupCodes = $this->twoFactorService->generateBackupCodes();
            $hashedBackupCodes = $this->twoFactorService->hashBackupCodes($backupCodes);

            $pending = [
                'secret' => $twoFactorData['secret'],
                'qrCode' => $twoFactorData['qrCode'],
                'backupCodes' => $backupCodes,
                'hashedBackupCodes' => $hashedBackupCodes,
            ];

            $session->set('pending_2fa_setup', $pending);
        }

        return $this->render('security/2fa-setup.html.twig', [
            'qrCode' => $pending['qrCode'],
            'secret' => $pending['secret'],
            'backupCodes' => $pending['backupCodes'],
            'debugCodes' => $this->getParameter('kernel.environment') === 'dev'
                ? $this->twoFactorService->getDebugTotpCodes($pending['secret'])
                : null,
        ]);
    }

    /**
     * Verify 2FA setup with TOTP code
     */
    #[Route('/verify-setup', name: 'app_2fa_verify_setup', methods: ['POST'])]
    public function verifySetup(Request $request, SessionInterface $session): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('verify_2fa_setup', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_2fa_setup');
        }

        $pending = $session->get('pending_2fa_setup');
        $secret = is_array($pending) ? ($pending['secret'] ?? null) : null;
        $code = trim((string) $request->request->get('code', ''));
        $hashedCodes = is_array($pending) ? ($pending['hashedBackupCodes'] ?? null) : null;

        if (!$secret) {
            $this->addFlash('error', '2FA setup session expired. Please scan the QR code again.');
            return $this->redirectToRoute('app_2fa_setup');
        }

        if ($code === '') {
            $this->addFlash('error', 'Please enter the 6-digit code from your authenticator app.');
            return $this->redirectToRoute('app_2fa_setup');
        }

        // Verify the 6-digit code
        // Setup verification allows wider tolerance to absorb local clock drift.
        if (!$this->twoFactorService->verifyTotp($secret, $code, null, 10)) {
            if ($this->getParameter('kernel.environment') === 'dev') {
                $debug = $this->twoFactorService->getDebugTotpCodes($secret);
                error_log(sprintf(
                    '[2FA setup] invalid code for user %s. submitted=%s, prev=%s, current=%s, next=%s',
                    (string) $user->email,
                    preg_replace('/\D+/', '', (string) $code),
                    $debug['previous'],
                    $debug['current'],
                    $debug['next']
                ));
            }
            $this->addFlash('error', 'Invalid verification code. Try the current code or wait for the next one.');
            return $this->redirectToRoute('app_2fa_setup');
        }

        // Enable 2FA
        $this->twoFactorService->enableTwoFactor($user, $secret);

        // Store backup codes
        if (is_array($hashedCodes) && $hashedCodes !== []) {
            $user->twoFactorBackupCodes = $hashedCodes;
        }

        $session->remove('pending_2fa_setup');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Audit log
        $this->logSecurityEvent($user, 'two_factor_enabled', 'User enabled 2FA');

        $this->addFlash('success', '✅ Two-Factor Authentication enabled! Keep your backup codes safe.');
        return $this->redirectToRoute('app_user_security_settings');
    }

    /**
     * Disable 2FA (requires password confirmation)
     */
    #[Route('/disable', name: 'app_2fa_disable', methods: ['POST'])]
    public function disable(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user->isTwoFactorEnabled) {
            $this->addFlash('warning', '2FA is not currently enabled.');
            return $this->redirectToRoute('app_user_security_settings');
        }

        if (!$this->isCsrfTokenValid('disable_2fa', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_user_security_settings');
        }

        // Require password confirmation
        $password = $request->request->get('password');
        if (!$password || !$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash('error', 'Invalid password. 2FA not disabled.');
            return $this->redirectToRoute('app_user_security_settings');
        }

        // Disable 2FA
        $this->twoFactorService->disableTwoFactor($user);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Audit log
        $this->logSecurityEvent($user, 'two_factor_disabled', 'User disabled 2FA');

        $this->addFlash('success', '2FA has been disabled.');
        return $this->redirectToRoute('app_user_security_settings');
    }

    /**
     * Regenerate backup codes
     */
    #[Route('/regenerate-backup-codes', name: 'app_2fa_regenerate_backup', methods: ['POST'])]
    public function regenerateBackupCodes(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->isTwoFactorEnabled) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('regenerate_codes', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_user_security_settings');
        }

        // Generate new backup codes
        $backupCodes = $this->twoFactorService->generateBackupCodes();
        $hashedCodes = $this->twoFactorService->hashBackupCodes($backupCodes);

        $user->twoFactorBackupCodes = $hashedCodes;
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Audit log
        $this->logSecurityEvent($user, 'backup_codes_regenerated', 'User regenerated backup codes');

        return $this->json([
            'ok' => true,
            'message' => 'Backup codes regenerated',
            'backupCodes' => $backupCodes,
        ]);
    }

    /**
     * Verify 2FA code during login
     */
    #[Route('/verify-login', name: 'app_2fa_verify_login', methods: ['POST'])]
    public function verifyLogin(Request $request, SessionInterface $session): Response
    {
        // Get user_id from session (set during initial login before 2FA prompt)
        $userId = $session->get('pending_2fa_user_id');
        
        if (!$userId) {
            $this->addFlash('error', 'Session expired. Please try logging in again.');
            return $this->redirectToRoute('app_login');
        }

        $user = $this->entityManager->find(User::class, $userId);
        if (!$user || !$user->isTwoFactorEnabled) {
            $this->addFlash('error', 'Invalid or expired 2FA session.');
            return $this->redirectToRoute('app_login');
        }

        $code = trim((string) $request->request->get('code', ''));

        // Try regular TOTP code
        if (strlen($code) === 6 && ctype_digit($code)) {
            if ($this->twoFactorService->verifyTotp($user->twoFactorSecret, $code)) {
                // Valid TOTP code - complete login
                $session->remove('pending_2fa_user_id');
                $this->authenticateUser($user, $session);
                
                $this->addFlash('success', '✅ Logged in successfully!');
                return $this->redirectToRoute('app_social_index');
            }
        }

        // Try backup code (format: XXXX-XXXX-XXXX)
        if (preg_match('/^\w{4}-\w{4}-\w{4}$/', $code)) {
            $backupCodes = $user->twoFactorBackupCodes ?? [];
            if ($this->twoFactorService->verifyBackupCode($code, $backupCodes)) {
                // Valid backup code - update and complete login
                $user->twoFactorBackupCodes = $backupCodes;
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                $session->remove('pending_2fa_user_id');
                $this->authenticateUser($user, $session);

                $this->addFlash('warning', '⚠️ You used a backup code. Generate new backup codes from security settings.');
                return $this->redirectToRoute('app_social_index');
            }
        }

        $this->addFlash('error', 'Invalid authentication code.');
        return $this->redirectToRoute('app_2fa_verify_form', ['userId' => $userId]);
    }

    /**
     * Show 2FA verification form during login
     */
    #[Route('/verify-form', name: 'app_2fa_verify_form', methods: ['GET'])]
    public function verifyForm(Request $request, SessionInterface $session): Response
    {
        $userId = $session->get('pending_2fa_user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/2fa-verify.html.twig', [
            'userId' => $userId,
        ]);
    }

    /**
     * Security settings page
     */
    #[Route('/security-settings', name: 'app_user_security_settings', methods: ['GET'])]
    public function securitySettings(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('user/security-settings.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * Log security events for audit trail
     */
    private function logSecurityEvent(User $user, string $eventType, string $description): void
    {
        // TODO: Implement security audit log
        // For now, just log to system
        error_log("[SECURITY] User #{$user->id} ({$user->email}): {$eventType} - {$description}");
    }

    /**
     * Authenticate user and create session
     */
    private function authenticateUser(User $user, SessionInterface $session): void
    {
        $user->lastLogin = new \DateTime();
        $user->isOnline = true;
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Create session
        $session->set('user_id', $user->id);
    }
}
