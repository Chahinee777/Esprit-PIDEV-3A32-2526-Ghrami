<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Hobby;
use App\Entity\Progress;
use App\Entity\ProgressLog;
use App\Entity\Milestone;
use App\Service\GroqAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/hobbies')]
final class HobbiesController extends AbstractController
{
    const CATEGORIES = Hobby::ALLOWED_CATEGORIES;

    #[Route('', name: 'app_hobbies_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_auth_login');
        }

        // Get filter and search params
        $category = $request->query->get('category', 'all');
        $search = $request->query->get('search', '');

        // Get hobbies
        $hobbyRepo = $entityManager->getRepository(Hobby::class);
        $qb = $hobbyRepo->createQueryBuilder('h')
            ->where('h.user = :user')
            ->setParameter('user', $user);

        if ($category !== 'all' && $category) {
            $qb->andWhere('h.category = :category')
                ->setParameter('category', $category);
        }

        if ($search) {
            $qb->andWhere('h.name LIKE :search OR h.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $hobbies = $qb->getQuery()->getResult();

        // Calculate stats
        $progressRepo = $entityManager->getRepository(Progress::class);
        $milestoneRepo = $entityManager->getRepository(Milestone::class);

        $totalHours = 0;
        $totalMilestones = 0;
        $achievedMilestones = 0;

        foreach ($hobbies as $hobby) {
            $progress = $progressRepo->findOneBy(['hobby' => $hobby]);
            if ($progress) {
                $totalHours += $progress->hoursSpent ?? 0;
            }

            $milestones = $milestoneRepo->findBy(['hobby' => $hobby]);
            foreach ($milestones as $milestone) {
                $totalMilestones++;
                if ($milestone->isAchieved) {
                    $achievedMilestones++;
                }
            }
        }

        return $this->render('hobbies/index.html.twig', [
            'hobbies' => $hobbies,
            'categories' => self::CATEGORIES,
            'current_category' => $category,
            'search' => $search,
            'total_hobbies' => count($hobbies),
            'total_hours' => round($totalHours, 1),
            'total_milestones' => $totalMilestones,
            'achieved_milestones' => $achievedMilestones,
            'user_id' => $user->id,
        ]);
    }

    #[Route('/create', name: 'app_hobbies_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        if (!$this->isCsrfTokenValid('hobby_create', $request->request->get('_csrf_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
        }

        try {
            $hobby = new Hobby();
            $hobby->user = $user;
            $hobby->name = trim((string) $request->request->get('name', ''));
            $hobby->category = trim((string) $request->request->get('category', ''));

            $description = trim((string) $request->request->get('description', ''));
            $hobby->description = $description !== '' ? $description : null;

            $violations = $validator->validate($hobby);
            if (count($violations) > 0) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Validation failed.',
                    'errors' => $this->normalizeValidationErrors($violations),
                ], 400);
            }

            $entityManager->persist($hobby);
            $entityManager->flush();

            // Create initial progress entry
            $progress = new Progress();
            $progress->hobby = $hobby;
            $progress->hoursSpent = 0;
            $progress->notes = 'Started tracking';
            $entityManager->persist($progress);
            $entityManager->flush();

            return new JsonResponse(['ok' => true, 'message' => '✨ Hobby created successfully!', 'id' => $hobby->id]);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/edit', name: 'app_hobbies_edit', methods: ['POST'])]
    public function edit(Hobby $hobby, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('HOBBY_EDIT', $hobby);

        if (!$this->isCsrfTokenValid('hobby_edit_' . $hobby->id, $request->request->get('_csrf_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
        }

        try {
            $hobby->name = trim((string) $request->request->get('name', ''));
            $hobby->category = trim((string) $request->request->get('category', ''));

            $description = trim((string) $request->request->get('description', ''));
            $hobby->description = $description !== '' ? $description : null;

            $violations = $validator->validate($hobby);
            if (count($violations) > 0) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Validation failed.',
                    'errors' => $this->normalizeValidationErrors($violations),
                ], 400);
            }

            $entityManager->flush();

            return new JsonResponse(['ok' => true, 'message' => 'Hobby updated successfully!']);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/delete', name: 'app_hobbies_delete', methods: ['POST'])]
    public function delete(Hobby $hobby, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('HOBBY_DELETE', $hobby);

        if (!$this->isCsrfTokenValid('hobby_delete_' . $hobby->id, $request->request->get('_csrf_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
        }

        try {
            $entityManager->remove($hobby);
            $entityManager->flush();

            return new JsonResponse(['ok' => true, 'message' => 'Hobby deleted successfully']);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/log-progress', name: 'app_hobbies_log_progress', methods: ['POST'])]
    public function logProgress(Hobby $hobby, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('HOBBY_VIEW', $hobby);

        if (!$this->isCsrfTokenValid('log_progress_' . $hobby->id, $request->request->get('_csrf_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
        }

        try {
            $hoursInput = trim((string) $request->request->get('hours', ''));
            if ($hoursInput === '' || !is_numeric($hoursInput)) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Validation failed.',
                    'errors' => ['hours' => 'Please enter a valid number of hours.'],
                ], 400);
            }

            $hours = (float) $hoursInput;
            $notes = trim((string) $request->request->get('notes', ''));
            $sessionDateInput = trim((string) $request->request->get('session_date', ''));
            $sessionDateImmutable = $sessionDateInput === '' ? new \DateTimeImmutable('today') : \DateTimeImmutable::createFromFormat('Y-m-d', $sessionDateInput);

            if ($sessionDateInput !== '' && !$sessionDateImmutable) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Validation failed.',
                    'errors' => ['session_date' => 'Please provide a valid session date.'],
                ], 400);
            }

            $log = new ProgressLog();
            $log->hobby = $hobby;
            $log->hoursSpent = $hours;
            $log->notes = $notes !== '' ? $notes : null;
            $log->logDate = $sessionDateImmutable;

            $violations = $validator->validate($log);
            if (count($violations) > 0) {
                $errors = $this->normalizeValidationErrors($violations);
                if (isset($errors['hoursSpent']) && !isset($errors['hours'])) {
                    $errors['hours'] = $errors['hoursSpent'];
                    unset($errors['hoursSpent']);
                }
                if (isset($errors['logDate']) && !isset($errors['session_date'])) {
                    $errors['session_date'] = $errors['logDate'];
                    unset($errors['logDate']);
                }

                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Validation failed.',
                    'errors' => $errors,
                ], 400);
            }

            // Update cumulative progress
            $progressRepo = $entityManager->getRepository(Progress::class);
            $progress = $progressRepo->findOneBy(['hobby' => $hobby]);

            if (!$progress) {
                $progress = new Progress();
                $progress->hobby = $hobby;
                $progress->hoursSpent = 0;
                $entityManager->persist($progress);
            }

            $progress->hoursSpent += $hours;
            $progress->notes = $notes !== '' ? $notes : null;

            $entityManager->persist($log);
            $entityManager->flush();

            return new JsonResponse([
                'ok' => true,
                'message' => sprintf('✅ Logged %.1f hrs on %s!', $hours, $sessionDateImmutable->format('M d, Y'))
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/milestone/add', name: 'app_hobbies_milestone_add', methods: ['POST'])]
    public function addMilestone(Hobby $hobby, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        $this->denyAccessUnlessGranted('HOBBY_EDIT', $hobby);

        if (!$this->isCsrfTokenValid('milestone_add_' . $hobby->id, $request->request->get('_csrf_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
        }

        try {
            $title = trim((string) $request->request->get('title', ''));
            $targetDateInput = trim((string) $request->request->get('target_date', ''));

            $targetDate = null;
            if ($targetDateInput !== '') {
                $targetDate = \DateTimeImmutable::createFromFormat('Y-m-d', $targetDateInput);
                if (!$targetDate) {
                    return new JsonResponse([
                        'ok' => false,
                        'error' => 'Validation failed.',
                        'errors' => ['target_date' => 'Please provide a valid target date.'],
                    ], 400);
                }
            }

            $milestone = new Milestone();
            $milestone->hobby = $hobby;
            $milestone->title = $title;
            $milestone->isAchieved = false;
            $milestone->targetDate = $targetDate;

            $violations = $validator->validate($milestone);
            if (count($violations) > 0) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Validation failed.',
                    'errors' => $this->normalizeValidationErrors($violations),
                ], 400);
            }

            $entityManager->persist($milestone);
            $entityManager->flush();

            return new JsonResponse(['ok' => true, 'message' => 'Milestone created!', 'id' => $milestone->id]);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/milestone/{id}/toggle', name: 'app_hobbies_milestone_toggle', methods: ['POST'])]
    public function toggleMilestone(Milestone $milestone, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('HOBBY_EDIT', $milestone->hobby);

        if (!$this->isCsrfTokenValid('milestone_toggle_' . $milestone->id, $request->request->get('_csrf_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
        }

        $milestone->isAchieved = !$milestone->isAchieved;
        $entityManager->flush();

        return new JsonResponse(['ok' => true, 'achieved' => $milestone->isAchieved]);
    }

    #[Route('/milestone/{id}/delete', name: 'app_hobbies_milestone_delete', methods: ['POST'])]
    public function deleteMilestone(Milestone $milestone, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('HOBBY_EDIT', $milestone->hobby);

        if (!$this->isCsrfTokenValid('milestone_delete_' . $milestone->id, $request->request->get('_csrf_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
        }

        $entityManager->remove($milestone);
        $entityManager->flush();

        return new JsonResponse(['ok' => true, 'message' => 'Milestone deleted']);
    }

    #[Route('/{id}/details', name: 'app_hobbies_details', methods: ['GET'])]
    public function details(Hobby $hobby, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('HOBBY_VIEW', $hobby);

        $progressRepo = $entityManager->getRepository(Progress::class);
        $logRepo = $entityManager->getRepository(ProgressLog::class);
        $milestoneRepo = $entityManager->getRepository(Milestone::class);

        $progress = $progressRepo->findOneBy(['hobby' => $hobby]);
        $logs = $logRepo->findBy(['hobby' => $hobby], ['logDate' => 'DESC']);
        $milestones = $milestoneRepo->findBy(['hobby' => $hobby]);

        $logsByDate = [];
        $dayHours = [];
        foreach ($logs as $log) {
            $date = $log->logDate?->format('Y-m-d') ?? 'unknown';
            if (!isset($logsByDate[$date])) {
                $logsByDate[$date] = ['total' => 0, 'logs' => []];
            }
            $logsByDate[$date]['total'] += $log->hoursSpent;
            $logsByDate[$date]['logs'][] = [
                'hours' => $log->hoursSpent,
                'notes' => $log->notes,
                'date' => $log->logDate?->format('M d, Y')
            ];

            // Group by date for calendar
            if ($log->logDate) {
                $dayHours[$log->logDate->format('Y-m-d')] = ($dayHours[$log->logDate->format('Y-m-d')] ?? 0) + $log->hoursSpent;
            }
        }

        // Calculate streak
        $today = new \DateTime();
        $current = clone $today;
        $streak = 0;
        while ($current->format('Y-m-d') >= (new \DateTime())->modify('-365 days')->format('Y-m-d')) {
            if (isset($dayHours[$current->format('Y-m-d')]) && $dayHours[$current->format('Y-m-d')] > 0) {
                $streak++;
                $current->modify('-1 day');
            } else {
                break;
            }
        }

        return new JsonResponse([
            'hobby' => [
                'id' => $hobby->id,
                'name' => $hobby->name,
                'category' => $hobby->category,
                'description' => $hobby->description,
            ],
            'progress' => [
                'total_hours' => $progress->hoursSpent,
            ],
            'logs_by_date' => $logsByDate,
            'day_hours' => $dayHours,
            'recent_logs' => array_map(function($log) {
                return [
                    'hours' => $log->hoursSpent,
                    'notes' => $log->notes ?? '',
                    'date' => $log->logDate?->format('M d, Y') ?? 'Unknown date',
                ];
            }, array_slice($logs, 0, 5)),
            'milestones' => array_map(function($m) {
                return [
                    'id' => $m->id,
                    'title' => $m->title,
                    'targetDate' => $m->targetDate?->format('Y-m-d'),
                    'isAchieved' => $m->isAchieved,
                    'isOverdue' => !$m->isAchieved && $m->targetDate && $m->targetDate < new \DateTime(),
                ];
            }, $milestones),
            'streak' => $streak,
            'csrf_tokens' => [
                'log_progress' => $this->container->get('security.csrf.token_manager')->getToken('log_progress_' . $hobby->id)->getValue(),
                'milestone_toggle' => array_map(function($m) {
                    return [
                        'id' => $m->id,
                        'token' => $this->container->get('security.csrf.token_manager')->getToken('milestone_toggle_' . $m->id)->getValue(),
                        'delete_token' => $this->container->get('security.csrf.token_manager')->getToken('milestone_delete_' . $m->id)->getValue(),
                    ];
                }, $milestones),
            ],
        ]);
    }

    #[Route('/analytics', name: 'app_hobbies_analytics', methods: ['GET'])]
    public function analytics(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_auth_login');
        }

        $hobbyRepo = $entityManager->getRepository(Hobby::class);
        $hobbies = $hobbyRepo->findBy(['user' => $user]);

        $progressRepo = $entityManager->getRepository(Progress::class);
        $logRepo = $entityManager->getRepository(ProgressLog::class);

        // Calculate analytics
        $hobbyStats = [];
        $categoryStats = [];
        $dayOfWeekStats = [0, 0, 0, 0, 0, 0, 0]; // Sun-Sat
        $totalHours = 0;

        foreach ($hobbies as $hobby) {
            // Use Progress entity for accurate cumulative hours (same as index page)
            $progress = $progressRepo->findOneBy(['hobby' => $hobby]);
            $hobbyHours = $progress->hoursSpent ?? 0;
            
            // Still get logs for day-of-week stats
            $logs = $logRepo->findBy(['hobby' => $hobby]);
            foreach ($logs as $log) {
                if ($log->logDate) {
                    $dow = $log->logDate->format('w');
                    $dayOfWeekStats[$dow] += $log->hoursSpent;
                }
            }

            $totalHours += $hobbyHours;
            $hobbyStats[] = [
                'id' => $hobby->id,
                'name' => $hobby->name,
                'hours' => $hobbyHours,
                'category' => $hobby->category,
            ];

            // Category breakdown
            if (!isset($categoryStats[$hobby->category])) {
                $categoryStats[$hobby->category] = 0;
            }
            $categoryStats[$hobby->category] += $hobbyHours;
        }

        // Sort hobbies by hours
        usort($hobbyStats, fn($a, $b) => $b['hours'] <=> $a['hours']);

        return $this->render('hobbies/analytics.html.twig', [
            'hobby_stats' => $hobbyStats,
            'category_stats' => $categoryStats,
            'day_of_week_stats' => $dayOfWeekStats,
            'total_hours' => $totalHours,
            'day_labels' => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            'user_id' => $user->id,
        ]);
    }

    #[Route('/{id}/milestone/edit', name: 'app_hobbies_milestone_edit', methods: ['POST'])]
    public function editMilestone(Milestone $milestone, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        $this->denyAccessUnlessGranted('HOBBY_EDIT', $milestone->hobby);

        if (!$this->isCsrfTokenValid('milestone_edit_' . $milestone->id, $request->request->get('_csrf_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'Invalid CSRF token'], 400);
        }

        try {
            $title = trim((string) $request->request->get('title', ''));
            $targetDateInput = trim((string) $request->request->get('target_date', ''));

            $targetDate = null;
            if ($targetDateInput !== '') {
                $targetDate = \DateTimeImmutable::createFromFormat('Y-m-d', $targetDateInput);
                if (!$targetDate) {
                    return new JsonResponse([
                        'ok' => false,
                        'error' => 'Validation failed.',
                        'errors' => ['target_date' => 'Please provide a valid target date.'],
                    ], 400);
                }
            }

            $milestone->title = $title;
            $milestone->targetDate = $targetDate;

            $violations = $validator->validate($milestone);
            if (count($violations) > 0) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Validation failed.',
                    'errors' => $this->normalizeValidationErrors($violations),
                ], 400);
            }

            $entityManager->flush();

            return new JsonResponse(['ok' => true, 'message' => 'Milestone updated!']);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/coach/tip', name: 'app_hobbies_coach_tip', methods: ['GET'])]
    public function getCoachTip(Hobby $hobby, \App\Service\HobbyCoachService $coachService, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('HOBBY_VIEW', $hobby);

        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        // Build system context with hobby data
        $systemContext = $this->buildCoachSystemContext($hobby, $entityManager);

        // Get greeting/initial tip
        $message = "Hello! Greet me warmly and in 2-3 sentences give me a personalized tip or motivation based on my hobby data. Keep it under 80 words.";

        $tip = $coachService->chat($message, $systemContext);

        if (!$tip) {
            return new JsonResponse(['ok' => false, 'error' => 'Could not get coaching advice']);
        }

        return new JsonResponse(['ok' => true, 'tip' => $tip]);
    }

    private function buildCoachSystemContext(Hobby $hobby, EntityManagerInterface $entityManager): string
    {
        $context = "You are an AI hobby coach called 'Hobby Coach'. YOUR ROLE: Help users improve specifically in the hobby they track with you. ";
        $context .= "IMPORTANT BOUNDARY: You can ONLY provide coaching for the specific hobby below. ";
        $context .= "If the user asks about something unrelated to their hobby (sports teams, general knowledge, politics, etc.), politely decline and refocus on their hobby.\n\n";
        $context .= "Hobby you're coaching for:\n";

        $totalHours = 0;
        if (count($hobby->progress) > 0) {
            foreach ($hobby->progress as $progress) {
                $totalHours += $progress->hoursSpent;
            }
        }

        $milestones = $hobby->milestones ?? [];
        $achieved = 0;
        foreach ($milestones as $m) {
            if ($m->isAchieved) {
                $achieved++;
            }
        }

        $context .= sprintf(
            "• %s (%s): %.1f hrs logged, %d/%d milestones achieved\n",
            $hobby->name,
            $hobby->category,
            $totalHours,
            $achieved,
            count($milestones)
        );

        $context .= "\n🚫 STRICT RULE: Only answer questions about " . $hobby->name . ". Decline politely for any off-topic requests.\n";
        $context .= "Be encouraging, realistic, and actionable in all your responses about their hobby.";
        return $context;
    }

    #[Route('/coach/chat', name: 'app_hobbies_coach_chat', methods: ['POST'])]
    public function coachChat(
        Request $request, 
        EntityManagerInterface $entityManager,
        \App\Service\HobbyCoachService $coachService
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $message = $data['message'] ?? '';

        if (empty($message)) {
            return new JsonResponse(['ok' => false, 'error' => 'Message is required'], 400);
        }

        // Build context with ALL user's hobbies
        $systemContext = "You are an AI hobby coach called 'Hobby Coach'. YOUR ROLE: Help users improve in the hobbies they track with you. ";
        $systemContext .= "CRITICAL BOUNDARY: You can ONLY provide coaching for the hobbies listed below. ";
        $systemContext .= "If the user asks about something unrelated to their hobbies (sports teams, celebrities, general knowledge, politics, etc.), politely decline and ask them to focus on their hobbies.\n\n";
        $systemContext .= "User's tracked hobbies:\n";

        $hobbyRepo = $entityManager->getRepository(Hobby::class);
        $hobbies = $hobbyRepo->findBy(['user' => $user]);

        if (empty($hobbies)) {
            $systemContext .= "• User has no hobbies tracked yet.\n";
            $systemContext .= "If they start describing a hobby, enthusiastically encourage them to track it.\n";
        } else {
            $progressRepo = $entityManager->getRepository(Progress::class);
            $milestoneRepo = $entityManager->getRepository(Milestone::class);

            foreach ($hobbies as $hobby) {
                $progress = $progressRepo->findOneBy(['hobby' => $hobby]);
                $hours = $progress->hoursSpent ?? 0;
                $milestones = $milestoneRepo->findBy(['hobby' => $hobby]);
                $achieved = 0;
                foreach ($milestones as $m) {
                    if ($m->isAchieved) {
                        $achieved++;
                    }
                }
                $systemContext .= sprintf(
                    "• %s (%s): %.1f hrs logged, %d/%d milestones achieved\n",
                    $hobby->name,
                    $hobby->category,
                    $hours,
                    $achieved,
                    count($milestones)
                );
            }
        }

        $systemContext .= "\n🚫 STRICT RULE: Only discuss the hobbies above. If asked about unrelated topics, respond with: 'I'm your hobby coach! I can only help with [hobby names]. Let's focus on improving your skills there!'\n";
        $systemContext .= "Be encouraging, realistic, and actionable in all responses about their hobbies.";

        try {
            $response = $coachService->chat($message, $systemContext);

            if ($response === null) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Could not get a response from the AI coach. Please check your Groq API key.'
                ], 400);
            }

            return new JsonResponse([
                'ok' => true,
                'response' => $response
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage()
            ], 400);
        }
    }

    private function normalizeValidationErrors(ConstraintViolationListInterface $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $field = $violation->getPropertyPath();
            if (!isset($errors[$field])) {
                $errors[$field] = $violation->getMessage();
            }
        }

        return $errors;
    }

    #[Route('/{id}/calendar/download', name: 'app_hobbies_calendar_download', methods: ['GET'])]
    public function downloadCalendar(Hobby $hobby, \App\Service\CalendarService $calendarService): Response
    {
        $this->denyAccessUnlessGranted('HOBBY_VIEW', $hobby);

        // Generate single hobby iCal feed
        $ical = $calendarService->generateHobbyCalendarFeed([[
            'hobby_id' => $hobby->id,
            'hobby_name' => $hobby->name,
            'hobby_category' => $hobby->category,
            'log_date' => new \DateTimeImmutable(),
            'hours_spent' => 0,
            'notes' => '',
        ]], $hobby->name);

        return new Response($ical, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . str_replace(' ', '_', $hobby->name) . '_hobby.ics"',
        ]);
    }

    #[Route('/calendar/google-link', name: 'app_hobbies_calendar_google_link', methods: ['POST'])]
    public function getGoogleCalendarLink(
        Request $request,
        \App\Service\CalendarService $calendarService,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
        }

        try {
            $hobbyId = (int)$request->request->get('hobby_id');
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $request->request->get('date', date('Y-m-d')));
            $hours = (float)$request->request->get('hours', 1);

            $hobby = $entityManager->getRepository(Hobby::class)->find($hobbyId);
            if (!$hobby || $hobby->user->id !== $user->id) {
                return new JsonResponse(['ok' => false, 'error' => 'Hobby not found'], 404);
            }

            $link = $calendarService->generateGoogleCalendarLink(
                $hobby->name,
                $date,
                $hours,
                "Category: {$hobby->category}"
            );

            return new JsonResponse(['ok' => true, 'link' => $link]);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/weather/for-activity', name: 'app_hobbies_weather_activity', methods: ['GET'])]
    public function getWeatherForActivity(
        Request $request,
        \App\Service\WeatherService $weatherService
    ): JsonResponse {
        try {
            $latitude = (float)$request->query->get('lat', 48.8566); // Default: Paris
            $longitude = (float)$request->query->get('lon', 2.3522);
            $date = $request->query->get('date');

            if ($date) {
                $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
                if (!$dateObj) {
                    return new JsonResponse([
                        'ok' => false,
                        'error' => 'Invalid date format. Expected YYYY-MM-DD',
                    ], 400);
                }
            } else {
                $dateObj = new \DateTimeImmutable('today');
            }

            $today = new \DateTimeImmutable('today');
            $weather = null;

            // Open-Meteo archive endpoint does not support future dates.
            if ($dateObj > $today) {
                $daysAhead = (int)$today->diff($dateObj)->days + 1;
                $forecast = $weatherService->getWeatherForecast($latitude, $longitude, min($daysAhead, 16));

                if (is_array($forecast)) {
                    $targetDate = $dateObj->format('Y-m-d');
                    foreach ($forecast as $item) {
                        if (($item['date'] ?? null) === $targetDate) {
                            $weather = $item;
                            break;
                        }
                    }
                }
            } else {
                $weather = $weatherService->getWeather($latitude, $longitude, $dateObj);
            }

            if (!$weather) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Could not fetch weather data',
                ], 400);
            }

            return new JsonResponse([
                'ok' => true,
                'weather' => $weather,
                'tip' => $weather['good_for_activity'] 
                    ? "✅ Great day for outdoor activities!" 
                    : "⚠️ Weather might not be ideal, consider indoor activities.",
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/weather/forecast', name: 'app_hobbies_weather_forecast', methods: ['GET'])]
    public function getWeatherForecast(
        Request $request,
        \App\Service\WeatherService $weatherService
    ): JsonResponse {
        try {
            $latitude = (float)$request->query->get('lat', 48.8566); // Default: Paris
            $longitude = (float)$request->query->get('lon', 2.3522);
            $days = (int)$request->query->get('days', 7);

            $forecast = $weatherService->getWeatherForecast($latitude, $longitude, min($days, 16));

            if (!$forecast) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Could not fetch weather forecast',
                ], 400);
            }

            return new JsonResponse([
                'ok' => true,
                'forecast' => $forecast,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/weather/current', name: 'app_hobbies_weather_current', methods: ['GET'])]
    public function getCurrentWeather(
        Request $request,
        \App\Service\WeatherService $weatherService
    ): JsonResponse {
        try {
            $latitude = (float)$request->query->get('lat', 48.8566); // Default: Paris
            $longitude = (float)$request->query->get('lon', 2.3522);

            $weather = $weatherService->getCurrentWeather($latitude, $longitude);

            if (!$weather) {
                return new JsonResponse([
                    'ok' => false,
                    'error' => 'Could not fetch current weather',
                ], 400);
            }

            return new JsonResponse([
                'ok' => true,
                'weather' => $weather,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
        }
    }

    #[Route('/{id}/music-recommendations', name: 'app_hobbies_music_recommendations', methods: ['GET'])]
    public function getMusicRecommendations(
        Hobby $hobby,
        \App\Service\MusicBrainzService $musicBrainzService
    ): JsonResponse {
        $this->denyAccessUnlessGranted('HOBBY_VIEW', $hobby);

        try {
            $recommendations = $musicBrainzService->getRecommendations(
                $hobby->name,
                $hobby->category ?? ''
            );

            return new JsonResponse([
                'ok' => $recommendations['success'],
                'data' => $recommendations,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'ok' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    #[Route('/api/insights', name: 'app_hobbies_api_insights', methods: ['GET'])]
    public function apiHobbyInsights(GroqAnalyticsService $groqAnalytics): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $insights = $groqAnalytics->getUserHobbyInsights((int) $user->id);
        return $this->json(['ok' => true, 'insights' => $insights['insights']]);
    }

    #[Route('/api/engagement-insights', name: 'app_hobbies_api_engagement', methods: ['GET'])]
    public function apiEngagementInsights(GroqAnalyticsService $groqAnalytics): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $insights = $groqAnalytics->getUserEngagementInsights((int) $user->id);
        return $this->json(['ok' => true, 'insights' => $insights['insights']]);
    }

    #[Route('/api/recommendations', name: 'app_hobbies_api_recommendations', methods: ['GET'])]
    public function apiHobbyRecommendations(GroqAnalyticsService $groqAnalytics): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $insights = $groqAnalytics->getHobbyRecommendations((int) $user->id);
        return $this->json(['ok' => true, 'insights' => $insights['insights']]);
    }

    // ── 1. Submit job (Pixazo P-Video) ───────────────────────────────────────────
#[Route('/{id}/generate-video', name: 'app_hobbies_generate_video', methods: ['GET'],
    requirements: ['id' => '\d+'])]
public function generateVideo(Hobby $hobby, Request $request): JsonResponse
{
    try {
        $this->denyAccessUnlessGranted('HOBBY_VIEW', $hobby);

        $pixazoKey = $_ENV['PIXAZO_API_KEY'] ?? '';
        if (!$pixazoKey) {
            return new JsonResponse(['ok' => false, 'error' => 'Pixazo API key not configured. Add PIXAZO_API_KEY to .env'], 500);
        }

        $hobbyName     = $hobby->name;
        $hobbyCategory = $hobby->category ?? 'hobby';
        $prompt = "A person happily practicing {$hobbyName}, {$hobbyCategory}, cinematic footage, smooth motion, bright lighting, high quality";

        // ── POST to Pixazo P-Video generate endpoint ──────────────────────────────
        $apiUrl = 'https://gateway.pixazo.ai/p-video/v1/p-video/generate';
        $payload = json_encode([
            'prompt'       => $prompt,
            'aspect_ratio' => '16:9',
            'resolution'   => '720p',
            'duration'     => 5,
        ]);

        if ($payload === false) {
            return new JsonResponse(['ok' => false, 'error' => 'Failed to encode request payload.'], 400);
        }

        $ch = curl_init($apiUrl);
        if ($ch === false) {
            return new JsonResponse(['ok' => false, 'error' => 'Failed to initialize HTTP request.'], 500);
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Cache-Control: no-cache',
                'Ocp-Apim-Subscription-Key: ' . $pixazoKey,
            ],
            CURLOPT_POSTFIELDS => $payload,
        ]);

        $rawResponse = curl_exec($ch);
        $curlError   = curl_error($ch);
        $curlErrno   = curl_errno($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // ── cURL-level failure ────────────────────────────────────────────────────
        if ($rawResponse === false || $httpCode === 0) {
            $reason = match ($curlErrno) {
                CURLE_COULDNT_RESOLVE_HOST => 'Could not resolve gateway.pixazo.ai — check DNS or firewall.',
                CURLE_COULDNT_CONNECT      => 'Could not connect to Pixazo — host unreachable.',
                CURLE_OPERATION_TIMEDOUT   => 'Connection to Pixazo timed out.',
                CURLE_SSL_CONNECT_ERROR    => 'SSL handshake failed — CA bundle may be misconfigured.',
                default                    => 'cURL error (' . $curlErrno . '): ' . $curlError,
            };
            return new JsonResponse(['ok' => false, 'error' => $reason], 502);
        }

        $decoded = json_decode($rawResponse, true);
        error_log('[PIXAZO SUBMIT] HTTP ' . $httpCode . ' — ' . $rawResponse);

        // ── Success: Pixazo returns request_id + QUEUED status ────────────────────
        if ($httpCode === 200 && is_array($decoded) && isset($decoded['request_id'])) {
            return new JsonResponse([
                'ok'         => false,
                'loading'    => true,
                'eta'        => 60,
                'request_id' => $decoded['request_id'],
                'prompt'     => $prompt,
            ]);
        }

        // ── Pixazo error ──────────────────────────────────────────────────────────
        $msg = null;
        if (is_array($decoded)) {
            $raw = $decoded['message'] ?? $decoded['error'] ?? null;
            $msg = is_array($raw) ? json_encode($raw) : $raw;
        }
        $msg = $msg ?? sprintf('Unexpected response from Pixazo (HTTP %d).', $httpCode);

        return new JsonResponse([
            'ok'         => false,
            'error'      => $msg,
            'debug_http' => $httpCode,
            'debug_body' => is_array($decoded) ? $decoded : substr((string)$rawResponse, 0, 300),
        ], $httpCode > 0 ? $httpCode : 502);

    } catch (\Exception $e) {
        return new JsonResponse([
            'ok'      => false,
            'error'   => 'An unexpected error occurred while submitting the video job.',
            'details' => $e->getMessage(),
        ], 500);
    }
}

// ── 2. Poll job status ────────────────────────────────────────────────────────
#[Route('/{id}/generate-video/poll', name: 'app_hobbies_generate_video_poll', methods: ['GET'],
    requirements: ['id' => '\d+'])]
public function pollGenerateVideo(Hobby $hobby, Request $request): JsonResponse
{
    try {
        $this->denyAccessUnlessGranted('HOBBY_VIEW', $hobby);

        $pixazoKey = $_ENV['PIXAZO_API_KEY'] ?? '';
        $requestId = $request->query->get('request_id');

        if (!$requestId) {
            return new JsonResponse(['ok' => false, 'error' => 'Missing request_id.'], 400);
        }

        if (!$pixazoKey) {
            return new JsonResponse(['ok' => false, 'error' => 'PIXAZO_API_KEY environment variable not set.'], 500);
        }

        // ── Poll Pixazo universal status endpoint ─────────────────────────────────
        $statusUrl = 'https://gateway.pixazo.ai/v2/requests/status/' . urlencode($requestId);

        $ch = curl_init($statusUrl);
        if ($ch === false) {
            return new JsonResponse(['ok' => false, 'error' => 'Failed to initialize HTTP request.'], 500);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Ocp-Apim-Subscription-Key: ' . $pixazoKey,
            ],
        ]);

        $rawResponse = curl_exec($ch);
        $curlError   = curl_error($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($rawResponse === false || $httpCode === 0) {
            return new JsonResponse(['ok' => false, 'error' => 'Poll request failed: ' . $curlError], 502);
        }

        if ($httpCode !== 200) {
            return new JsonResponse([
                'ok'             => false,
                'error'          => 'Pixazo returned HTTP ' . $httpCode,
                'debug_http'     => $httpCode,
                'debug_response' => substr((string)$rawResponse, 0, 500),
            ], 502);
        }

        $decoded = json_decode($rawResponse, true);
        if ($decoded === null) {
            return new JsonResponse([
                'ok'             => false,
                'error'          => 'Failed to parse Pixazo JSON response',
                'debug_response' => substr((string)$rawResponse, 0, 300),
            ], 502);
        }

        $status = strtoupper($decoded['status'] ?? '');
        error_log('[PIXAZO POLL] request_id=' . $requestId . ' status=' . $status);

        // ── Still in queue or processing ──────────────────────────────────────────
        if (in_array($status, ['QUEUED', 'PROCESSING'], true)) {
            return new JsonResponse([
                'ok'           => false,
                'loading'      => true,
                'eta'          => 15,
                'debug_status' => $status,
            ]);
        }

        // ── Completed ─────────────────────────────────────────────────────────────
        if ($status === 'COMPLETED') {
            $videoUrl = $decoded['output']['media_url'][0] ?? null;

            if (!$videoUrl) {
                return new JsonResponse([
                    'ok'    => false,
                    'error' => 'Video completed but no URL found in response.',
                    'debug' => $decoded,
                ], 502);
            }

            return new JsonResponse(['ok' => true, 'videoUrl' => $videoUrl]);
        }

        // ── Failed / Error / unknown ──────────────────────────────────────────────
        $errMsg = $decoded['error'] ?? ('Unexpected Pixazo status: ' . $status);
        return new JsonResponse([
            'ok'           => false,
            'error'        => $errMsg,
            'debug_status' => $status,
            'debug_body'   => $decoded,
        ], 502);

    } catch (\Exception $e) {
        return new JsonResponse([
            'ok'      => false,
            'error'   => 'An unexpected error occurred while polling video status.',
            'details' => $e->getMessage(),
        ], 500);
    }
}

}