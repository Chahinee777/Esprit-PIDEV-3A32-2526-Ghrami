<?php

namespace App\Service;

use Google\Client;
use Google\Exception;
use Google\Service\Oauth2;

/**
 * Service for Google OAuth 2.0 authentication flow.
 * 
 * Setup:
 *   1. Install Google Client library: composer require google/apiclient
 *   2. Create OAuth 2.0 credentials at Google Cloud Console
 *   3. Add to .env:
 *      GOOGLE_CLIENT_ID=your_client_id
 *      GOOGLE_CLIENT_SECRET=your_client_secret
 *      GOOGLE_REDIRECT_URI=http://localhost/auth/google/callback
 */
class GoogleAuthService
{
    private Client $client;
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
        $this->redirectUri = $_ENV['GOOGLE_REDIRECT_URI'] ?? 'http://localhost/auth/google/callback';

        $this->client = new Client();
        $this->client->setClientId($this->clientId);
        $this->client->setClientSecret($this->clientSecret);
        $this->client->setRedirectUri($this->redirectUri);
        $this->client->addScope('openid email profile');
    }

    /**
     * Returns true if Google OAuth is properly configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }

    /**
     * Returns the Google authentication URL with specific scopes.
     * 
     * @param array $additionalScopes Scopes to add beyond the default
     * @return string The authentication URL
     */
    public function getAuthorizationUrl(array $additionalScopes = []): string
    {
        // Scopes are already set in constructor, just add any additional ones
        foreach ($additionalScopes as $scope) {
            $this->client->addScope($scope);
        }
        return $this->client->createAuthUrl();
    }

    /**
     * Returns Google Calendar authorization URL for users to grant calendar permissions.
     *
     * @return string The authentication URL
     */
    public function getCalendarAuthorizationUrl(): string
    {
        return $this->getAuthorizationUrl(['https://www.googleapis.com/auth/calendar.events']);
    }

    /**
     * Exchanges an authorization code for an access token.
     * Call this from your callback endpoint after the user returns from Google.
     *
     * @param string $code The authorization code from Google
     * @return array|null Array with 'access_token', 'id_token', etc., or null on failure
     */
    public function handleAuthorizationCode(string $code, ?string $redirectUri = null): ?array
    {
        try {
            if ($redirectUri !== null && $redirectUri !== '') {
                $this->client->setRedirectUri($redirectUri);
            }

            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            
            if (isset($token['error'])) {
                error_log('Google OAuth error: ' . $token['error']);
                return null;
            }

            $this->client->setAccessToken($token);
            return $token;
        } catch (Exception $e) {
            error_log('Failed to exchange authorization code: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Exchanges a refresh token for a new access token.
     */
    public function refreshAccessTokenWithRefreshToken(string $refreshToken): ?array
    {
        if ($refreshToken === '') {
            return null;
        }

        try {
            $this->client->setAccessType('offline');
            $token = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);

            if (!is_array($token) || isset($token['error']) || empty($token['access_token'])) {
                return null;
            }

            if (empty($token['refresh_token'])) {
                $token['refresh_token'] = $refreshToken;
            }

            $this->client->setAccessToken($token);
            return $token;
        } catch (Exception $e) {
            error_log('Failed to refresh access token with refresh token: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Gets the authenticated user's profile information from Google.
     * Must be called after successfully setting the access token.
     *
     * @return array|null Array with 'id', 'email', 'name', 'picture', etc., or null on failure
     */
    public function getUserProfile(): ?array
    {
        try {
            $oauth2 = new Oauth2($this->client);
            $userInfo = $oauth2->userinfo->get();

            return [
                'id' => $userInfo->getId(),
                'email' => $userInfo->getEmail(),
                'name' => $userInfo->getName(),
                'given_name' => $userInfo->getGivenName(),
                'family_name' => $userInfo->getFamilyName(),
                'picture' => $userInfo->getPicture(),
                'locale' => $userInfo->getLocale(),
            ];
        } catch (Exception $e) {
            error_log('Failed to get user profile: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sets an existing access token (e.g., from session/database).
     *
     * @param array $token Token array with 'access_token' and optionally 'refresh_token'
     */
    public function setAccessToken(array $token): void
    {
        $this->client->setAccessToken($token);
    }

    /**
     * Gets the current access token.
     *
     * @return array
     */
    public function getAccessToken(): array
    {
        return $this->client->getAccessToken() ?? [];
    }

    /**
     * Checks if a token is expired and refreshes it if necessary.
     *
     * @return bool True if token is valid (either not expired or successfully refreshed)
     */
    public function refreshTokenIfNeeded(): bool
    {
        try {
            $accessToken = $this->client->getAccessToken();
            if (empty($accessToken)) {
                return false;
            }

            if ($this->client->isAccessTokenExpired()) {
                $this->client->fetchAccessTokenWithRefreshToken(
                    $this->client->getRefreshToken()
                );
                return true;
            }

            return true;
        } catch (Exception $e) {
            error_log('Failed to refresh token: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Revokes the current access token and disconnects the user.
     *
     * @return bool True if revocation succeeded
     */
    public function revokeToken(): bool
    {
        try {
            $this->client->revokeToken();
            return true;
        } catch (Exception $e) {
            error_log('Failed to revoke token: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Gets the authorization URL for the login flow.
     * Helper method for session management.
     *
     * @param array $options Additional options to pass to the authorization URL
     * @return string
     */
    public function buildLoginUrl(array $options = []): string
    {
        if (isset($options['redirect_uri']) && is_string($options['redirect_uri']) && $options['redirect_uri'] !== '') {
            $this->client->setRedirectUri($options['redirect_uri']);
        }

        // Scopes are already set in constructor (openid email profile)
        // Add calendar scope if specified
        if (isset($options['calendar']) && $options['calendar']) {
            $this->client->addScope('https://www.googleapis.com/auth/calendar.events');
        }
        
        if (isset($options['state']) && is_string($options['state']) && $options['state'] !== '') {
            $this->client->setState($options['state']);
        } else {
            $this->client->setState(bin2hex(random_bytes(16)));
        }
        
        if (isset($options['access_type'])) {
            $this->client->setAccessType($options['access_type']);
        }

        if (isset($options['prompt']) && is_string($options['prompt']) && $options['prompt'] !== '') {
            $this->client->setPrompt($options['prompt']);
        }

        if (isset($options['include_granted_scopes'])) {
            $this->client->setIncludeGrantedScopes((bool) $options['include_granted_scopes']);
        }
        
        if (isset($options['approval_prompt'])) {
            $this->client->setApprovalPrompt($options['approval_prompt']);
        }

        return $this->client->createAuthUrl();
    }

    /**
     * Validates the CSRF state parameter (optional security measure).
     *
     * @param string $state The state parameter from the callback
     * @param string $sessionState The state stored in session
     * @return bool True if states match
     */
    public static function validateState(string $state, string $sessionState): bool
    {
        return $state === $sessionState;
    }
}
