<?php

namespace App\Controller;

use App\Entity\ClassProvider;
use App\Entity\LearningClass;
use App\Entity\User;
use App\Service\CertificateService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/instructor')]
final class InstructorController extends AbstractController
{
    private string $projectDir;

    public function __construct(
        // FIX 2: Inject the real project root so we can build proper upload paths
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ) {
        $this->projectDir = $projectDir;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * FIX 1: Convert PHP ini size strings (e.g. "8M", "512K", "2G") to bytes.
     * Used to detect when the entire POST body has been silently dropped by PHP.
     */
    private function parsePhpSize(string $size): int
    {
        $unit  = strtolower(substr(trim($size), -1));
        $value = (int) $size;

        return match ($unit) {
            'g' => $value * 1_073_741_824,
            'm' => $value * 1_048_576,
            'k' => $value * 1_024,
            default => $value,
        };
    }

    /**
     * FIX 2: Move an uploaded file into public/uploads/<subDir>/ and return
     * the web-accessible relative path (e.g. "uploads/videos/video_abc.mp4").
     * Throws FileException on any error so callers can surface a proper message.
     */
    private function moveUpload(
        \Symfony\Component\HttpFoundation\File\UploadedFile $file,
        string $subDir,
        string $prefix
    ): string {
        $dir = $this->projectDir . '/public/uploads/' . $subDir;

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new FileException('Failed to create upload directory: ' . $dir);
        }

        $ext      = $file->guessExtension() ?: pathinfo((string) $file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'bin';
        $filename = uniqid($prefix . '_', true) . '.' . $ext;
        $file->move($dir, $filename);

        return 'uploads/' . $subDir . '/' . $filename;
    }

    /** Delete a previously-uploaded file from public/ if it exists. */
    private function removeUpload(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }
        $abs = $this->projectDir . '/public/' . $relativePath;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────

    #[Route('', name: 'app_instructor_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $provider = $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]);
        if (!$provider instanceof ClassProvider) {
            return $this->render('instructor/index.html.twig', [
                'provider'          => null,
                'classes'           => [],
                'bookings'          => [],
                'stats'             => ['classes' => 0, 'bookings' => 0, 'completed' => 0, 'revenue' => 0],
                'revenueByCategory' => [],
            ]);
        }

        $conn = $em->getConnection();

        $classes = $conn->fetchAllAssociative(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM bookings b WHERE b.class_id = c.class_id) AS booking_count,
                    (SELECT COALESCE(AVG(b.rating), 0) FROM bookings b WHERE b.class_id = c.class_id AND b.rating IS NOT NULL) AS avg_rating
             FROM classes c
             WHERE c.provider_id = :pid
             ORDER BY c.class_id DESC",
            ['pid' => $provider->id]
        );

        $bookings = $conn->fetchAllAssociative(
            "SELECT b.*, c.title AS class_title, u.username, u.full_name
             FROM bookings b
             JOIN classes c ON c.class_id = b.class_id
             JOIN users u ON u.user_id = b.user_id
             WHERE c.provider_id = :pid
             ORDER BY b.booking_date DESC
             LIMIT 100",
            ['pid' => $provider->id]
        );

        $stats = [
            'classes'   => (int) $conn->fetchOne('SELECT COUNT(*) FROM classes WHERE provider_id = :pid', ['pid' => $provider->id]),
            'bookings'  => (int) $conn->fetchOne(
                'SELECT COUNT(*) FROM bookings b JOIN classes c ON c.class_id = b.class_id WHERE c.provider_id = :pid',
                ['pid' => $provider->id]
            ),
            'completed' => (int) $conn->fetchOne(
                "SELECT COUNT(*) FROM bookings b JOIN classes c ON c.class_id = b.class_id WHERE c.provider_id = :pid AND b.status = 'completed'",
                ['pid' => $provider->id]
            ),
            'revenue'   => (float) $conn->fetchOne(
                "SELECT COALESCE(SUM(b.total_amount), 0) FROM bookings b JOIN classes c ON c.class_id = b.class_id WHERE c.provider_id = :pid AND b.payment_status = 'paid'",
                ['pid' => $provider->id]
            ),
        ];

        $revenueByCategory = $conn->fetchAllAssociative(
            "SELECT COALESCE(c.category, 'No Category') AS category,
                    COALESCE(SUM(b.total_amount), 0)     AS revenue,
                    COUNT(b.booking_id)                  AS booking_count
             FROM classes c
             LEFT JOIN bookings b ON b.class_id = c.class_id AND b.payment_status = 'paid'
             WHERE c.provider_id = :pid
             GROUP BY c.category
             ORDER BY revenue DESC",
            ['pid' => $provider->id]
        );

        return $this->render('instructor/index.html.twig', [
            'provider'          => $provider,
            'classes'           => $classes,
            'bookings'          => $bookings,
            'stats'             => $stats,
            'revenueByCategory' => $revenueByCategory,
        ]);
    }

    // ─── Create Class ─────────────────────────────────────────────────────────

    #[Route('/class/create', name: 'app_instructor_class_create', methods: ['POST'])]
    public function createClass(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // FIX 3: Validate CSRF token (was missing entirely before)
        if (!$this->isCsrfTokenValid('create_class', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid security token. Please refresh the page and try again.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        // FIX 1: Detect PHP post_max_size overflow.
        // When the total upload exceeds post_max_size, PHP silently empties $_POST
        // and $_FILES — so $title becomes '' and we redirect without saving anything,
        // with no obvious error. Detect this before touching any request data.
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postMaxBytes  = $this->parsePhpSize((string) ini_get('post_max_size'));
        if ($contentLength > 0 && $postMaxBytes > 0 && $contentLength > $postMaxBytes) {
            $this->addFlash('error', sprintf(
                'Upload failed: total upload size (%s MB) exceeds the server limit (%s MB). '
                . 'Ask your admin to increase post_max_size in php.ini, or use a smaller file.',
                round($contentLength / 1_048_576, 1),
                round($postMaxBytes / 1_048_576, 1)
            ));
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $provider = $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]);
        if (!$provider instanceof ClassProvider) {
            $this->addFlash('error', 'You are not registered as a class provider.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        // Text fields
        $title           = trim((string) $request->request->get('title', ''));
        $category        = trim((string) $request->request->get('category', ''));
        $price           = (float) $request->request->get('price', 0);
        $duration        = (int) $request->request->get('duration', 0);
        $maxParticipants = (int) $request->request->get('max_participants', 1);
        $description     = trim((string) $request->request->get('description', ''));

        if ($title === '' || $price <= 0 || $duration <= 0 || $maxParticipants <= 0) {
            $this->addFlash('error', 'Please provide valid class information (title required; price, duration, and participants must be positive).');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        // FIX: Video and thumbnail are now mandatory
        $videoFile     = $request->files->get('video');
        $thumbnailFile = $request->files->get('thumbnail');

        if (!$videoFile || !$videoFile->isValid()) {
            $error = $videoFile ? ('Upload error: ' . $videoFile->getErrorMessage()) : 'No file received — check that PHP\'s upload_max_filesize is large enough.';
            $this->addFlash('error', 'A video file is required. ' . $error);
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        if (!$thumbnailFile || !$thumbnailFile->isValid()) {
            $error = $thumbnailFile ? ('Upload error: ' . $thumbnailFile->getErrorMessage()) : 'No file received.';
            $this->addFlash('error', 'A thumbnail image is required. ' . $error);
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        if ($videoFile->getSize() > 100 * 1_048_576) {
            $this->addFlash('error', sprintf('Video is too large (%s MB). Maximum is 100 MB.', round($videoFile->getSize() / 1_048_576, 1)));
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        if ($thumbnailFile->getSize() > 5 * 1_048_576) {
            $this->addFlash('error', sprintf('Thumbnail is too large (%s MB). Maximum is 5 MB.', round($thumbnailFile->getSize() / 1_048_576, 1)));
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        try {
            $class                  = new LearningClass();
            $class->provider        = $provider;
            $class->title           = $title;
            $class->category        = $category !== '' ? $category : null;
            $class->description     = $description !== '' ? $description : null;
            $class->price           = $price;
            $class->duration        = $duration;
            $class->maxParticipants = $maxParticipants;

            // FIX 2: Use absolute paths for upload dirs; store relative web paths on entity
            $class->videoPath = $this->moveUpload($videoFile, 'videos', 'video');
            $class->imagePath = $this->moveUpload($thumbnailFile, 'thumbnails', 'thumb');

            $em->persist($class);
            $em->flush();

            $this->addFlash('success', sprintf('✓ Class "%s" created successfully!', $title));
        } catch (FileException $e) {
            $this->addFlash('error', 'File upload failed: ' . $e->getMessage());
        } catch (\Exception $e) {
            error_log('Class creation error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $this->addFlash('error', 'Error creating class: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_instructor_dashboard');
    }

    // ─── Update Class ─────────────────────────────────────────────────────────

    #[Route('/class/update', name: 'app_instructor_class_update', methods: ['POST'])]
    public function updateClass(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('update_class', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $provider = $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]);
        if (!$provider instanceof ClassProvider) {
            $this->addFlash('error', 'You are not registered as a class provider.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $classId = (int) $request->request->get('class_id', 0);
        /** @var LearningClass|null $class */
        $class = $em->getRepository(LearningClass::class)->find($classId);

        if (!$class instanceof LearningClass || $class->provider?->id !== $provider->id) {
            $this->addFlash('error', 'Class not found.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $title           = trim((string) $request->request->get('title', ''));
        $category        = trim((string) $request->request->get('category', ''));
        $price           = (float) $request->request->get('price', 0);
        $duration        = (int) $request->request->get('duration', 0);
        $maxParticipants = (int) $request->request->get('max_participants', 1);
        $description     = trim((string) $request->request->get('description', ''));

        if ($title === '' || $price < 0 || $duration <= 0 || $maxParticipants <= 0) {
            $this->addFlash('error', 'Please provide valid class information.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $class->title           = $title;
        $class->category        = $category !== '' ? $category : null;
        $class->description     = $description !== '' ? $description : null;
        $class->price           = $price;
        $class->duration        = $duration;
        $class->maxParticipants = $maxParticipants;

        // FIX 5: Update video only when a new file is actually uploaded
        $videoFile = $request->files->get('video');
        if ($videoFile && $videoFile->isValid() && $videoFile->getSize() > 0) {
            if ($videoFile->getSize() > 500 * 1_048_576) {
                $this->addFlash('error', 'Video is too large. Maximum is 500 MB.');
                return $this->redirectToRoute('app_instructor_dashboard');
            }
            try {
                $this->removeUpload($class->videoPath);
                $class->videoPath = $this->moveUpload($videoFile, 'videos', 'video');
            } catch (FileException $e) {
                $this->addFlash('error', 'Video upload failed: ' . $e->getMessage());
                return $this->redirectToRoute('app_instructor_dashboard');
            }
        }

        // FIX 5: Update thumbnail only when a new file is actually uploaded
        $thumbnailFile = $request->files->get('thumbnail');
        if ($thumbnailFile && $thumbnailFile->isValid() && $thumbnailFile->getSize() > 0) {
            if ($thumbnailFile->getSize() > 5 * 1_048_576) {
                $this->addFlash('error', 'Thumbnail is too large. Maximum is 5 MB.');
                return $this->redirectToRoute('app_instructor_dashboard');
            }
            try {
                $this->removeUpload($class->imagePath);
                $class->imagePath = $this->moveUpload($thumbnailFile, 'thumbnails', 'thumb');
            } catch (FileException $e) {
                $this->addFlash('error', 'Thumbnail upload failed: ' . $e->getMessage());
                return $this->redirectToRoute('app_instructor_dashboard');
            }
        }

        $em->flush();

        $this->addFlash('success', 'Class updated successfully.');
        return $this->redirectToRoute('app_instructor_dashboard');
    }

    // ─── Delete Class ─────────────────────────────────────────────────────────

    #[Route('/class/delete', name: 'app_instructor_class_delete', methods: ['POST'])]
    public function deleteClass(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('delete_class', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $provider = $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]);
        if (!$provider instanceof ClassProvider) {
            $this->addFlash('error', 'You are not registered as a class provider.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $classId = (int) $request->request->get('class_id', 0);
        /** @var LearningClass|null $class */
        $class = $em->getRepository(LearningClass::class)->find($classId);

        if (!$class instanceof LearningClass || $class->provider?->id !== $provider->id) {
            $this->addFlash('error', 'Class not found.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        // Clean up uploaded files when the class is deleted
        $this->removeUpload($class->videoPath);
        $this->removeUpload($class->imagePath);

        $em->remove($class);
        $em->flush();

        $this->addFlash('success', 'Class deleted successfully.');
        return $this->redirectToRoute('app_instructor_dashboard');
    }

    // ─── Update Booking Status ────────────────────────────────────────────────

    #[Route('/booking/update-status', name: 'app_instructor_booking_update_status', methods: ['POST'])]
    public function updateBookingStatus(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('update_booking_status', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $provider = $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]);
        if (!$provider instanceof ClassProvider) {
            $this->addFlash('error', 'You are not registered as a class provider.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $bookingId     = (int) $request->request->get('booking_id', 0);
        $status        = trim((string) $request->request->get('status', ''));
        $paymentStatus = trim((string) $request->request->get('payment_status', ''));
        $allowedStatus  = ['pending', 'scheduled', 'completed', 'cancelled'];
        $allowedPayment = ['pending', 'paid', 'failed', 'refunded'];

        if (!in_array($status, $allowedStatus, true) || !in_array($paymentStatus, $allowedPayment, true)) {
            $this->addFlash('error', 'Invalid booking status update.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $ownsBooking = (int) $em->getConnection()->fetchOne(
            'SELECT COUNT(*)
             FROM bookings b
             JOIN classes c ON c.class_id = b.class_id
             WHERE b.booking_id = :bid AND c.provider_id = :pid',
            ['bid' => $bookingId, 'pid' => $provider->id]
        );

        if ($ownsBooking === 0) {
            $this->addFlash('error', 'Booking not found.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $em->getConnection()->update(
            'bookings',
            ['status' => $status, 'payment_status' => $paymentStatus],
            ['booking_id' => $bookingId]
        );

        $this->addFlash('success', 'Booking status updated.');
        return $this->redirectToRoute('app_instructor_dashboard');
    }

    // ─── Export Bookings CSV ──────────────────────────────────────────────────

    #[Route('/export/bookings', name: 'app_instructor_export_bookings', methods: ['GET'])]
    public function exportBookings(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $provider = $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]);
        if (!$provider instanceof ClassProvider) {
            $this->addFlash('error', 'You are not registered as a class provider.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $rows = $em->getConnection()->fetchAllAssociative(
            "SELECT b.booking_id, c.title AS class_title, u.username, u.email,
                    b.status, b.payment_status, b.total_amount, b.booking_date, b.rating
             FROM bookings b
             JOIN classes c ON c.class_id = b.class_id
             JOIN users u ON u.user_id = b.user_id
             WHERE c.provider_id = :pid
             ORDER BY b.booking_date DESC",
            ['pid' => $provider->id]
        );

        $response = new StreamedResponse(function () use ($rows): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['booking_id', 'class_title', 'username', 'email', 'status', 'payment_status', 'total_amount', 'booking_date', 'rating']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['booking_id'],
                    $row['class_title'],
                    $row['username'],
                    $row['email'],
                    $row['status'],
                    $row['payment_status'],
                    $row['total_amount'],
                    $row['booking_date'],
                    $row['rating'],
                ]);
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="instructor_bookings.csv"');

        return $response;
    }

    // ─── Download Certificate ─────────────────────────────────────────────────

    #[Route('/booking/certificate/{bookingId}', name: 'app_instructor_booking_certificate', methods: ['GET'])]
    public function downloadCertificate(int $bookingId, EntityManagerInterface $em, CertificateService $certificateService): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $provider = $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]);
        if (!$provider instanceof ClassProvider) {
            $this->addFlash('error', 'You are not registered as a class provider.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $row = $em->getConnection()->fetchAssociative(
            "SELECT b.booking_id, b.status, b.booking_date,
                    c.title AS class_title,
                    u.full_name AS student_full_name,
                    u.username AS student_username,
                    iu.full_name AS instructor_full_name,
                    iu.username AS instructor_username
             FROM bookings b
             JOIN classes c ON c.class_id = b.class_id
             JOIN users u ON u.user_id = b.user_id
             JOIN class_providers cp ON cp.provider_id = c.provider_id
             JOIN users iu ON iu.user_id = cp.user_id
             WHERE b.booking_id = :bid AND c.provider_id = :pid",
            ['bid' => $bookingId, 'pid' => $provider->id]
        );

        if (!is_array($row)) {
            $this->addFlash('error', 'Booking not found.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        if (strtolower((string) $row['status']) !== 'completed') {
            $this->addFlash('error', 'Certificate is only available for completed bookings.');
            return $this->redirectToRoute('app_instructor_dashboard');
        }

        $studentName    = trim((string) ($row['student_full_name'] ?: $row['student_username']));
        $instructorName = trim((string) ($row['instructor_full_name'] ?: $row['instructor_username']));
        $issuedOn       = (new \DateTime((string) $row['booking_date']))->format('F d, Y');

        $pdf = $certificateService->generate([
            'student_name'    => $studentName,
            'class_title'     => (string) $row['class_title'],
            'instructor_name' => $instructorName,
            'issued_on'       => $issuedOn,
        ]);

        $safeStudent = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $studentName) ?: 'student';
        $safeClass   = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $row['class_title']) ?: 'class';
        $fileName    = sprintf('Certificate_%s_%s.pdf', $safeStudent, $safeClass);

        return new Response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    // ─── Analytics API ────────────────────────────────────────────────────────

    #[Route('/api/analytics/bookings', name: 'app_instructor_api_analytics_bookings', methods: ['GET'])]
    public function apiAnalyticsBookings(EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        $provider = $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]);
        if (!$provider instanceof ClassProvider) {
            return $this->json(['ok' => false, 'error' => 'Not a provider'], Response::HTTP_FORBIDDEN);
        }

        $data = $em->getConnection()->fetchAllAssociative(
            "SELECT DATE(b.booking_date) AS booking_date,
                    COUNT(*) AS count,
                    SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) AS completed
             FROM bookings b
             JOIN classes c ON c.class_id = b.class_id
             WHERE c.provider_id = :pid AND b.booking_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(b.booking_date)
             ORDER BY booking_date DESC",
            ['pid' => $provider->id]
        );

        return $this->json(['ok' => true, 'bookingTrends' => $data]);
    }

    #[Route('/api/analytics/revenue', name: 'app_instructor_api_analytics_revenue', methods: ['GET'])]
    public function apiAnalyticsRevenue(EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        $provider = $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]);
        if (!$provider instanceof ClassProvider) {
            return $this->json(['ok' => false, 'error' => 'Not a provider'], Response::HTTP_FORBIDDEN);
        }

        $byCategory = $em->getConnection()->fetchAllAssociative(
            "SELECT c.category,
                    COUNT(DISTINCT b.booking_id)                                             AS bookings,
                    COALESCE(SUM(b.total_amount), 0)                                         AS revenue,
                    COALESCE(SUM(CASE WHEN b.payment_status = 'pending' THEN b.total_amount ELSE 0 END), 0) AS pending
             FROM bookings b
             JOIN classes c ON c.class_id = b.class_id
             WHERE c.provider_id = :pid
             GROUP BY c.category
             ORDER BY revenue DESC",
            ['pid' => $provider->id]
        );

        // DATE_TRUNC is PostgreSQL; use DATE_FORMAT for MySQL
        $byMonth = $em->getConnection()->fetchAllAssociative(
            "SELECT DATE_FORMAT(b.booking_date, '%Y-%m-01') AS month,
                    COALESCE(SUM(b.total_amount), 0) AS revenue
             FROM bookings b
             JOIN classes c ON c.class_id = b.class_id
             WHERE c.provider_id = :pid AND b.booking_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(b.booking_date, '%Y-%m-01')
             ORDER BY month DESC",
            ['pid' => $provider->id]
        );

        return $this->json(['ok' => true, 'byCategory' => $byCategory, 'byMonth' => $byMonth]);
    }

    #[Route('/api/analytics/status', name: 'app_instructor_api_analytics_status', methods: ['GET'])]
    public function apiAnalyticsStatus(EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        $provider = $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]);
        if (!$provider instanceof ClassProvider) {
            return $this->json(['ok' => false, 'error' => 'Not a provider'], Response::HTTP_FORBIDDEN);
        }

        $statuses = $em->getConnection()->fetchAllAssociative(
            "SELECT b.status, COUNT(*) AS count
             FROM bookings b
             JOIN classes c ON c.class_id = b.class_id
             WHERE c.provider_id = :pid
             GROUP BY b.status",
            ['pid' => $provider->id]
        );

        return $this->json(['ok' => true, 'statuses' => $statuses]);
    }

    // ─── Class Students ───────────────────────────────────────────────────────

    #[Route('/class/{classId}/students', name: 'app_instructor_class_students', methods: ['GET'])]
    public function getClassStudents(int $classId, EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $provider = $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]);
        if (!$provider instanceof ClassProvider) {
            return $this->json(['error' => 'Not a provider'], Response::HTTP_FORBIDDEN);
        }

        $ownsClass = (int) $em->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM classes WHERE class_id = :cid AND provider_id = :pid',
            ['cid' => $classId, 'pid' => $provider->id]
        );

        if ($ownsClass === 0) {
            return $this->json(['error' => 'Class not found'], Response::HTTP_NOT_FOUND);
        }

        // FIX 6: SQL column is watch_progress, not progress — match the JS key
        $students = $em->getConnection()->fetchAllAssociative(
            "SELECT u.user_id, u.username, u.full_name, u.email,
                    b.status, b.watch_progress, b.payment_status
             FROM bookings b
             JOIN users u ON u.user_id = b.user_id
             WHERE b.class_id = :cid
             ORDER BY b.booking_date DESC",
            ['cid' => $classId]
        );

        return $this->json(['students' => $students]);
    }

    #[Route('/debug/create-test', name: 'app_instructor_debug_create', methods: ['GET', 'POST'])]
public function debugCreate(Request $request, EntityManagerInterface $em): Response
{
    $user = $this->getUser();
    $provider = $user ? $em->getRepository(ClassProvider::class)->findOneBy(['user' => $user]) : null;

    $info = [
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size'       => ini_get('post_max_size'),
        'public_dir'          => $this->projectDir . '/public',
        'uploads_writable'    => is_writable($this->projectDir . '/public/uploads') ? 'YES' : 'NO (or does not exist)',
        'videos_dir_exists'   => is_dir($this->projectDir . '/public/uploads/videos') ? 'YES' : 'NO',
        'user_logged_in'      => $user ? 'YES (' . $user->getUserIdentifier() . ')' : 'NO',
        'provider_found'      => $provider ? 'YES (id=' . $provider->id . ')' : 'NO',
        'provider_verified'   => $provider ? ($provider->isVerified ? 'YES' : 'NO') : 'N/A',
    ];

    if ($request->isMethod('POST')) {
        $videoFile = $request->files->get('video');
        $thumbFile = $request->files->get('thumbnail');
        $info['POST title']          = $request->request->get('title', '(empty)');
        $info['POST price']          = $request->request->get('price', '(empty)');
        $info['video file received'] = $videoFile ? 'YES' : 'NO';
        $info['video error code']    = $videoFile ? $videoFile->getError() : 'N/A';
        $info['video error msg']     = $videoFile ? $videoFile->getErrorMessage() : 'N/A';
        $info['video size']          = $videoFile ? $videoFile->getSize() . ' bytes' : 'N/A';
        $info['video valid']         = $videoFile ? ($videoFile->isValid() ? 'YES' : 'NO') : 'N/A';
        $info['thumb file received'] = $thumbFile ? 'YES' : 'NO';
        $info['thumb error msg']     = $thumbFile ? $thumbFile->getErrorMessage() : 'N/A';
        $info['thumb size']          = $thumbFile ? $thumbFile->getSize() . ' bytes' : 'N/A';
        $info['CONTENT_LENGTH']      = $_SERVER['CONTENT_LENGTH'] ?? 'not set';
        $info['post_max_bytes']      = $this->parsePhpSize((string) ini_get('post_max_size'));

        try {
            if ($videoFile && $videoFile->isValid()) {
                $path = $this->moveUpload($videoFile, 'videos', 'video');
                $info['video move result'] = 'SUCCESS → ' . $path;
            } else {
                $info['video move result'] = 'SKIPPED (not valid)';
            }
        } catch (\Exception $e) {
            $info['video move result'] = 'FAILED: ' . $e->getMessage();
        }

        try {
            $class = new LearningClass();
            $class->provider        = $provider;
            $class->title           = 'Debug Test Class';
            $class->price           = 9.99;
            $class->duration        = 60;
            $class->maxParticipants = 5;
            $em->persist($class);
            $em->flush();
            $info['DB persist'] = 'SUCCESS — class id=' . $class->id;
        } catch (\Exception $e) {
            $info['DB persist'] = 'FAILED: ' . $e->getMessage();
        }
    }

    $rows = implode('', array_map(
        fn($k, $v) => "<tr><td style='padding:6px 12px;border-bottom:1px solid #eee;font-weight:600;'>{$k}</td><td style='padding:6px 12px;border-bottom:1px solid #eee;font-family:monospace;'>{$v}</td></tr>",
        array_keys($info), array_values($info)
    ));

    return new Response("
        <h2 style='font-family:sans-serif'>🔍 Class Creation Diagnostics</h2>
        <table style='font-family:sans-serif;border-collapse:collapse;width:100%;max-width:800px'>{$rows}</table>
        <hr>
        <h3 style='font-family:sans-serif'>Test POST with files</h3>
        <form method='post' enctype='multipart/form-data' style='font-family:sans-serif'>
            <input type='text' name='title' value='Test Class' style='display:block;margin:4px 0;padding:6px'><br>
            <input type='number' name='price' value='10' style='display:block;margin:4px 0;padding:6px'><br>
            Video: <input type='file' name='video'><br><br>
            Thumb: <input type='file' name='thumbnail'><br><br>
            <button type='submit' style='padding:8px 20px;background:#667eea;color:white;border:none;border-radius:4px;cursor:pointer'>Run Test</button>
        </form>
    ");
}
}