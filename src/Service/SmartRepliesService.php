<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generate smart reply suggestions using Groq LLaMA
 */
class SmartRepliesService
{
    private HttpClientInterface $httpClient;
    private string $groqApiKey;
    private string $groqModel;

    public function __construct(HttpClientInterface $httpClient, string $groqApiKey, string $groqModel = 'llama-3.1-8b-instant')
    {
        $this->httpClient = $httpClient;
        $this->groqApiKey = $groqApiKey;
        $this->groqModel = $groqModel;
    }

    /**
     * Generate smart reply suggestions based on the last message received
     * Returns 3 contextual reply options
     */
    public function generateReplies(string $lastMessage): array
    {
        if (empty($this->groqApiKey) || empty($lastMessage)) {
            return [];
        }

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->groqModel,
                    'temperature' => 0.7,
                    'max_tokens' => 150,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un expert en communication. Génère exactement 3 réponses courtes (max 12 mots chacune) et naturelles en français à un message reçu. Format: une réponse par ligne, sans numéros ni tirets. Les réponses doivent être variées et appropriées.',
                        ],
                        [
                            'role' => 'user',
                            'content' => 'Message reçu: "' . $lastMessage . '"\n\nGénère 3 réponses rapides et naturelles:',
                        ],
                    ],
                ],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                error_log('Groq smart replies error: ' . $response->getStatusCode());
                return [];
            }

            $data = $response->toArray(false);
            $content = trim((string) ($data['choices'][0]['message']['content'] ?? ''));

            if (empty($content)) {
                return [];
            }

            // Parse the response into individual replies
            $lines = array_filter(array_map('trim', explode("\n", $content)));
            $replies = [];

            foreach ($lines as $line) {
                // Remove numbering and bullets if present
                $line = preg_replace('/^[\d\.\-\*\s]+/', '', $line);
                $line = trim($line);

                if (!empty($line) && strlen($line) <= 100) {
                    $replies[] = $line;
                }

                if (count($replies) >= 3) {
                    break;
                }
            }

            return $replies;
        } catch (\Throwable $e) {
            error_log('SmartRepliesService error: ' . $e->getMessage());
            return [];
        }
    }
}
