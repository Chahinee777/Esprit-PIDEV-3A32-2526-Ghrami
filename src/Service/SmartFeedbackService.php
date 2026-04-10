<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * SmartFeedbackService – AI-powered personalized feedback for student answers.
 * Uses Groq API to analyze answers and provide constructive, educational feedback.
 *
 * Example:
 *   Student: "I think Docker is a database."
 *   AI: "Not quite — Docker is for containers, not databases. Review Lesson 3 about container basics."
 */
class SmartFeedbackService
{
    private const GROQ_MODEL = 'llama-3.1-8b-instant';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $groqApiKey = '',
    ) {}

    /**
     * Generate smart feedback for a student answer.
     *
     * @param string $question The original question asked to the student
     * @param string $studentAnswer The student's response/answer
     * @param string $context Optional: course topic, lesson name, difficulty level, etc.
     * @param string $correctAnswer Optional: the expected/ideal answer for reference
     *
     * @return array {
     *   'isCorrect': bool,
     *   'feedback': string (constructive, encouraging feedback),
     *   'score': int (0-100),
     *   'reasoning': string (brief explanation of the feedback),
     *   'suggestions': string[] (3-5 specific improvement suggestions),
     *   'nextLessonHint': string (what to study next)
     * }
     */
    public function generateFeedback(
        string $question,
        string $studentAnswer,
        ?string $context = null,
        ?string $correctAnswer = null
    ): array {
        if (empty($this->groqApiKey)) {
            return $this->getFallbackFeedback($studentAnswer);
        }

        try {
            $prompt = $this->buildFeedbackPrompt(
                $question,
                $studentAnswer,
                $context,
                $correctAnswer
            );

            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => self::GROQ_MODEL,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are an expert educator providing personalized, constructive feedback to students. ' .
                                         'Be encouraging, specific, and educational. Always point to what to study next.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ]
                    ],
                    'max_tokens' => 400,
                    'temperature' => 0.5,
                ],
                'timeout' => 15,
            ]);

            $data = $response->toArray();

            if (isset($data['error'])) {
                error_log('Groq feedback error: ' . json_encode($data['error']));
                return $this->getFallbackFeedback($studentAnswer);
            }

            $content = $data['choices'][0]['message']['content'] ?? '';
            return $this->parseFeedbackResponse($content);
        } catch (\Exception $e) {
            error_log('SmartFeedbackService exception: ' . $e->getMessage());
            return $this->getFallbackFeedback($studentAnswer);
        }
    }

    /**
     * Build the prompt for Groq to analyze the student answer.
     */
    private function buildFeedbackPrompt(
        string $question,
        string $studentAnswer,
        ?string $context,
        ?string $correctAnswer
    ): string {
        $prompt = "You are evaluating a student's answer to an educational question.\n\n" .
                  "QUESTION: $question\n\n" .
                  "STUDENT'S ANSWER: $studentAnswer\n";

        if ($correctAnswer) {
            $prompt .= "\nEXPECTED/IDEAL ANSWER: $correctAnswer\n";
        }

        if ($context) {
            $prompt .= "\nCONTEXT: $context\n";
        }

        $prompt .= "\nProvide feedback in this EXACT JSON format (no markdown, pure JSON):\n" .
                   "{\n" .
                   "  \"isCorrect\": true/false,\n" .
                   "  \"feedback\": \"Encouragement + constructive comment (1-2 sentences)\",\n" .
                   "  \"score\": 0-100,\n" .
                   "  \"reasoning\": \"Brief explanation of why this is correct/incorrect\",\n" .
                   "  \"suggestions\": [\"Suggestion 1\", \"Suggestion 2\", \"Suggestion 3\"],\n" .
                   "  \"nextLessonHint\": \"What specific topic or lesson to review next\"\n" .
                   "}\n\n" .
                   "Be encouraging! Mistakes are learning opportunities. Focus on growth.";

        return $prompt;
    }

    /**
     * Parse Groq's JSON response.
     */
    private function parseFeedbackResponse(string $content): array
    {
        try {
            // Extract JSON from response (in case there's extra text)
            if (preg_match('/\{[\s\S]*\}/', $content, $matches)) {
                $data = json_decode($matches[0], true);

                return [
                    'isCorrect' => $data['isCorrect'] ?? false,
                    'feedback' => $data['feedback'] ?? 'Good effort! Keep learning.',
                    'score' => (int) ($data['score'] ?? 50),
                    'reasoning' => $data['reasoning'] ?? '',
                    'suggestions' => $data['suggestions'] ?? ['Review the lesson', 'Practice more examples'],
                    'nextLessonHint' => $data['nextLessonHint'] ?? 'Continue with the next section',
                ];
            }
        } catch (\Exception $e) {
            error_log('Feedback parsing error: ' . $e->getMessage());
        }

        return $this->getFallbackFeedback('');
    }

    /**
     * Fallback feedback when Groq is unavailable.
     */
    private function getFallbackFeedback(string $studentAnswer): array
    {
        $isLikelyCorrect = strlen($studentAnswer) > 50 || str_contains(strtolower($studentAnswer), 'because');

        return [
            'isCorrect' => $isLikelyCorrect,
            'feedback' => $isLikelyCorrect
                ? 'Great answer! You demonstrate good understanding.'
                : 'Good effort! Review the lesson and try again.',
            'score' => $isLikelyCorrect ? 75 : 40,
            'reasoning' => 'Feedback system temporarily unavailable.',
            'suggestions' => [
                'Review the lesson material',
                'Practice with similar examples',
                'Ask instructor if confused'
            ],
            'nextLessonHint' => 'Continue to the next topic',
        ];
    }

    /**
     * Batch feedback for multiple answers (for quizzes).
     */
    public function generateBatchFeedback(array $questionAnswerPairs, ?string $context = null): array
    {
        return array_map(
            fn($pair) => $this->generateFeedback(
                $pair['question'],
                $pair['answer'],
                $context,
                $pair['expectedAnswer'] ?? null
            ),
            $questionAnswerPairs
        );
    }
}
