<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for sending emails via Mailjet API.
 * 
 * Setup:
 *   1. Create Mailjet account: https://www.mailjet.com
 *   2. Get API key and secret from dashboard
 *   3. Add to .env:
 *      MAILJET_API_KEY=your_api_key
 *      MAILJET_API_SECRET=your_api_secret
 *      MAILJET_FROM_EMAIL=noreply@ghrami.com
 *      MAILJET_FROM_NAME=Ghrami Platform
 */
class EmailService
{
    private string $apiKey;
    private string $apiSecret;
    private string $fromEmail;
    private string $fromName;
    private const MAILJET_URL = 'https://api.mailjet.com/v3.1/send';

    private HttpClientInterface $httpClient;

    public function __construct()
    {
        $this->apiKey = $_ENV['MAILJET_API_KEY'] ?? '';
        $this->apiSecret = $_ENV['MAILJET_API_SECRET'] ?? '';
        $this->fromEmail = $_ENV['MAILJET_FROM_EMAIL'] ?? 'noreply@ghrami.com';
        $this->fromName = $_ENV['MAILJET_FROM_NAME'] ?? 'Ghrami Platform';
        $this->httpClient = HttpClient::create();
    }

    /**
     * Sends an email via Mailjet.
     *
     * @param string $toEmail Recipient email address
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $htmlContent HTML email body
     * @return bool True if sent successfully
     */
    public function sendEmail(string $toEmail, string $toName, string $subject, string $htmlContent): bool
    {
        if (!$this->isConfigured()) {
            error_log('Mailjet is not configured. Set MAILJET_API_KEY and MAILJET_API_SECRET in .env');
            return false;
        }

        try {
            $payload = [
                'Messages' => [
                    [
                        'From' => [
                            'Email' => $this->fromEmail,
                            'Name' => $this->fromName,
                        ],
                        'To' => [
                            [
                                'Email' => $toEmail,
                                'Name' => $toName,
                            ],
                        ],
                        'Subject' => $subject,
                        'HTMLPart' => $htmlContent,
                    ],
                ],
            ];

            $response = $this->httpClient->request('POST', self::MAILJET_URL, [
                'auth_basic' => [$this->apiKey, $this->apiSecret],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode === 200) {
                return true;
            } else {
                error_log('Mailjet error (status ' . $statusCode . '): ' . $response->getContent(false));
                return false;
            }
        } catch (\Exception $e) {
            error_log('Failed to send email via Mailjet: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sends a password reset email.
     *
     * @param string $toEmail User email
     * @param string $username User name
     * @param string $resetCode The reset code/link
     * @return bool True if sent successfully
     */
    public function sendPasswordResetEmail(string $toEmail, string $username, string $resetCode): bool
    {
        $subject = '🔐 Réinitialisation de votre mot de passe Ghrami';
        $html = $this->buildPasswordResetEmailHTML($username, $resetCode);
        return $this->sendEmail($toEmail, $username, $subject, $html);
    }

    /**
     * Sends a welcome email to a new user.
     *
     * @param string $toEmail User email
     * @param string $username User name
     * @return bool True if sent successfully
     */
    public function sendWelcomeEmail(string $toEmail, string $username): bool
    {
        $subject = '👋 Bienvenue sur Ghrami!';
        $html = $this->buildWelcomeEmailHTML($username);
        return $this->sendEmail($toEmail, $username, $subject, $html);
    }

    /**
     * Sends a test email.
     *
     * @param string $toEmail Recipient email
     * @return bool True if sent successfully
     */
    public function sendTestEmail(string $toEmail): bool
    {
        $subject = '✅ Test Email - Ghrami Email Service';
        $html = '<p>This is a test email from <strong>Ghrami Platform</strong>. Email service is working correctly!</p>';
        return $this->sendEmail($toEmail, 'Test User', $subject, $html);
    }

    /**
     * Returns true if Mailjet is properly configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->apiSecret);
    }

    /**
     * Builds the password reset email HTML.
     *
     * @param string $username User name
     * @param string $resetCode Reset code or link
     * @return string HTML content
     */
    private function buildPasswordResetEmailHTML(string $username, string $resetCode): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f7fa; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                .content { padding: 40px 30px; color: #333; }
                .code-box { background: linear-gradient(135deg, rgba(102,126,234,0.1) 0%, rgba(118,75,162,0.1) 100%); border: 2px solid #667eea; border-radius: 8px; padding: 30px; text-align: center; margin: 25px 0; }
                .code { font-size: 36px; font-weight: bold; letter-spacing: 8px; color: #667eea; font-family: 'Courier New', monospace; }
                .warning { background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 4px; }
                .footer { background-color: #f8f9fa; padding: 25px 30px; text-align: center; color: #6c757d; font-size: 13px; border-top: 1px solid #e9ecef; }
                strong { color: #667eea; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header"><h1>🔐 Réinitialisation de mot de passe</h1></div>
                <div class="content">
                    <p style="font-size:16px;">Bonjour <strong>{$username}</strong>,</p>
                    <p>Vous avez demandé la réinitialisation de votre mot de passe Ghrami.</p>
                    <div class="code-box">
                        <p style="margin:0 0 10px 0;font-size:14px;color:#666;">Votre code de réinitialisation :</p>
                        <div class="code">{$resetCode}</div>
                        <p style="margin:10px 0 0 0;font-size:12px;color:#999;">⏱️ Valide pendant 15 minutes</p>
                    </div>
                    <div class="warning">
                        <strong>⚠️ Important :</strong>
                        <ul style="margin:10px 0 0 0;padding-left:20px;">
                            <li>Ce code expire dans <strong>15 minutes</strong></li>
                            <li>Ne partagez jamais ce code avec personne</li>
                            <li>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email</li>
                        </ul>
                    </div>
                    <p style="margin-top:30px;">Pour réinitialiser votre mot de passe :</p>
                    <ol style="line-height:1.8;">
                        <li>Retournez à l'application Ghrami</li>
                        <li>Entrez ce code dans le champ prévu</li>
                        <li>Choisissez un nouveau mot de passe sécurisé</li>
                    </ol>
                </div>
                <div class="footer">
                    <p style="margin:0 0 10px 0;"><strong>Ghrami Platform</strong> by OPGG</p>
                    <p style="margin:0;font-size:12px;">Plateforme sociale de gestion de hobbies et connectivité</p>
                    <p style="margin:15px 0 0 0;font-size:11px;color:#999;">Cet email a été envoyé automatiquement, merci de ne pas répondre.</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }

    /**
     * Builds the welcome email HTML.
     *
     * @param string $username User name
     * @return string HTML content
     */
    private function buildWelcomeEmailHTML(string $username): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: 'Segoe UI', Arial, sans-serif; background-color: #f5f7fa; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 30px; text-align: center; color: white; }
                .header h1 { margin: 0; font-size: 28px; font-weight: bold; }
                .content { padding: 40px 30px; color: #333; }
                .button { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 30px; border-radius: 6px; text-decoration: none; margin: 20px 0; font-weight: bold; }
                .footer { background-color: #f8f9fa; padding: 25px 30px; text-align: center; color: #6c757d; font-size: 13px; border-top: 1px solid #e9ecef; }
                strong { color: #667eea; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header"><h1>👋 Bienvenue sur Ghrami!</h1></div>
                <div class="content">
                    <p style="font-size:16px;">Bonjour <strong>{$username}</strong>,</p>
                    <p>Merci de vous être inscrit sur <strong>Ghrami</strong>, votre plateforme communautaire pour partager vos hobbies et connecter avec d'autres passionnés!</p>
                    <h3 style="color: #667eea;">Ce que vous pouvez faire maintenant :</h3>
                    <ul style="line-height: 1.8;">
                        <li>🎮 Découvrez les VR Rooms et explorez des espaces virtuels</li>
                        <li>🏫 Parcourez les classes et trouvez des instructeurs</li>
                        <li>👥 Connectez-vous avec d'autres utilisateurs partageant vos intérêts</li>
                        <li>📊 Suivez votre progression avec les graphiques d'activité</li>
                        <li>🎯 Gagnez des badges through completing challenges</li>
                    </ul>
                    <a href="http://localhost" class="button">Aller à Ghrami</a>
                </div>
                <div class="footer">
                    <p style="margin:0 0 10px 0;"><strong>Ghrami Platform</strong> by OPGG</p>
                    <p style="margin:0;font-size:12px;">Plateforme sociale de gestion de hobbies et connectivité</p>
                </div>
            </div>
        </body>
        </html>
        HTML;
    }
}
