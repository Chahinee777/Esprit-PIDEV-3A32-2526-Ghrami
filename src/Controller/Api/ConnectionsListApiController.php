<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Service\ConnectionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/connections')]
final class ConnectionsListApiController extends AbstractController
{
    public function __construct(private readonly ConnectionService $connectionService) {}

    /**
     * Get all connections for current user.
     */
    #[Route('', name: 'api_connections_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $connections = $this->connectionService->getConnections((int) $user->id);
            $pending = $this->connectionService->getIncomingPendingConnections((int) $user->id);

            return $this->json([
                'ok' => true,
                'connections' => $connections,
                'pending' => $pending
            ]);
        } catch (\Exception $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
