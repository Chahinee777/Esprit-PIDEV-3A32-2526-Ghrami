<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\SmartFeedbackService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/smart-feedback')]
final class SmartFeedbackApiController extends AbstractController
{
    public function __construct(
        private readonly SmartFeedbackService $feedbackService,
    ) {}

    /**
     * Get AI-powered feedback on a student's answer.
     *
     * POST /api/smart-feedback/analyze
     * Body: {
     *   "question": "What is Docker?",
     *   "answer": "A container platform",
     *   "context": "Learning Docker - Lesson 1",
     *   "expectedAnswer": "Docker is a containerization platform..."  (optional)
     * }
     */
    #[Route('/analyze', name: 'api_smart_feedback_analyze', methods: ['POST'])]
    public function analyze(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $data = json_decode($request->getContent(), true);

            $question = $data['question'] ?? '';
            $answer = $data['answer'] ?? '';
            $context = $data['context'] ?? null;
            $expectedAnswer = $data['expectedAnswer'] ?? null;
            $className = $data['className'] ?? null;
            $category = $data['category'] ?? null;

            if (empty($question) || empty($answer)) {
                return $this->json(['ok' => false, 'error' => 'Question and answer required'], Response::HTTP_BAD_REQUEST);
            }

            // Enhance context with class details if provided
            if ($className) {
                $classInfo = $className . ($category ? " ($category)" : "");
                $context = $context ? $context . " | Class: $classInfo" : "Class: $classInfo";
            }

            $feedback = $this->feedbackService->generateFeedback(
                $question,
                $answer,
                $context,
                $expectedAnswer
            );

            return $this->json([
                'ok' => true,
                'feedback' => $feedback,
            ]);
        } catch (\Exception $e) {
            return $this->json(
                ['ok' => false, 'error' => $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Get feedback for multiple answers (quiz submission).
     *
     * POST /api/smart-feedback/batch
     * Body: {
     *   "answers": [
     *     { "question": "Q1", "answer": "A1", "expectedAnswer": "..." },
     *     { "question": "Q2", "answer": "A2", "expectedAnswer": "..." }
     *   ],
     *   "context": "Course: Docker Basics"
     * }
     */
    #[Route('/batch', name: 'api_smart_feedback_batch', methods: ['POST'])]
    public function batch(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $data = json_decode($request->getContent(), true);
            $answers = $data['answers'] ?? [];
            $context = $data['context'] ?? null;

            if (empty($answers)) {
                return $this->json(['ok' => false, 'error' => 'No answers provided'], Response::HTTP_BAD_REQUEST);
            }

            $feedbacks = $this->feedbackService->generateBatchFeedback($answers, $context);

            // Calculate overall score
            $totalScore = array_sum(array_map(fn($fb) => $fb['score'], $feedbacks)) / count($feedbacks);

            return $this->json([
                'ok' => true,
                'feedbacks' => $feedbacks,
                'totalScore' => (int) $totalScore,
                'passed' => $totalScore >= 70,
            ]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
