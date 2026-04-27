<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\LoginFormAuthenticator;
use App\Service\EmailService;
use App\Service\GoogleAuthService;
use App\Service\PasswordResetTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class AuthController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() instanceof User) {
            $user = $this->getUser();
            // Admin should go to admin dashboard, others to social feed
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                return $this->redirectToRoute('app_admin_dashboard');
            }
            return $this->redirectToRoute('app_social_index');
        }

        return $this->render('auth/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
    ): Response {
        if ($this->getUser() instanceof User) {
            $user = $this->getUser();
            // Admin should go to admin dashboard, others to social feed
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                return $this->redirectToRoute('app_admin_dashboard');
            }
            return $this->redirectToRoute('app_social_index');
        }

        $values = [
            'username' => '',
            'full_name' => '',
            'email' => '',
            'location' => '',
            'bio' => '',
        ];
        $errors = [];
        $fieldErrors = [];

        if ($request->isMethod('POST')) {
            $values['username'] = trim((string) $request->request->get('username', ''));
            $values['full_name'] = trim((string) $request->request->get('full_name', ''));
            $values['email'] = trim((string) $request->request->get('email', ''));
            $values['location'] = trim((string) $request->request->get('location', ''));
            $values['bio'] = trim((string) $request->request->get('bio', ''));
            $password = (string) $request->request->get('password', '');
            $confirmPassword = (string) $request->request->get('confirm_password', '');

            if (!$this->isCsrfTokenValid('register', (string) $request->request->get('_csrf_token'))) {
                $errors[] = 'Invalid CSRF token.';
            }

            // Create user object for validation
            $user = new User();
            $user->username = $values['username'];
            $user->fullName = $values['full_name'];
            $user->email = mb_strtolower($values['email']);
            $user->location = $values['location'];
            $user->bio = $values['bio'] !== '' ? $values['bio'] : null;
            $user->password = $password; // Temporary, for validation

            // Validate using Symfony Validator with registration rules.
            $violations = $validator->validate($user, null, ['Default', 'registration']);

            foreach ($violations as $violation) {
                $propertyPath = $violation->getPropertyPath();
                // Convert camelCase to snake_case for frontend compatibility
                $propertyPath = strtolower(preg_replace('/([A-Z])/', '_$1', $propertyPath));
                $propertyPath = ltrim($propertyPath, '_');
                if (!isset($fieldErrors[$propertyPath])) {
                    $fieldErrors[$propertyPath] = $violation->getMessage();
                }
            }

            if (trim($password) === '' && !isset($fieldErrors['password'])) {
                $fieldErrors['password'] = 'Password is required.';
            }

            // Validate password confirmation match
            if ($password !== $confirmPassword) {
                $fieldErrors['confirm_password'] = 'Password confirmation does not match.';
            }

            // Return JSON for AJAX requests
            if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
                if ($fieldErrors === [] && $errors === []) {
                    $user->password = $passwordHasher->hashPassword($user, $password);
                    $user->createdAt = new \DateTime();
                    $user->lastLogin = null;
                    $user->isOnline = false;
                    $user->authProvider = 'local';
                    $user->isBanned = false;

                    $entityManager->persist($user);
                    $entityManager->flush();

                    return $this->json(['ok' => true, 'message' => 'Account created successfully'], Response::HTTP_OK);
                }

                return $this->json(['ok' => false, 'errors' => $fieldErrors], Response::HTTP_BAD_REQUEST);
            }

            if ($fieldErrors === [] && $errors === []) {
                $user->password = $passwordHasher->hashPassword($user, $password);
                $user->createdAt = new \DateTime();
                $user->lastLogin = null;
                $user->isOnline = false;
                $user->authProvider = 'local';
                $user->isBanned = false;

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Account created. You can sign in now.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('auth/register.html.twig', [
            'values' => $values,
            'errors' => array_merge($errors, array_values($fieldErrors)),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('Logout is handled by the firewall.');
    }

    #[Route('/reset-password/request', name: 'app_reset_password_request', methods: ['GET', 'POST'])]
    public function requestPasswordReset(
        Request $request,
        UserRepository $userRepository,
        PasswordResetTokenService $tokenService,
        EmailService $emailService,
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));

            if ($this->isCsrfTokenValid('reset_password_request', (string) $request->request->get('_csrf_token')) && $email !== '') {
                $user = $userRepository->findOneBy(['email' => mb_strtolower($email)]);

                if ($user instanceof User && !$user->isBanned) {
                    $token = $tokenService->createToken((int) $user->id, 3600);
                    $resetUrl = $this->generateUrl('app_reset_password_confirm', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);

                    $sent = $emailService->sendPasswordResetEmail(
                        (string) $user->email,
                        (string) ($user->fullName ?: $user->username),
                        $resetUrl
                    );

                    if (!$sent) {
                        $this->addFlash('error', 'Reset email could not be sent with current mailer settings.');
                    }
                }
            }

            $this->addFlash('success', 'If an account exists for this email, a reset link has been sent.');
            return $this->redirectToRoute('app_reset_password_request');
        }

        return $this->render('auth/reset_password_request.html.twig');
    }

    #[Route('/reset-password/confirm', name: 'app_reset_password_confirm', methods: ['GET', 'POST'])]
    public function confirmPasswordReset(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        PasswordResetTokenService $tokenService,
        ValidatorInterface $validator,
    ): Response {
        $token = (string) $request->query->get('token', $request->request->get('token', ''));
        $parsed = $tokenService->parseToken($token);
        $errors = [];

        if (!($parsed['valid'] ?? false)) {
            $errors[] = 'This reset link is invalid or expired.';
        }

        if ($request->isMethod('POST') && $errors === []) {
            if (!$this->isCsrfTokenValid('reset_password_confirm', (string) $request->request->get('_csrf_token'))) {
                $errors[] = 'Invalid CSRF token.';
            }

            $password = (string) $request->request->get('password', '');
            $confirm = (string) $request->request->get('confirm_password', '');

            $user = null;
            if ($errors === []) {
                $user = $userRepository->find((int) $parsed['user_id']);
                if (!$user instanceof User || $user->isBanned) {
                    $errors[] = 'Account unavailable for password reset.';
                }
            }

            if ($errors === [] && $user instanceof User) {
                // Set password for validation
                $user->password = $password;

                // Validate password using Symfony Validator
                $violations = $validator->validate($user, null, ['password_reset']);

                foreach ($violations as $violation) {
                    $errors[] = $violation->getMessage();
                }

                // Validate password confirmation match
                if ($password !== $confirm) {
                    $errors[] = 'Password confirmation does not match.';
                }

                if ($errors === []) {
                    $user->password = $passwordHasher->hashPassword($user, $password);
                    $user->authProvider = $user->authProvider ?: 'local';
                    $entityManager->flush();

                    $this->addFlash('success', 'Password updated. You can sign in now.');
                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('auth/reset_password_confirm.html.twig', [
            'token' => $token,
            'errors' => $errors,
            'isTokenValid' => empty($errors),
        ]);
    }

    #[Route('/auth/google/start', name: 'app_auth_google_start', methods: ['GET'])]
    public function googleStart(SessionInterface $session, GoogleAuthService $googleAuthService): Response
    {
        if (!$googleAuthService->isConfigured()) {
            $this->addFlash('error', 'Google OAuth is not configured yet. Add GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GOOGLE_REDIRECT_URI.');
            return $this->redirectToRoute('app_login');
        }

        $state = bin2hex(random_bytes(16));
        $session->set('google_oauth_state', $state);

        return new RedirectResponse($googleAuthService->buildLoginUrl([
            'state' => $state,
            'calendar' => true,
            'access_type' => 'offline',
            'prompt' => 'consent select_account',
            'approval_prompt' => 'force',
        ]));
    }

    #[Route('/google/callback', name: 'app_auth_google_callback_universal', methods: ['GET'])]
    public function googleUniversalCallback(
        Request $request,
        SessionInterface $session,
        GoogleAuthService $googleAuthService,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator
    ): Response {
        // If we have an expected state for calendar, it's a calendar connection
        if ($session->has('google_calendar_oauth_state')) {
            return $this->googleCalendarCallback($request, $session, $googleAuthService);
        }

        // Otherwise, it's a login
        return $this->googleCallback($request, $session, $googleAuthService, $userRepository, $entityManager, $userAuthenticator, $loginFormAuthenticator);
    }

    #[Route('/auth/google/callback', name: 'app_auth_google_callback', methods: ['GET'])]
    public function googleCallback(
        Request $request,
        SessionInterface $session,
        GoogleAuthService $googleAuthService,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserAuthenticatorInterface $userAuthenticator,
        LoginFormAuthenticator $loginFormAuthenticator,
    ): Response {
        $state = (string) $request->query->get('state', '');
        $expectedState = (string) $session->get('google_oauth_state', '');
        $session->remove('google_oauth_state');

        if ($state === '' || !GoogleAuthService::validateState($state, $expectedState)) {
            $this->addFlash('error', 'Invalid Google OAuth state.');
            return $this->redirectToRoute('app_login');
        }

        $code = (string) $request->query->get('code', '');
        if ($code === '') {
            $this->addFlash('error', 'Google login was cancelled or failed.');
            return $this->redirectToRoute('app_login');
        }

        if (!$googleAuthService->isConfigured()) {
            $this->addFlash('error', 'Google OAuth credentials are incomplete.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $loginCallbackUrl = $this->generateUrl('app_auth_google_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
            $token = $googleAuthService->handleAuthorizationCode($code, $loginCallbackUrl);
            if ($token === null) {
                throw new \RuntimeException('Failed to exchange Google authorization code.');
            }

            $profile = $googleAuthService->getUserProfile();
            if (!is_array($profile)) {
                throw new \RuntimeException('Failed to fetch Google profile.');
            }

            $googleId = (string) ($profile['id'] ?? '');
            $email = mb_strtolower((string) ($profile['email'] ?? ''));
            $fullName = (string) ($profile['name'] ?? '');
            $picture = (string) ($profile['picture'] ?? '');

            if ($googleId === '' || $email === '') {
                throw new \RuntimeException('Incomplete Google profile data.');
            }

            $user = $userRepository->findOneBy(['googleId' => $googleId]);
            if (!$user instanceof User) {
                $user = $userRepository->findOneBy(['email' => $email]);
            }

            if (!$user instanceof User) {
                $baseUsername = preg_replace('/[^a-zA-Z0-9_]+/', '', strstr($email, '@', true) ?: 'googleuser');
                $baseUsername = $baseUsername !== '' ? $baseUsername : 'googleuser';
                $username = $baseUsername;
                $i = 1;
                while ($userRepository->hasUsername($username)) {
                    $username = $baseUsername . $i;
                    ++$i;
                }

                $user = new User();
                $user->username = $username;
                $user->email = $email;
                $user->createdAt = new \DateTime();
                $entityManager->persist($user);
            }

            $user->googleId = $googleId;
            $user->authProvider = 'google';
            $user->fullName = $fullName !== '' ? $fullName : $user->fullName;
            if ($picture !== '' && $user->profilePicture === null) {
                $user->profilePicture = $picture;
            }
            $user->isBanned = $user->isBanned ?? false;

            $entityManager->flush();

            // Store the access token in session for use in creating Google Meet links
            if (!empty($token['access_token'])) {
                $session->set('google_access_token', $token['access_token']);
            }
            if (!empty($token['refresh_token'])) {
                $session->set('google_refresh_token', $token['refresh_token']);
            }

            return $userAuthenticator->authenticateUser($user, $loginFormAuthenticator, $request);
        } catch (\Throwable) {
            $this->addFlash('error', 'Google authentication failed. Please try again.');
            return $this->redirectToRoute('app_login');
        }
    }

    #[Route('/auth/google/calendar/connect', name: 'app_auth_google_calendar_connect', methods: ['GET'])]
    public function googleCalendarConnect(
        Request $request,
        GoogleAuthService $googleAuthService,
        SessionInterface $session
    ): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$googleAuthService->isConfigured()) {
            $this->addFlash('error', 'Google OAuth is not configured. Cannot link Google Calendar.');
            return $this->redirectToRoute('app_meetings_index');
        }

        $isPopup = $request->query->getBoolean('popup', false);
        $session->set('google_calendar_popup', $isPopup);

        $state = bin2hex(random_bytes(16));
        $session->set('google_calendar_oauth_state', $state);

        $calendarCallbackUrl = $request->getSchemeAndHttpHost() . '/google/callback';

        $authUrl = $googleAuthService->buildLoginUrl([
            'state' => $state,
            'calendar' => true,
            'access_type' => 'offline',
            'prompt' => 'consent select_account',
            'include_granted_scopes' => true,
            'redirect_uri' => $calendarCallbackUrl,
        ]);

        return new RedirectResponse($authUrl);
    }

    #[Route('/auth/google/calendar/callback', name: 'app_auth_google_calendar_callback', methods: ['GET'])]
    public function googleCalendarCallback(
        Request $request,
        SessionInterface $session,
        GoogleAuthService $googleAuthService,
    ): Response {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $state = (string) $request->query->get('state', '');
        $expectedState = (string) $session->get('google_calendar_oauth_state', '');
        $session->remove('google_calendar_oauth_state');
        $isPopup = (bool) $session->get('google_calendar_popup', false);

        if ($state === '' || !GoogleAuthService::validateState($state, $expectedState)) {
            if ($isPopup) {
                $session->remove('google_calendar_popup');
                return new Response('<!doctype html><html><body><script>\n'
                    . 'if (window.opener) { window.opener.postMessage({ type: "ghrami-google-calendar", ok: false }, window.location.origin); }\n'
                    . 'window.close();\n'
                    . '</script>Invalid OAuth state. You can close this window.</body></html>');
            }
            $this->addFlash('error', 'Invalid Google OAuth state for calendar.');
            return $this->redirectToRoute('app_meetings_index');
        }

        $code = (string) $request->query->get('code', '');
        if ($code === '') {
            if ($isPopup) {
                $session->remove('google_calendar_popup');
                return new Response('<!doctype html><html><body><script>\n'
                    . 'if (window.opener) { window.opener.postMessage({ type: "ghrami-google-calendar", ok: false }, window.location.origin); }\n'
                    . 'window.close();\n'
                    . '</script>Google Calendar linking cancelled. You can close this window.</body></html>');
            }
            $this->addFlash('info', 'Google Calendar linking was cancelled.');
            return $this->redirectToRoute('app_meetings_index');
        }

        try {
            $calendarCallbackUrl = $request->getSchemeAndHttpHost() . '/google/callback';
            $token = $googleAuthService->handleAuthorizationCode($code, $calendarCallbackUrl);
            if ($token === null) {
                throw new \RuntimeException('Failed to exchange Google authorization code.');
            }

            // Store the calendar-authorized token in session
            if (!empty($token['access_token'])) {
                $session->set('google_access_token', $token['access_token']);
            }
            if (!empty($token['refresh_token'])) {
                $session->set('google_refresh_token', $token['refresh_token']);
            }

            $session->remove('google_calendar_popup');

            if ($isPopup) {
                return new Response('<!doctype html><html><body><script>\n'
                    . 'if (window.opener) { window.opener.postMessage({ type: "ghrami-google-calendar", ok: true }, window.location.origin); }\n'
                    . 'window.close();\n'
                    . '</script>Google Calendar connected. You can close this window.</body></html>');
            }

            $this->addFlash('success', '✅ Google Calendar connected successfully! Virtual meetings will now be added to your calendar.');
            return $this->redirectToRoute('app_meetings_index');
        } catch (\Throwable) {
            $session->remove('google_calendar_popup');
            if ($isPopup) {
                return new Response('<!doctype html><html><body><script>\n'
                    . 'if (window.opener) { window.opener.postMessage({ type: "ghrami-google-calendar", ok: false }, window.location.origin); }\n'
                    . 'window.close();\n'
                    . '</script>Google Calendar linking failed. You can close this window.</body></html>');
            }
            $this->addFlash('error', 'Google Calendar linking failed. Please try again.');
            return $this->redirectToRoute('app_meetings_index');
        }
    }
}
