<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class WeeklyDigestAiService
{
    private const MODEL = 'claude-haiku-4-5-20251001';
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const SYSTEM_PROMPT = 'You are a warm personal coach. Write concise weekly digests in French. Be encouraging, specific, and action-oriented. No markdown, no bullet symbols — plain text only.';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $anthropicApiKey,
    ) {
    }

    public function generateDigest(array $context): string
    {
        if ($this->anthropicApiKey === '') {
            return $this->buildFallbackDigest($context);
        }

        $contextJson = (string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $userPrompt = <<<PROMPT
Here is the user's activity data for the past week:
{$contextJson}

Generate a digest with exactly:
- 2 sentences summarizing their week (warm, personal tone)
- Their #1 highlight
- 3 concrete actions for next week (numbered 1. 2. 3.)
- 1 sentence about their next badge goal

Keep it under 300 words.
PROMPT;

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'x-api-key' => $this->anthropicApiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ],
                'json' => [
                    'model' => self::MODEL,
                    'max_tokens' => 400,
                    'temperature' => 0.7,
                    'system' => self::SYSTEM_PROMPT,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $userPrompt,
                        ],
                    ],
                ],
                'timeout' => 20,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Anthropic API request failed with status ' . $response->getStatusCode());
            }

            $data = $response->toArray(false);
            $text = '';

            foreach ($data['content'] ?? [] as $block) {
                if (($block['type'] ?? null) === 'text') {
                    $text .= ($block['text'] ?? '') . "\n";
                }
            }

            $digest = trim($text);

            if ($digest === '') {
                throw new \RuntimeException('Anthropic API returned an empty digest.');
            }

            return $digest;
        } catch (\Throwable) {
            return $this->buildFallbackDigest($context);
        }
    }

    private function buildFallbackDigest(array $context): string
    {
        $name = $context['user_name'] ?? 'ami';
        $posts = (int) ($context['posts']['published'] ?? 0);
        $sessions = (int) ($context['hobbies']['sessions'] ?? 0);
        $minutes = (int) ($context['hobbies']['total_minutes'] ?? 0);
        $meetings = (int) ($context['meetings']['count'] ?? 0);
        $completed = (int) ($context['classes']['completed'] ?? 0);
        $highlight = $context['posts']['top_post_title'] ?: ($context['hobbies']['top_hobby'] ?: 'ta régularité cette semaine');
        $nextBadge = $context['badges']['next_badge'] ?: 'ton prochain badge';
        $missing = max(0, (int) ($context['badges']['points_missing'] ?? 0));

        return trim(implode("\n", [
            sprintf("%s, ta semaine a montré une belle dynamique avec %d publication(s), %d session(s) hobby et %d rencontre(s).", $name, $posts, $sessions, $meetings),
            sprintf("Tu avances aussi avec %d minute(s) investies dans tes hobbies et %d classe(s) terminée(s), ce qui crée une progression concrète.", $minutes, $completed),
            'Ton highlight #1 : ' . $highlight,
            '1. Reprends ton activité la plus forte dès le début de semaine pour garder l’élan.',
            '2. Planifie une session courte et précise sur un hobby ou une classe encore en cours.',
            '3. Partage une mise à jour visible pour maintenir ton engagement et tes interactions.',
            sprintf("Prochain objectif badge : %s, il te reste environ %d point(s) ou action(s) de progression.", $nextBadge, $missing),
        ]));
    }
}
