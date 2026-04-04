<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class HobbyCoachService
{
    private string $groqApiKey;
    private string $groqModel;
    private HttpClientInterface $httpClient;

    public function __construct(
        ParameterBagInterface $params,
        HttpClientInterface $httpClient
    ) {
        $this->groqApiKey = $params->get('groq_api_key');
        $this->groqModel = $params->get('groq_text_model') ?? 'llama-3.1-8b-instant';
        $this->httpClient = $httpClient;
    }

    /**
     * Chat with the hobby coach - single turn conversation
     */
    public function chat(string $userMessage, string $systemContext): ?string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->groqModel,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemContext
                        ],
                        [
                            'role' => 'user',
                            'content' => $userMessage
                        ]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 300,
                ],
                'timeout' => 15,
            ]);

            $data = $response->toArray();
            
            if (isset($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
