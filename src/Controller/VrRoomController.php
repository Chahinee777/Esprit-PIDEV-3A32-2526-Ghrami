<?php

namespace App\Controller;

use App\Service\VrRoomService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/vr-rooms')]
final class VrRoomController extends AbstractController
{
    #[Route('', name: 'app_vr_rooms', methods: ['GET'])]
    public function index(VrRoomService $vrRoomService): Response
    {
        return $this->render('vr_rooms/index.html.twig', [
            'room'        => $vrRoomService->getRoom(),
            'onlineCount' => $vrRoomService->getOnlineCount(),
        ]);
    }

    #[Route('/api/room', name: 'app_vr_rooms_api_room', methods: ['GET'])]
    public function apiGetRoom(VrRoomService $vrRoomService): JsonResponse
    {
        return $this->json([
            'room'        => $vrRoomService->getRoom(),
            'onlineCount' => $vrRoomService->getOnlineCount(),
        ]);
    }
}