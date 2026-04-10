<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class HobbyCoachService
{
    private string $groqApiKey;
    private string $groqModel;
    private HttpClientInterface $httpClient;

    public function __construct(
        string $groqApiKey,
        string $groqModel,
        HttpClientInterface $httpClient
    ) {
        $this->groqApiKey = $groqApiKey;
        $this->groqModel = $groqModel;
        $this->httpClient = $httpClient;
    }

    /**
     * Chat with the hobby coach - single turn conversation
     */
    public function chat(string $userMessage, string $systemContext): ?string
    {
        try {
            if (empty($this->groqApiKey)) {
                return $this->getFallbackResponse($userMessage);
            }

            $response = $this->httpClient->request(
                'POST',
                'https://api.groq.com/openai/v1/chat/completions',
                [
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
                ]
            );

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                error_log("Groq API error (Status {$statusCode}): " . $response->getContent(false));
                return $this->getFallbackResponse($userMessage);
            }

            $data = $response->toArray();

            if (isset($data['choices'][0]['message']['content'])) {
                return trim($data['choices'][0]['message']['content']);
            }

            error_log("Groq API: No content in response. Data: " . json_encode($data));
            return $this->getFallbackResponse($userMessage);
        } catch (\Throwable $e) {
            error_log("Groq API exception: " . $e->getMessage());
            return $this->getFallbackResponse($userMessage);
        }
    }

    /**
     * Fallback response when Groq API is unavailable
     */
    private function getFallbackResponse(string $userMessage): string
    {
        // Basic fallback responses
        $lowerMessage = strtolower($userMessage);

        if (strpos($lowerMessage, 'motivat') !== false || strpos($lowerMessage, 'encour') !== false) {
            return "💪 Keep pushing! Consistency is the key to mastery. Every hour you invest in your hobby brings you closer to your goals. You've got this!";
        }

        if (strpos($lowerMessage, 'tip') !== false || strpos($lowerMessage, 'advice') !== false) {
            return "💡 Here's a tip: Focus on progress, not perfection. Break your learning into smaller, manageable goals and celebrate each milestone. Steady progress beats sporadic effort!";
        }

        if (strpos($lowerMessage, 'stuck') !== false || strpos($lowerMessage, 'help') !== false || strpos($lowerMessage, 'difficult') !== false) {
            return "🎯 Feeling stuck is part of the learning journey! Try breaking the problem into smaller steps, take a short break, or explore a different approach. Sometimes a fresh perspective is all you need.";
        }

        if (strpos($lowerMessage, 'congratulat') !== false || strpos($lowerMessage, 'achieved') !== false) {
            return "🎉 That's awesome! Celebrate this win—you've earned it! Use this momentum to set your next challenge. You're building amazing habits!";
        }

        // Default fallback
        return "🎓 Great question! Keep exploring and practicing consistently. Every moment dedicated to your hobby is a step forward. What else would you like to know?";
    }
}

