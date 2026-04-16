<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\ConnectionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/connections')]
final class ConnectionsApiController extends AbstractController
{
    public function __construct(
        private readonly ConnectionService $connectionService,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * Quick connect (from discovery cards).
     */
    #[Route('/quick-connect', name: 'api_connections_quick_connect', methods: ['POST'])]
    public function quickConnect(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }


        $data = json_decode($request->getContent(), true);
        $receiverId = (int) ($data['receiver_id'] ?? 0);
        $type = $data['type'] ?? 'General';
        $initiatorSkill = $data['initiator_skill'] ?? null;
        $receiverSkill = $data['receiver_skill'] ?? null;

        if ($receiverId === 0 || $receiverId === (int) $user->id) {
            return $this->json(['ok' => false, 'error' => 'Invalid user'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $connection = $this->connectionService->createConnection(
                (int) $user->id,
                $receiverId,
                $type,
                $initiatorSkill,
                $receiverSkill
            );

            return $this->json([
                'ok' => true,
                'connection_id' => $connection->id,
                'message' => 'Connection request sent!'
            ]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Create connection by user ID lookup.
     */
    #[Route('/create', name: 'api_connections_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $userId = (int) ($data['user_id'] ?? 0);
        $type = $data['type'] ?? 'General';
        $initiatorSkill = $data['initiator_skill'] ?? null;
        $receiverSkill = $data['receiver_skill'] ?? null;

        if ($userId === 0) {
            return $this->json(['ok' => false, 'error' => 'User ID required'], Response::HTTP_BAD_REQUEST);
        }

        if ($userId === (int) $user->id) {
            return $this->json(['ok' => false, 'error' => 'Cannot connect to yourself'], Response::HTTP_BAD_REQUEST);
        }

        // Verify user exists
        $receiver = $this->em->getRepository(User::class)->find($userId);
        if (!$receiver) {
            return $this->json(['ok' => false, 'error' => 'User not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if already connected
        $existing = $this->em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM connections
             WHERE (initiator_id = :uid1 AND receiver_id = :uid2)
                OR (initiator_id = :uid2 AND receiver_id = :uid1)",
            ['uid1' => (int) $user->id, 'uid2' => $userId]
        );

        if ($existing > 0) {
            return $this->json(['ok' => false, 'error' => 'Already connected'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $connection = $this->connectionService->createConnection(
                (int) $user->id,
                $userId,
                $type,
                $initiatorSkill,
                $receiverSkill
            );

            return $this->json(['ok' => true, 'connection_id' => $connection->id]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Look up user by ID (for connection form validation).
     */
    #[Route('/lookup-user/{userId}', name: 'api_connections_lookup_user', methods: ['GET'])]
    public function lookupUser(int $userId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        if ($userId === (int) $user->id) {
            return $this->json(['ok' => false, 'error' => 'Cannot connect to yourself']);
        }

        $receiver = $this->em->getRepository(User::class)->find($userId);
        if (!$receiver) {
            return $this->json(['ok' => false, 'error' => 'User not found']);
        }

        return $this->json([
            'ok' => true,
            'user_id' => $receiver->id,
            'username' => $receiver->username,
            'full_name' => $receiver->fullName,
        ]);
    }

    /**
     * Accept connection request.
     */
    #[Route('/{connectionId}/accept', name: 'api_connections_accept', methods: ['POST'])]
    public function accept(string $connectionId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->connectionService->acceptConnection($connectionId, (int) $user->id);
            return $this->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Reject connection request.
     */
    #[Route('/{connectionId}/reject', name: 'api_connections_reject', methods: ['POST'])]
    public function reject(string $connectionId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->connectionService->rejectConnection($connectionId, (int) $user->id);
            return $this->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Delete connection.
     */
    #[Route('/{connectionId}/delete', name: 'api_connections_delete', methods: ['POST'])]
    public function delete(string $connectionId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $this->connectionService->deleteConnection($connectionId, (int) $user->id);
            return $this->json(['ok' => true]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
