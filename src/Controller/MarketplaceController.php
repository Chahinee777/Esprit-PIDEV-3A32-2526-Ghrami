<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\User;
use App\Entity\ClassProvider;
use App\Service\MarketplaceService;
use App\Service\StripePaymentService;
use App\Service\CertificateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/marketplace')]
final class MarketplaceController extends AbstractController
{
    #[Route('', name: 'app_marketplace_index', methods: ['GET'])]
public function index(
    Request $request,
    MarketplaceService $marketplaceService,
    EntityManagerInterface $em
): Response {
    $currentUser = $this->getUser();
    $userId = $currentUser instanceof User ? $currentUser->getId() : null;

    $isInstructor = false;
    $applicationPending = false;

    if ($userId) {
        // Better: use repository instead of raw SQL
        $provider = $em->getRepository(ClassProvider::class)
            ->findOneBy(['user' => $userId]);

        if ($provider) {
            $isInstructor = $provider->isVerified();
            $applicationPending = !$isInstructor;
        }
    }

    // Clean query params
    $category = $request->query->get('category');
    $maxPrice = $request->query->get('max_price');
    $search   = $request->query->get('q');

    $maxPrice = is_numeric($maxPrice) ? (float) $maxPrice : null;

    $classes = $marketplaceService->listClasses(
        $category,
        $maxPrice,
        $search,
        $userId
    );

    // Sorting
    $sort = $request->query->get('sort', '');

    usort($classes, function ($a, $b) use ($sort) {
        return match ($sort) {
            'price_asc'  => ($a['price'] ?? 0) <=> ($b['price'] ?? 0),
            'price_desc' => ($b['price'] ?? 0) <=> ($a['price'] ?? 0),
            'rating'     => ($b['rating'] ?? 0) <=> ($a['rating'] ?? 0),
            'newest'     => strtotime($b['created_at'] ?? '2000-01-01') 
                         <=> strtotime($a['created_at'] ?? '2000-01-01'),
            default      => ($b['participants'] ?? 0) <=> ($a['participants'] ?? 0),
        };
    });

    return $this->render('marketplace/index.html.twig', [
        'classes'            => $classes,
        'isInstructor'       => $isInstructor,
        'applicationPending' => $applicationPending,
    ]);
}

    #[Route('/booking/create', name: 'app_marketplace_booking_create', methods: ['POST'])]
    public function createBooking(Request $request, MarketplaceService $marketplaceService, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $userId  = (int) $currentUser->id;
        $classId = (int) $request->request->get('class_id');

        if ($classId <= 0) {
            return $this->json(['error' => 'Invalid class ID'], Response::HTTP_BAD_REQUEST);
        }

        $classData = $em->getConnection()->fetchAssociative(
            'SELECT cp.user_id FROM classes c JOIN class_providers cp ON c.provider_id = cp.provider_id WHERE c.class_id = :classId',
            ['classId' => $classId]
        );

        if ($classData && (int) $classData['user_id'] === $userId) {
            return $this->json(['error' => 'You cannot book your own class'], Response::HTTP_FORBIDDEN);
        }

        $booking = $marketplaceService->createBooking($classId, $userId);
        return $this->json(['ok' => true, 'id' => $booking->id]);
    }

    #[Route('/booking/pay', name: 'app_marketplace_booking_pay', methods: ['POST'])]
    public function payBooking(Request $request, MarketplaceService $marketplaceService): JsonResponse
    {
        $bookingId = (int) $request->request->get('booking_id');
        if ($bookingId <= 0) {
            return $this->json(['error' => 'Invalid booking ID'], Response::HTTP_BAD_REQUEST);
        }

        $marketplaceService->markBookingPaid(
            $bookingId,
            $request->request->get('stripe_session_id')
        );
        return $this->json(['ok' => true]);
    }

    #[Route('/booking/cancel', name: 'app_marketplace_booking_cancel', methods: ['POST'])]
    public function cancelBooking(Request $request, MarketplaceService $marketplaceService): JsonResponse
    {
        $bookingId = (int) $request->request->get('booking_id');
        if ($bookingId <= 0) {
            return $this->json(['error' => 'Invalid booking ID'], Response::HTTP_BAD_REQUEST);
        }

        $marketplaceService->cancelBooking($bookingId);
        return $this->json(['ok' => true]);
    }

    #[Route('/booking/complete', name: 'app_marketplace_booking_complete', methods: ['POST'])]
    public function completeBooking(Request $request, MarketplaceService $marketplaceService): JsonResponse
    {
        $bookingId = (int) $request->request->get('booking_id');
        if ($bookingId <= 0) {
            return $this->json(['error' => 'Invalid booking ID'], Response::HTTP_BAD_REQUEST);
        }

        $marketplaceService->completeBooking($bookingId);
        return $this->json(['ok' => true]);
    }

    #[Route('/booking/rate', name: 'app_marketplace_booking_rate', methods: ['POST'])]
    public function rateBooking(Request $request, MarketplaceService $marketplaceService, ValidatorInterface $validator): JsonResponse
    {
        $bookingId = (int) $request->request->get('booking_id');
        $rating = (int) $request->request->get('rating');
        $review = trim((string) $request->request->get('review', ''));

        if ($bookingId <= 0) {
            return $this->json([
                'ok' => true,
                'error' => 'Validation failed.',
                'errors' => ['booking_id' => 'Invalid booking ID.'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $bookingValidation = new Booking();
        $bookingValidation->rating = $rating;
        $bookingValidation->review = $review !== '' ? $review : null;

        $violations = $validator->validate($bookingValidation);
        if (count($violations) > 0) {
            return $this->json([
                'ok' => false,
                'error' => 'Validation failed.',
                'errors' => $this->normalizeValidationErrors($violations),
            ], Response::HTTP_BAD_REQUEST);
        }

        $marketplaceService->rateBooking(
            $bookingId,
            $rating,
            $review !== '' ? $review : null
        );
        return $this->json(['ok' => true]);
    }

    #[Route('/bookings/user', name: 'app_marketplace_user_bookings', methods: ['GET'])]
    public function userBookings(Request $request, MarketplaceService $marketplaceService): JsonResponse
    {
        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? (int) $currentUser->id : (int) $request->query->get('user_id');
        return $this->json($marketplaceService->listUserBookings($userId));
    }

    #[Route('/bookings', name: 'app_marketplace_bookings', methods: ['GET'])]
    public function bookingsPage(MarketplaceService $marketplaceService): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }
        return $this->render('marketplace/bookings.html.twig', [
            'bookings' => $marketplaceService->listUserBookings((int) $currentUser->id),
        ]);
    }

    #[Route('/bookings/provider', name: 'app_marketplace_provider_bookings', methods: ['GET'])]
    public function providerBookings(Request $request, MarketplaceService $marketplaceService): JsonResponse
    {
        return $this->json($marketplaceService->listProviderBookings((int) $request->query->get('provider_id')));
    }

    #[Route('/checkout/session', name: 'app_marketplace_checkout_session', methods: ['POST'])]
    public function createCheckoutSession(
        Request $request,
        MarketplaceService $marketplaceService,
        StripePaymentService $stripeService
    ): JsonResponse {
        try {
            $currentUser = $this->getUser();
            if (!$currentUser instanceof User) {
                return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
            }

            $bookingId = (int) $request->request->get('booking_id');
            $booking   = $marketplaceService->getBookingById($bookingId);

            if (!$booking || !$booking->user || $booking->user->id !== (int) $currentUser->id) {
                return $this->json(['error' => 'Booking not found'], Response::HTTP_NOT_FOUND);
            }

            $classEntity = $marketplaceService->getClassById($booking->class->id);
            if (!$classEntity) {
                return $this->json(['error' => 'Class not found'], Response::HTTP_NOT_FOUND);
            }

            $amountCents = StripePaymentService::tndToEurCents((float) $classEntity->price);

            $successUrl = $this->generateUrl('app_marketplace_payment_success', [], UrlGeneratorInterface::ABSOLUTE_URL)
                . '?session_id={CHECKOUT_SESSION_ID}&booking_id=' . $bookingId;
            $cancelUrl  = $this->generateUrl('app_marketplace_payment_cancel', [], UrlGeneratorInterface::ABSOLUTE_URL);

            $session = $stripeService->createCheckoutSession(
                $classEntity->title, $amountCents, 'eur', $bookingId, $successUrl, $cancelUrl
            );

            if (!$session) {
                return $this->json(['error' => 'Failed to create payment session'], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->json(['ok' => true, 'session_id' => $session->id, 'checkout_url' => $session->url]);
        } catch (\Exception $e) {
            error_log('Checkout session error: ' . $e->getMessage());
            return $this->json(['error' => 'Server error'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/payment/success', name: 'app_marketplace_payment_success', methods: ['GET'])]
    public function paymentSuccess(
        Request $request,
        MarketplaceService $marketplaceService,
        StripePaymentService $stripeService
    ): Response {
        try {
            $sessionId = (string) $request->query->get('session_id', '');
            $bookingId = (int) $request->query->get('booking_id', 0);

            if ($sessionId === '' || $bookingId === 0) {
                $this->addFlash('error', 'Invalid payment parameters.');
                return $this->redirectToRoute('app_marketplace_index');
            }

            if (!$stripeService->verifySessionPaid($sessionId)) {
                $this->addFlash('error', 'Payment verification failed.');
                return $this->redirectToRoute('app_marketplace_index');
            }

            $marketplaceService->markBookingPaid($bookingId, $sessionId);
            $this->addFlash('success', '🎉 Payment successful! Your booking is confirmed. You can now watch the class video.');
            return $this->redirectToRoute('app_marketplace_bookings');
        } catch (\Exception $e) {
            error_log('Payment success error: ' . $e->getMessage());
            $this->addFlash('error', 'An error occurred while processing your payment.');
            return $this->redirectToRoute('app_marketplace_index');
        }
    }

    #[Route('/payment/cancel', name: 'app_marketplace_payment_cancel', methods: ['GET'])]
    public function paymentCancel(): Response
    {
        $this->addFlash('info', 'Payment was cancelled.');
        return $this->redirectToRoute('app_marketplace_index');
    }

    // ── Class detail JSON (used by the JS modals in both pages) ──────────────

    #[Route('/class/{classId}', name: 'app_marketplace_class_detail', methods: ['GET'])]
    public function getClassDetail(int $classId, EntityManagerInterface $em): JsonResponse
    {
        $class = $em->getConnection()->fetchAssociative(
            "SELECT c.*,
                    cp.company_name, cp.rating AS provider_rating, cp.is_verified,
                    cp.provider_id, cp.user_id AS provider_user_id,
                    u.full_name AS provider_name,
                    (SELECT COUNT(*) FROM bookings b WHERE b.class_id = c.class_id AND b.status != 'cancelled') AS current_enrollment,
                    (SELECT AVG(b.rating) FROM bookings b WHERE b.class_id = c.class_id AND b.rating IS NOT NULL) AS rating
             FROM classes c
             JOIN class_providers cp ON c.provider_id = cp.provider_id
             JOIN users u ON cp.user_id = u.user_id
             WHERE c.class_id = :classId",
            ['classId' => $classId]
        );

        if (!$class) {
            return $this->json(['error' => 'Class not found'], Response::HTTP_NOT_FOUND);
        }

        $currentUser = $this->getUser();
        $userId      = $currentUser instanceof User ? (int) $currentUser->id : null;
        $isInstructor = $userId && (int) $class['provider_user_id'] === $userId;

        $hasBooked    = false;
        $watchProgress = 0;
        $videoUrl     = null;
        $imageUrl     = null;
        $bookingId    = null;
        $isPaid       = false;

        if ($userId && !$isInstructor) {
            $booking = $em->getConnection()->fetchAssociative(
                "SELECT booking_id, watch_progress, payment_status
                 FROM bookings
                 WHERE class_id = :cid AND user_id = :uid AND status != 'cancelled'
                 ORDER BY booking_id DESC LIMIT 1",
                ['cid' => $classId, 'uid' => $userId]
            );

            if ($booking) {
                $hasBooked    = true;
                $bookingId    = (int) $booking['booking_id'];
                $watchProgress = (int) ($booking['watch_progress'] ?? 0);
                $isPaid       = strtolower((string) ($booking['payment_status'] ?? '')) === 'paid';
            }
        }

        // FIX: Only expose video/image URLs for web-uploaded files (relative paths
        // starting with 'uploads/'). Legacy desktop absolute paths (C:\...) cannot
        // be served by the web server and are silently omitted.
        $rawVideo = $class['video_path'] ?? null;
        if ($isPaid && $rawVideo && str_starts_with($rawVideo, 'uploads/')) {
            $videoUrl = '/' . ltrim($rawVideo, '/');
        }

        $rawImage = $class['image_path'] ?? null;
        if ($rawImage && str_starts_with($rawImage, 'uploads/')) {
            $imageUrl = '/' . ltrim($rawImage, '/');
        }

        return $this->json([
            'id'             => (int) $class['class_id'],
            'title'          => $class['title'],
            'description'    => $class['description'],
            'price'          => (float) $class['price'],
            'duration'       => (int) $class['duration'],
            'category'       => $class['category'],
            'rating'         => $class['rating'] ? (float) $class['rating'] : null,
            'participants'   => (int) $class['current_enrollment'],
            'maxParticipants'=> (int) $class['max_participants'],
            'hasBooked'      => $hasBooked,
            'isPaid'         => $isPaid,
            'isInstructor'   => $isInstructor,
            'watchProgress'  => $watchProgress,
            'bookingId'      => $bookingId,
            'videoUrl'       => $videoUrl,          // null if not paid or legacy path
            'imageUrl'       => $imageUrl,          // null if not uploaded or legacy path
            'provider'       => [
                'id'     => (int) $class['provider_id'],
                'name'   => $class['provider_name'],
                'rating' => (float) $class['provider_rating'],
            ],
        ]);
    }

    #[Route('/provider/{providerId}', name: 'app_marketplace_provider_detail', methods: ['GET'])]
    public function getProviderDetail(int $providerId, EntityManagerInterface $em): JsonResponse
    {
        $provider = $em->getRepository(ClassProvider::class)->find($providerId);
        if (!$provider) {
            return $this->json(['error' => 'Provider not found'], Response::HTTP_NOT_FOUND);
        }

        $conn    = $em->getConnection();
        $classes = $conn->fetchAllAssociative(
            'SELECT class_id, title, category, price FROM classes WHERE provider_id = :pid LIMIT 6',
            ['pid' => $providerId]
        );

        $enrolledCount = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM bookings b
             JOIN classes c ON c.class_id = b.class_id
             WHERE c.provider_id = :pid AND b.status != 'cancelled'",
            ['pid' => $providerId]
        );

        return $this->json([
            'id'           => $provider->id,
            'name'         => $provider->companyName,
            'description'  => $provider->expertise,   // expertise = "about" text
            'rating'       => $provider->rating,
            'verified'     => $provider->isVerified,
            'classCount'   => count($classes),
            'enrolledCount'=> $enrolledCount,
            'classes'      => $classes,
        ]);
    }

    // ── Watch-progress save ───────────────────────────────────────────────────

    #[Route('/class/{classId}/progress', name: 'app_marketplace_save_progress', methods: ['POST'])]
    public function saveWatchProgress(int $classId, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $progress = min(100, max(0, (int) $request->request->get('progress', 0)));

        // FIX: DB stores status as lowercase ('scheduled'), not 'SCHEDULED'.
        // The old query never matched, so progress was never saved.
        $updated = $em->getConnection()->executeStatement(
            "UPDATE bookings
             SET watch_progress = :progress
             WHERE class_id = :cid AND user_id = :uid
               AND status IN ('scheduled', 'completed')
               AND payment_status = 'paid'",
            ['progress' => $progress, 'cid' => $classId, 'uid' => (int) $currentUser->id]
        );

        return $this->json(['ok' => true, 'progress' => $progress, 'rowsUpdated' => $updated]);
    }

    // ── Certificate download ──────────────────────────────────────────────────

    #[Route('/certificate/{bookingId}', name: 'app_marketplace_certificate_download', methods: ['GET'])]
    public function downloadCertificate(
        int $bookingId,
        EntityManagerInterface $em,
        CertificateService $certificateService
    ): Response {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $booking = $em->getConnection()->fetchAssociative(
            "SELECT b.booking_id, b.status, b.booking_date, b.user_id,
                    c.title AS class_title,
                    u.full_name AS student_full_name, u.username AS student_username,
                    iu.full_name AS instructor_full_name, iu.username AS instructor_username
             FROM bookings b
             JOIN classes c ON c.class_id = b.class_id
             JOIN users u ON u.user_id = b.user_id
             JOIN class_providers cp ON cp.provider_id = c.provider_id
             JOIN users iu ON iu.user_id = cp.user_id
             WHERE b.booking_id = :bid",
            ['bid' => $bookingId]
        );

        if (!is_array($booking) || (int) $booking['user_id'] !== (int) $currentUser->id) {
            $this->addFlash('error', 'Booking not found or access denied.');
            return $this->redirectToRoute('app_marketplace_bookings');
        }

        if (strtolower((string) $booking['status']) !== 'completed') {
            $this->addFlash('error', 'Certificate is available only for completed bookings.');
            return $this->redirectToRoute('app_marketplace_bookings');
        }

        $studentName    = trim((string) ($booking['student_full_name'] ?: $booking['student_username']));
        $instructorName = trim((string) ($booking['instructor_full_name'] ?: $booking['instructor_username']));
        $issuedOn       = (new \DateTime((string) $booking['booking_date']))->format('F d, Y');

        $pdf = $certificateService->generate([
            'student_name'    => $studentName,
            'class_title'     => (string) $booking['class_title'],
            'instructor_name' => $instructorName,
            'issued_on'       => $issuedOn,
        ]);

        return new Response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="certificate_' . $bookingId . '.pdf"',
        ]);
    }

    // ── Become instructor ─────────────────────────────────────────────────────

    #[Route('/become-instructor', name: 'app_marketplace_become_instructor', methods: ['POST'])]
    public function becomeInstructor(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['error' => 'You must be logged in'], Response::HTTP_UNAUTHORIZED);
        }

        $userId   = (int) $currentUser->id;
        $existing = $em->getConnection()->fetchOne(
            'SELECT provider_id FROM class_providers WHERE user_id = :userId',
            ['userId' => $userId]
        );

        if ($existing) {
            return $this->json(['error' => 'You already have an instructor application'], Response::HTTP_BAD_REQUEST);
        }

        $companyName = trim((string) $request->request->get('company_name', ''));
        $expertise   = trim((string) $request->request->get('expertise', ''));

        if (empty($expertise)) {
            return $this->json(['error' => 'Expertise is required'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($companyName) > 100) {
            return $this->json(['error' => 'Company name cannot exceed 100 characters.'], Response::HTTP_BAD_REQUEST);
        }

        if (mb_strlen($expertise) > 1000) {
            return $this->json(['error' => 'Expertise cannot exceed 1000 characters.'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $em->getConnection()->executeStatement(
                'INSERT INTO class_providers (user_id, company_name, expertise, is_verified, rating)
                 VALUES (:userId, :company, :expertise, false, 0)',
                ['userId' => $userId, 'company' => $companyName ?: null, 'expertise' => $expertise]
            );
            return $this->json(['ok' => true, 'message' => 'Application submitted successfully']);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to submit: ' . $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
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
}