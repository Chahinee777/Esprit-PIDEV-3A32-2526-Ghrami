<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Content Moderation Service using Groq.
 * 
 * Detects inappropriate content (swearing, harassment, etc.) using AI.
 * Returns verdict on whether content is safe to post.
 */
final class ContentModerationService
{
    private const GROQ_MODEL = 'llama-3.1-8b-instant';
    private const GROQ_URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey,
    ) {}

    /**
     * Check if content is appropriate.
     * 
     * @return array {
     *   'approved': bool,
     *   'reason': string,
     *   'severity': 'safe'|'warning'|'blocked',
     *   'flagged_words': string[]
     * }
     */
    public function checkContent(string $content, string $type = 'post'): array
    {
        if (empty($this->groqApiKey)) {
            return $this->fallbackModeration($content);
        }

        try {
            $prompt = sprintf(
                "You are a content moderator for a hobby community platform.\n\n" .
                "Analyze this %s for inappropriate content (swearing, harassment, hate speech, spam, etc.)\n\n" .
                "Content: \"%s\"\n\n" .
                "Respond in JSON format ONLY:\n" .
                "{\n" .
                '  "approved": true|false,' . "\n" .
                '  "severity": "safe|warning|blocked",' . "\n" .
                '  "reason": "brief explanation",' . "\n" .
                '  "flagged_words": [list of problematic words]' . "\n" .
                "}\n\n" .
                "Be reasonable - mild language is OK for adults, but avoid hate/violence.",
                $type,
                substr($content, 0, 500)
            );

            $response = $this->queryGroq($prompt);
            
            // Parse JSON response
            $result = json_decode($response, true);
            if (is_array($result) && isset($result['approved'])) {
                return [
                    'approved' => (bool) $result['approved'],
                    'reason' => $result['reason'] ?? 'Content review',
                    'severity' => $result['severity'] ?? 'safe',
                    'flagged_words' => $result['flagged_words'] ?? [],
                ];
            }

            // Fallback if parsing fails
            return $this->fallbackModeration($content);
        } catch (\Exception $e) {
            error_log('ContentModerationService error: ' . $e->getMessage());
            return $this->fallbackModeration($content);
        }
    }

    /**
     * Fallback moderation (rules-based).
     */
    private function fallbackModeration(string $content): array
    {
        $badWords = [
            'damn', 'hell', 'crap', 'piss', 'ass', 'bitch', 'bastard',
            'fuck', 'shit', 'dick', 'cock', 'pussy', 'whore', 'slut',
            'nigger', 'faggot', 'retard', 'spastic',
            'kill', 'murder', 'rape', 'hate speech', 'terrorist',
        ];

        $lowerContent = strtolower($content);
        $flagged = [];

        foreach ($badWords as $word) {
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $content)) {
                $flagged[] = $word;
            }
        }

        if (empty($flagged)) {
            return [
                'approved' => true,
                'reason' => 'Content looks good',
                'severity' => 'safe',
                'flagged_words' => [],
            ];
        }

        $severity = count($flagged) >= 3 ? 'blocked' : 'warning';

        return [
            'approved' => $severity !== 'blocked',
            'reason' => count($flagged) . ' inappropriate word(s) detected: ' . implode(', ', $flagged),
            'severity' => $severity,
            'flagged_words' => $flagged,
        ];
    }

    /**
     * Query Groq API.
     */
    private function queryGroq(string $prompt): string
    {
        $response = $this->httpClient->request('POST', self::GROQ_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => self::GROQ_MODEL,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a content moderator. Respond only with valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.3,
                'max_tokens' => 200,
            ],
            'timeout' => 8,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException('Groq API failed');
        }

        $data = $response->toArray();
        return trim($data['choices'][0]['message']['content'] ?? '');
    }
}
