<?php

namespace App\Controller;

use App\Service\VrRoomService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vr-rooms')]
final class VrRoomController extends AbstractController
{
    #[Route('', name: 'app_vr_rooms', methods: ['GET'])]
    public function index(VrRoomService $vrRoomService): Response
    {
        $rooms = $vrRoomService->getAllRooms();
        $onlineCount = $vrRoomService->getTotalOnlineCount();

        return $this->render('vr_rooms/index.html.twig', [
            'rooms' => $rooms,
            'onlineCount' => $onlineCount,
        ]);
    }

    #[Route('/api/all', name: 'app_vr_rooms_api_all', methods: ['GET'])]
    public function apiGetAll(VrRoomService $vrRoomService): JsonResponse
    {
        return $this->json([
            'rooms' => $vrRoomService->getAllRooms(),
            'onlineCount' => $vrRoomService->getTotalOnlineCount(),
        ]);
    }

    #[Route('/api/search', name: 'app_vr_rooms_api_search', methods: ['POST', 'GET'])]
    public function apiSearch(Request $request, VrRoomService $vrRoomService): JsonResponse
    {
        $query = $request->query->get('q', '') ?? $request->request->get('q', '');
        $results = $vrRoomService->searchRooms($query);

        return $this->json([
            'query' => $query,
            'rooms' => $results,
            'count' => count($results),
        ]);
    }

    #[Route('/api/room/{roomId}', name: 'app_vr_rooms_api_get_room', methods: ['GET'])]
    public function apiGetRoom(string $roomId, VrRoomService $vrRoomService): JsonResponse
    {
        $room = $vrRoomService->getRoom($roomId);

        if (null === $room) {
            return $this->json(['error' => 'Room not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'room' => $room,
            'onlineCount' => $vrRoomService->getTotalOnlineCount(),
        ]);
    }
}
