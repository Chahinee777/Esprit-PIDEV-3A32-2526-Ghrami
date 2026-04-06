<?php

namespace App\Controller;

use App\Service\AnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $conn = $em->getConnection();

        $stats = [
            'users' => (int) $conn->fetchOne('SELECT COUNT(*) FROM users'),
            'active_users' => (int) $conn->fetchOne('SELECT COUNT(*) FROM users WHERE is_online = 1'),
            'friendships' => (int) $conn->fetchOne('SELECT COUNT(*) FROM friendships'),
            'classes' => (int) $conn->fetchOne('SELECT COUNT(*) FROM classes'),
            'bookings' => (int) $conn->fetchOne('SELECT COUNT(*) FROM bookings'),
            'revenue' => (float) $conn->fetchOne("SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE payment_status = 'paid'"),
            'posts' => (int) $conn->fetchOne('SELECT COUNT(*) FROM posts'),
            'hobbies' => (int) $conn->fetchOne('SELECT COUNT(*) FROM hobbies'),
            'meetings' => (int) $conn->fetchOne('SELECT COUNT(*) FROM meetings'),
            'badges' => (int) $conn->fetchOne('SELECT COUNT(*) FROM badges'),
        ];

        $users = $conn->fetchAllAssociative(
            'SELECT user_id, username, full_name, email, is_online, is_banned, auth_provider, created_at FROM users ORDER BY user_id DESC LIMIT 100'
        );

        $providers = $conn->fetchAllAssociative(
            'SELECT cp.provider_id, cp.company_name, cp.expertise, cp.rating, cp.is_verified, u.user_id, u.username, u.email
             FROM class_providers cp
             JOIN users u ON u.user_id = cp.user_id
             ORDER BY cp.provider_id DESC'
        );

        $classes = $conn->fetchAllAssociative(
            "SELECT c.class_id, c.title, c.category, c.price, c.duration, c.max_participants,
                    cp.provider_id, u.username AS provider_username,
                    (SELECT COUNT(*) FROM bookings b WHERE b.class_id = c.class_id) AS booking_count
             FROM classes c
             JOIN class_providers cp ON cp.provider_id = c.provider_id
             JOIN users u ON u.user_id = cp.user_id
             ORDER BY c.class_id DESC
             LIMIT 200"
        );

        $bookings = $conn->fetchAllAssociative(
            "SELECT b.booking_id, b.status, b.payment_status, b.total_amount, b.booking_date,
                    c.title AS class_title, su.username AS student_username, pu.username AS provider_username
             FROM bookings b
             JOIN classes c ON c.class_id = b.class_id
             JOIN users su ON su.user_id = b.user_id
             JOIN class_providers cp ON cp.provider_id = c.provider_id
             JOIN users pu ON pu.user_id = cp.user_id
             ORDER BY b.booking_date DESC
             LIMIT 200"
        );

        $friendships = $conn->fetchAllAssociative(
            "SELECT f.friendship_id, f.user1_id, f.user2_id, f.status,
                    u1.username AS user1_username, u2.username AS user2_username
             FROM friendships f
             LEFT JOIN users u1 ON u1.user_id = f.user1_id
             LEFT JOIN users u2 ON u2.user_id = f.user2_id
             ORDER BY f.friendship_id DESC
             LIMIT 200"
        );

        $badges = $conn->fetchAllAssociative(
            "SELECT b.badge_id, b.name AS badge_name, b.description, u.user_id, u.username, b.earned_date AS awarded_at
             FROM badges b
             JOIN users u ON u.user_id = b.user_id
             ORDER BY b.badge_id DESC
             LIMIT 200"
        );

        // Distinct badge names already in DB — drives the Award modal dropdown
        $badgeNames = $conn->fetchFirstColumn(
            'SELECT DISTINCT name FROM badges ORDER BY name ASC'
        );

        // Badge statistics for management
        $badgeStats = $conn->fetchAllAssociative(
            "SELECT name, COUNT(*) AS total_awarded, MAX(earned_date) AS last_awarded
             FROM badges
             GROUP BY name
             ORDER BY total_awarded DESC"
        );

        // Social media: posts with author info and like/comment counts
        $posts = $conn->fetchAllAssociative(
            "SELECT p.post_id, p.content, p.image_url, p.created_at,
                    u.user_id, u.username,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comment_count,
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS like_count
             FROM posts p
             JOIN users u ON u.user_id = p.user_id
             ORDER BY p.post_id DESC
             LIMIT 300"
        );

        // Social media: comments with post and author info
        $comments = $conn->fetchAllAssociative(
            "SELECT c.comment_id, c.content, c.created_at,
                    u.user_id, u.username,
                    p.post_id, SUBSTRING(p.content, 1, 80) AS post_excerpt,
                    pu.username AS post_author
             FROM comments c
             JOIN users u ON u.user_id = c.user_id
             JOIN posts p ON p.post_id = c.post_id
             JOIN users pu ON pu.user_id = p.user_id
             ORDER BY c.comment_id DESC
             LIMIT 300"
        );

        $stats['comments'] = (int) $conn->fetchOne('SELECT COUNT(*) FROM comments');

        $hobbies = $conn->fetchAllAssociative(
            "SELECT h.hobby_id, h.name, h.category, h.description,
                u.user_id, u.username,
                COALESCE((SELECT SUM(p.hours_spent) FROM progress p WHERE p.hobby_id = h.hobby_id), 0) AS total_hours
             FROM hobbies h
             JOIN users u ON u.user_id = h.user_id
             ORDER BY h.hobby_id DESC
             LIMIT 300"
        );

        $meetings = $conn->fetchAllAssociative(
            "SELECT m.meeting_id, m.meeting_type, m.location, m.scheduled_at, m.duration, m.status,
                o.user_id AS organizer_id, o.username AS organizer_username,
                i.username AS initiator_username,
                r.username AS receiver_username,
                (SELECT COUNT(*) FROM meeting_participants mp WHERE mp.meeting_id = m.meeting_id) AS participant_count
             FROM meetings m
             JOIN users o ON o.user_id = m.organizer_id
             LEFT JOIN connections c ON c.connection_id = m.connection_id
             LEFT JOIN users i ON i.user_id = c.initiator_id
             LEFT JOIN users r ON r.user_id = c.receiver_id
             ORDER BY m.scheduled_at DESC
             LIMIT 300"
        );

        return $this->render('admin/index.html.twig', [
            'stats'       => $stats,
            'users'       => $users,
            'providers'   => $providers,
            'classes'     => $classes,
            'bookings'    => $bookings,
            'friendships' => $friendships,
            'badges'      => $badges,
            'badgeNames'  => $badgeNames,
            'badgeStats'  => $badgeStats,
            'posts'       => $posts,
            'comments'    => $comments,
            'hobbies'     => $hobbies,
            'meetings'    => $meetings,
        ]);
    }

    #[Route('/user/toggle-ban', name: 'app_admin_toggle_user_ban', methods: ['POST'])]
    public function toggleUserBan(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_user_toggle', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $userId = (int) $request->request->get('user_id', 0);
        if ($userId > 0) {
            $current = (int) $em->getConnection()->fetchOne('SELECT is_banned FROM users WHERE user_id = :id', ['id' => $userId]);
            $em->getConnection()->update('users', ['is_banned' => $current ? 0 : 1], ['user_id' => $userId]);
            $this->addFlash('success', 'User status updated.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/provider/toggle-verify', name: 'app_admin_toggle_provider_verify', methods: ['POST'])]
    public function toggleProviderVerify(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_provider_toggle', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $providerId = (int) $request->request->get('provider_id', 0);
        if ($providerId > 0) {
            $current = (int) $em->getConnection()->fetchOne('SELECT is_verified FROM class_providers WHERE provider_id = :id', ['id' => $providerId]);
            $em->getConnection()->update('class_providers', ['is_verified' => $current ? 0 : 1], ['provider_id' => $providerId]);
            $this->addFlash('success', 'Provider verification updated.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/class/delete', name: 'app_admin_class_delete', methods: ['POST'])]
    public function deleteClass(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_class_delete', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $classId = (int) $request->request->get('class_id', 0);
        if ($classId > 0) {
            $em->getConnection()->delete('classes', ['class_id' => $classId]);
            $this->addFlash('success', 'Class deleted.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/booking/update-status', name: 'app_admin_booking_update_status', methods: ['POST'])]
    public function updateBookingStatus(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_booking_update', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $bookingId = (int) $request->request->get('booking_id', 0);
        $status = trim((string) $request->request->get('status', ''));
        $paymentStatus = trim((string) $request->request->get('payment_status', ''));
        $allowedStatus = ['pending', 'scheduled', 'completed', 'cancelled'];
        $allowedPayment = ['pending', 'paid', 'failed', 'refunded'];

        if ($bookingId <= 0 || !in_array($status, $allowedStatus, true) || !in_array($paymentStatus, $allowedPayment, true)) {
            $this->addFlash('error', 'Invalid booking update.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $em->getConnection()->update(
            'bookings',
            ['status' => $status, 'payment_status' => $paymentStatus],
            ['booking_id' => $bookingId]
        );

        $this->addFlash('success', 'Booking updated.');
        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/friendship/delete', name: 'app_admin_friendship_delete', methods: ['POST'])]
    public function deleteFriendship(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_friendship_delete', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $friendshipId = (int) $request->request->get('friendship_id', 0);
        if ($friendshipId > 0) {
            $em->getConnection()->delete('friendships', ['friendship_id' => $friendshipId]);
            $this->addFlash('success', 'Friendship deleted.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/export/{type}', name: 'app_admin_export', methods: ['GET'])]
    public function export(string $type, EntityManagerInterface $em): Response
    {
        $conn = $em->getConnection();

        if ($type === 'users') {
            $rows = $conn->fetchAllAssociative('SELECT user_id, username, email, auth_provider, is_online, is_banned, created_at FROM users ORDER BY user_id DESC');
            $headers = ['user_id', 'username', 'email', 'auth_provider', 'is_online', 'is_banned', 'created_at'];
            return $this->csvResponse('admin_users.csv', $headers, $rows);
        }

        if ($type === 'bookings') {
            $rows = $conn->fetchAllAssociative(
                "SELECT b.booking_id, c.title AS class_title, su.username AS student_username, pu.username AS provider_username,
                        b.status, b.payment_status, b.total_amount, b.booking_date
                 FROM bookings b
                 JOIN classes c ON c.class_id = b.class_id
                 JOIN users su ON su.user_id = b.user_id
                 JOIN class_providers cp ON cp.provider_id = c.provider_id
                 JOIN users pu ON pu.user_id = cp.user_id
                 ORDER BY b.booking_date DESC"
            );
            $headers = ['booking_id', 'class_title', 'student_username', 'provider_username', 'status', 'payment_status', 'total_amount', 'booking_date'];
            return $this->csvResponse('admin_bookings.csv', $headers, $rows);
        }

        if ($type === 'revenue') {
            $rows = $conn->fetchAllAssociative(
                "SELECT DATE_FORMAT(booking_date, '%Y-%m') AS period,
                        COUNT(*) AS bookings,
                        SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END) AS paid_revenue
                 FROM bookings
                 GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
                 ORDER BY period DESC"
            );
            $headers = ['period', 'bookings', 'paid_revenue'];
            return $this->csvResponse('admin_revenue.csv', $headers, $rows);
        }

        $this->addFlash('error', 'Unknown export type.');
        return $this->redirectToRoute('app_admin_dashboard');
    }

    private function csvResponse(string $filename, array $headers, array $rows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $line[] = $row[$header] ?? '';
                }
                fputcsv($out, $line);
            }
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }

    #[Route('/analytics-kpi', name: 'app_admin_analytics_kpi', methods: ['GET'])]
    public function getAnalyticsKpi(AnalyticsService $analytics): JsonResponse
    {
        return $this->json($analytics->getAllAnalytics()['kpi']);
    }

    #[Route('/analytics-charts', name: 'app_admin_analytics_charts', methods: ['GET'])]
    public function getAnalyticsCharts(AnalyticsService $analytics): JsonResponse
    {
        $chartData = $analytics->getAllAnalytics()['charts'];
        return $this->json([
            'modulePopulation' => $this->formatChartData($chartData['modulePopulation']),
            'bookingsByStatus' => $this->formatChartData($chartData['bookingsByStatus']),
            'meetingsByStatus' => $this->formatChartData($chartData['meetingsByStatus']),
            'friendshipsByStatus' => $this->formatChartData($chartData['friendshipsByStatus']),
            'topBadges' => $this->formatChartData($chartData['topBadges']),
        ]);
    }

    private function formatChartData(array $data): array
    {
        return [
            'labels' => array_keys($data),
            'data' => array_values($data),
        ];
    }

    #[Route('/badge/delete', name: 'app_admin_badge_delete', methods: ['POST'])]
    public function deleteBadge(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_badge_delete', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $badgeId = (int) $request->request->get('badge_id', 0);
        if ($badgeId > 0) {
            $em->getConnection()->delete('badges', ['badge_id' => $badgeId]);
            $this->addFlash('success', 'Badge deleted.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/user/edit', name: 'app_admin_user_edit', methods: ['POST'])]
    public function editUser(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_user_edit', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $userId = (int) $request->request->get('user_id', 0);
        if ($userId > 0) {
            $username = $request->request->get('username', '');
            $email = $request->request->get('email', '');
            $location = $request->request->get('location', '');
            $password = $request->request->get('password', '');

            $updateData = [
                'username' => $username,
                'email' => $email,
                'location' => $location,
            ];

            if (!empty($password)) {
                $updateData['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
            }

            $em->getConnection()->update('users', $updateData, ['user_id' => $userId]);
            $this->addFlash('success', 'User updated successfully.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/user/delete', name: 'app_admin_user_delete', methods: ['POST'])]
    public function deleteUser(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_user_delete', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $userId = (int) $request->request->get('user_id', 0);
        if ($userId > 0) {
            $conn = $em->getConnection();
            // Delete related data first due to foreign keys
            $conn->delete('badges', ['user_id' => $userId]);
            $conn->delete('hobbies', ['user_id' => $userId]);
            $conn->delete('posts', ['user_id' => $userId]);
            $conn->delete('comments', ['user_id' => $userId]);
            $conn->delete('bookings', ['user_id' => $userId]);
            $conn->delete('class_providers', ['user_id' => $userId]);
            $conn->delete('friendships', ['user1_id' => $userId]);
            $conn->delete('friendships', ['user2_id' => $userId]);
            $conn->delete('users', ['user_id' => $userId]);
            $this->addFlash('success', 'User deleted permanently.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/friendship/edit', name: 'app_admin_friendship_edit', methods: ['POST'])]
    public function editFriendship(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_friendship_edit', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $friendshipId = (int) $request->request->get('friendship_id', 0);
        if ($friendshipId > 0) {
            $status = $request->request->get('status', 'PENDING');
            $em->getConnection()->update('friendships', ['status' => $status], ['friendship_id' => $friendshipId]);
            $this->addFlash('success', 'Friendship status updated.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/badge/award', name: 'app_admin_badge_award', methods: ['POST'])]
    public function awardBadge(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_badge_award', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $userId      = (int) $request->request->get('user_id', 0);
        $badgeName   = trim((string) $request->request->get('badge_name', ''));
        $description = trim((string) $request->request->get('description', ''));

        if ($userId > 0 && !empty($badgeName)) {
            $em->getConnection()->insert('badges', [
                'user_id'     => $userId,
                'name'        => $badgeName,
                'description' => $description,
                'earned_date' => date('Y-m-d H:i:s'),
            ]);
            $this->addFlash('success', 'Badge awarded successfully.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    /* ── Edit Badge (name / description on an existing user badge) ───────────── */

    #[Route('/badge/edit', name: 'app_admin_badge_edit', methods: ['POST'])]
    public function editBadge(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_badge_edit', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $badgeId     = (int) $request->request->get('badge_id', 0);
        $name        = trim((string) $request->request->get('name', ''));
        $description = trim((string) $request->request->get('description', ''));

        if ($badgeId > 0 && !empty($name)) {
            $em->getConnection()->update('badges', [
                'name'        => $name,
                'description' => $description ?: null,
            ], ['badge_id' => $badgeId]);
            $this->addFlash('success', 'Badge updated.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    /* ════════════════════════════════════════════════════════════════════════
       SOCIAL MEDIA MODERATION
    ════════════════════════════════════════════════════════════════════════ */

    /** Delete a post (cascades to its comments and likes via FK). */
    #[Route('/post/delete', name: 'app_admin_post_delete', methods: ['POST'])]
    public function deletePost(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_post_delete', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $postId = (int) $request->request->get('post_id', 0);
        if ($postId > 0) {
            $conn = $em->getConnection();
            // Remove likes and comments first (safety net if FK cascade is off)
            $conn->delete('post_likes', ['post_id' => $postId]);
            $conn->delete('comments',   ['post_id' => $postId]);
            $conn->delete('posts',      ['post_id' => $postId]);
            $this->addFlash('success', 'Post deleted.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    /** Edit a post's content (admin correction / redaction). */
    #[Route('/post/edit', name: 'app_admin_post_edit', methods: ['POST'])]
    public function editPost(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_post_edit', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $postId  = (int) $request->request->get('post_id', 0);
        $content = trim((string) $request->request->get('content', ''));

        if ($postId > 0 && !empty($content)) {
            $em->getConnection()->update('posts', ['content' => $content], ['post_id' => $postId]);
            $this->addFlash('success', 'Post updated.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    /** Delete a single comment. */
    #[Route('/comment/delete', name: 'app_admin_comment_delete', methods: ['POST'])]
    public function deleteComment(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_comment_delete', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $commentId = (int) $request->request->get('comment_id', 0);
        if ($commentId > 0) {
            $em->getConnection()->delete('comments', ['comment_id' => $commentId]);
            $this->addFlash('success', 'Comment deleted.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    /** Edit a comment's content. */
    #[Route('/comment/edit', name: 'app_admin_comment_edit', methods: ['POST'])]
    public function editComment(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_comment_edit', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $commentId = (int) $request->request->get('comment_id', 0);
        $content   = trim((string) $request->request->get('content', ''));

        if ($commentId > 0 && !empty($content)) {
            $em->getConnection()->update('comments', ['content' => $content], ['comment_id' => $commentId]);
            $this->addFlash('success', 'Comment updated.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    /** Export posts to CSV. */
    #[Route('/post/export', name: 'app_admin_post_export', methods: ['GET'])]
    public function exportPosts(EntityManagerInterface $em): Response
    {
        $rows = $em->getConnection()->fetchAllAssociative(
            "SELECT p.post_id, u.username AS author, p.content, p.image_url,
                    (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.post_id) AS comments,
                    (SELECT COUNT(*) FROM post_likes pl WHERE pl.post_id = p.post_id) AS likes,
                    p.created_at
             FROM posts p JOIN users u ON u.user_id = p.user_id
             ORDER BY p.post_id DESC"
        );
        return $this->csvResponse('admin_posts.csv',
            ['post_id', 'author', 'content', 'image_url', 'comments', 'likes', 'created_at'],
            $rows
        );
    }

    /**
     * Revoke all instances of a badge by name (removes it from every user who holds it).
     * Useful when a badge type is retired or was awarded in error en-masse.
     */
    #[Route('/badge/revoke-by-name', name: 'app_admin_badge_revoke_by_name', methods: ['POST'])]
    public function revokeBadgeByName(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_badge_revoke_name', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $badgeName = trim((string) $request->request->get('badge_name', ''));
        if (!empty($badgeName)) {
            $affected = $em->getConnection()->executeStatement(
                'DELETE FROM badges WHERE name = :name',
                ['name' => $badgeName]
            );
            $this->addFlash('success', sprintf('Revoked «%s» from %d user(s).', $badgeName, $affected));
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/hobby/delete', name: 'app_admin_hobby_delete', methods: ['POST'])]
    public function deleteHobby(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_hobby_delete', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $hobbyId = (int) $request->request->get('hobby_id', 0);
        if ($hobbyId > 0) {
            $conn = $em->getConnection();
            $conn->delete('progress_log', ['hobby_id' => $hobbyId]);
            $conn->delete('progress', ['hobby_id' => $hobbyId]);
            $conn->delete('milestones', ['hobby_id' => $hobbyId]);
            $conn->delete('hobbies', ['hobby_id' => $hobbyId]);
            $this->addFlash('success', 'Hobby deleted.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }

    #[Route('/meeting/delete', name: 'app_admin_meeting_delete', methods: ['POST'])]
    public function deleteMeeting(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('admin_meeting_delete', (string) $request->request->get('_csrf_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        $meetingId = trim((string) $request->request->get('meeting_id', ''));
        if ($meetingId !== '') {
            $conn = $em->getConnection();
            $conn->delete('meeting_participants', ['meeting_id' => $meetingId]);
            $conn->delete('meetings', ['meeting_id' => $meetingId]);
            $this->addFlash('success', 'Meeting deleted.');
        }

        return $this->redirectToRoute('app_admin_dashboard');
    }
}