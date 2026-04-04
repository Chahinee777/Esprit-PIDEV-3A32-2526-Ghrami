<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiContentService
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function completePostText(string $currentText): string
    {
        $apiKey = trim((string) ($_ENV['GROQ_API_KEY'] ?? ''));
        $model = trim((string) ($_ENV['GROQ_TEXT_MODEL'] ?? 'llama-3.1-8b-instant'));

        if ($apiKey === '') {
            throw new \RuntimeException('AI text completion is not configured. Set GROQ_API_KEY.');
        }

        $prompt = mb_substr(trim($currentText), 0, 3000);
        if ($prompt === '') {
            throw new \RuntimeException('Post text is empty.');
        }

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model,
                'temperature' => 0.7,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Complete the user social post naturally, positively, and concisely. Return only the completion text without quotes.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => 120,
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('AI text completion request failed.');
        }

        $data = $response->toArray(false);
        $completion = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($completion === '') {
            throw new \RuntimeException('AI did not return text.');
        }

        return $completion;
    }

    /**
     * @return array{bytes: string, mime: string, extension: string}
     */
    public function generateImage(string $prompt): array
    {
        $token = trim((string) ($_ENV['HUGGINGFACE_API_TOKEN'] ?? ''));
        $model = trim((string) ($_ENV['HUGGINGFACE_IMAGE_MODEL'] ?? 'black-forest-labs/FLUX.1-schnell'));

        if ($token === '') {
            throw new \RuntimeException('AI image generation is not configured. Set HUGGINGFACE_API_TOKEN.');
        }

        $cleanPrompt = mb_substr(trim($prompt), 0, 1200);
        if ($cleanPrompt === '') {
            throw new \RuntimeException('Prompt is empty.');
        }

        $response = $this->httpClient->request('POST', 'https://api-inference.huggingface.co/models/' . $model, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'inputs' => $cleanPrompt,
                'options' => [
                    'wait_for_model' => true,
                ],
            ],
            'timeout' => 90,
        ]);

        $status = $response->getStatusCode();
        $contentType = strtolower((string) $response->getHeaders(false)['content-type'][0] ?? '');
        $content = $response->getContent(false);

        if ($status >= 400) {
            throw new \RuntimeException('AI image generation request failed.');
        }

        if (str_contains($contentType, 'application/json')) {
            $payload = json_decode($content, true);
            $err = is_array($payload) ? (string) ($payload['error'] ?? 'Unknown image generation error.') : 'Image generation error.';
            throw new \RuntimeException($err);
        }

        $mime = $this->detectMime($contentType, $content);
        $extension = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');

        return [
            'bytes' => $content,
            'mime' => $mime,
            'extension' => $extension,
        ];
    }

    private function detectMime(string $contentType, string $bytes): string
    {
        if (str_contains($contentType, 'image/png')) {
            return 'image/png';
        }
        if (str_contains($contentType, 'image/webp')) {
            return 'image/webp';
        }
        if (str_contains($contentType, 'image/jpeg') || str_contains($contentType, 'image/jpg')) {
            return 'image/jpeg';
        }

        if (str_starts_with($bytes, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return 'image/jpeg';
    }
}
