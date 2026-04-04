<?php

namespace App\Controller;

use App\Entity\Connection;
use App\Entity\User;
use App\Service\ConnectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/connections')]
final class ConnectionController extends AbstractController
{
    #[Route('', name: 'app_connections_index', methods: ['GET'])]
    public function index(ConnectionService $connectionService): Response
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->redirectToRoute('app_auth_login');
        }

        $userId = (int) $currentUser->id;
        return $this->render('connections/index.html.twig', [
            'accepted' => $connectionService->getAcceptedConnections($userId),
            'pendingIncoming' => $connectionService->getIncomingPendingConnections($userId),
            'pendingOutgoing' => $connectionService->getOutgoingPendingConnections($userId),
            'all' => $connectionService->getConnections($userId),
        ]);
    }

    #[Route('/api/create', name: 'app_connections_api_create', methods: ['POST'])]
    public function apiCreate(Request $request, ConnectionService $connectionService, ValidatorInterface $validator): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $receiverId = (int) $request->request->get('receiver_id');
        $type = (string) $request->request->get('type', 'Mentor');
        $initiatorSkill = trim((string) $request->request->get('initiator_skill', ''));
        $receiverSkill = trim((string) $request->request->get('receiver_skill', ''));

        if ($receiverId <= 0) {
            return $this->json([
                'ok' => false,
                'error' => 'Validation failed.',
                'errors' => ['receiver_id' => 'Receiver is required.'],
            ], Response::HTTP_BAD_REQUEST);
        }

        if ((int) $currentUser->id === $receiverId) {
            return $this->json([
                'ok' => false,
                'error' => 'Validation failed.',
                'errors' => ['receiver_id' => 'You cannot connect with yourself.'],
            ], Response::HTTP_BAD_REQUEST);
        }

        $connectionValidation = new Connection();
        $connectionValidation->connectionType = $type;
        $connectionValidation->initiatorSkill = $initiatorSkill !== '' ? $initiatorSkill : null;
        $connectionValidation->receiverSkill = $receiverSkill !== '' ? $receiverSkill : null;
        $connectionValidation->status = 'pending';

        $violations = $validator->validate($connectionValidation);
        if (count($violations) > 0) {
            return $this->json([
                'ok' => false,
                'error' => 'Validation failed.',
                'errors' => $this->normalizeValidationErrors($violations),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $connection = $connectionService->createConnection(
                (int) $currentUser->id,
                $receiverId,
                $type,
                $initiatorSkill !== '' ? $initiatorSkill : null,
                $receiverSkill !== '' ? $receiverSkill : null
            );

            return $this->json([
                'ok' => true,
                'connectionId' => $connection->id,
                'message' => 'Connection request sent!',
            ]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/list', name: 'app_connections_api_list', methods: ['GET'])]
    public function apiList(ConnectionService $connectionService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $connections = $connectionService->getConnections((int) $currentUser->id);
        return $this->json(['ok' => true, 'connections' => $connections]);
    }

    #[Route('/api/accepted', name: 'app_connections_api_accepted', methods: ['GET'])]
    public function apiAccepted(ConnectionService $connectionService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $connections = $connectionService->getAcceptedConnections((int) $currentUser->id);
        return $this->json(['ok' => true, 'connections' => $connections]);
    }

    #[Route('/api/pending', name: 'app_connections_api_pending', methods: ['GET'])]
    public function apiPending(ConnectionService $connectionService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $pending = $connectionService->getPendingConnections((int) $currentUser->id);
        return $this->json(['ok' => true, 'pending' => $pending, 'count' => count($pending)]);
    }

    #[Route('/api/accept/{connectionId}', name: 'app_connections_api_accept', methods: ['POST'])]
    public function apiAccept(string $connectionId, ConnectionService $connectionService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $connectionService->acceptConnection($connectionId, (int) $currentUser->id);
            return $this->json(['ok' => true, 'message' => 'Connection accepted!']);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/reject/{connectionId}', name: 'app_connections_api_reject', methods: ['POST'])]
    public function apiReject(string $connectionId, ConnectionService $connectionService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $connectionService->rejectConnection($connectionId, (int) $currentUser->id);
            return $this->json(['ok' => true, 'message' => 'Connection rejected']);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/cancel/{connectionId}', name: 'app_connections_api_cancel', methods: ['POST'])]
    public function apiCancel(string $connectionId, ConnectionService $connectionService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $connectionService->cancelOutgoingConnection($connectionId, (int) $currentUser->id);
            return $this->json(['ok' => true, 'message' => 'Connection request cancelled']);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/api/search', name: 'app_connections_api_search', methods: ['GET'])]
    public function apiSearch(Request $request, ConnectionService $connectionService): JsonResponse
    {
        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            return $this->json(['ok' => false, 'error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $query = (string) $request->query->get('q', '');
        $type = (string) $request->query->get('type', '');

        if (strlen($query) < 2) {
            return $this->json(['ok' => false, 'error' => 'Query too short'], Response::HTTP_BAD_REQUEST);
        }

        $results = $connectionService->searchConnections((int) $currentUser->id, $query, $type);
        return $this->json(['ok' => true, 'results' => $results]);
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
