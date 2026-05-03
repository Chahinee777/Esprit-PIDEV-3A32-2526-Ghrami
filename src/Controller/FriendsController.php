<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\MatchingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/friends')]
final class FriendsController extends AbstractController
{
    #[Route('', name: 'app_friends_index', methods: ['GET'])]
    public function index(Request $request, MatchingService $matchingService): Response
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->redirectToRoute('app_login');
        }

        $userId = (int) $currentUser->id;

        // Get all users for browsing
        $allUsers = $matchingService->getAllUsers();
        $browseUsers = array_filter($allUsers, function ($u) use ($userId) {
            return $u['user_id'] !== $userId;
        });

        // Get accepted friendships
        $acceptedFriendships = $matchingService->getAcceptedFriendships($userId);
        $acceptedFriendIds = [];
        $friendshipMap = [];
        foreach ($acceptedFriendships as $f) {
            $otherUserId = $f['user1_id'] == $userId ? $f['user2_id'] : $f['user1_id'];
            $acceptedFriendIds[] = $otherUserId;
            $friendshipMap[$otherUserId] = $f['friendship_id'];
        }

        // Get accepted friends with details
        $acceptedFriendsDetails = [];
        if (!empty($acceptedFriendIds)) {
            $friends = $matchingService->getUsersById($acceptedFriendIds);
            // Add friendship_id to each friend
            foreach ($friends as &$friend) {
                $friend['friendship_id'] = $friendshipMap[$friend['user_id']] ?? null;
            }
            $acceptedFriendsDetails = $friends;
        }

        // Get pending requests (where current user is recipient)
        $pendingRequests = $matchingService->getPendingRequestsForUser($userId);

        // Get pending request senders
        $pendingRequestSenderIds = array_column($pendingRequests, 'user1_id');
        $pendingRequestSenders = [];
        if (!empty($pendingRequestSenderIds)) {
            $pendingRequestSenders = $matchingService->getUsersById($pendingRequestSenderIds);
        }

        // Get outgoing pending requests (sent BY current user)
        $outgoingPendingRequests = $matchingService->getOutgoingPendingRequests($userId);
        $outgoingPendingUserIds = array_column($outgoingPendingRequests, 'user2_id');

        // Build user friendship status map for template
        $userFriendshipStatus = [];
        foreach ($browseUsers as $user) {
            $userId = $user['user_id'];
            if (in_array($userId, $acceptedFriendIds)) {
                $userFriendshipStatus[$userId] = 'ACCEPTED';
            } elseif (in_array($userId, $pendingRequestSenderIds)) {
                $userFriendshipStatus[$userId] = 'PENDING_INCOMING';
            } elseif (in_array($userId, $outgoingPendingUserIds)) {
                $userFriendshipStatus[$userId] = 'PENDING_OUTGOING';
            }
        }

        return $this->render('friends/index.html.twig', [
            'currentUser' => $currentUser,
            'userId' => $userId,
            'browseUsers' => $browseUsers,
            'userFriendshipStatus' => $userFriendshipStatus,
            'acceptedFriends' => $acceptedFriendsDetails,
            'pendingRequests' => $pendingRequests,
            'pendingRequestSenders' => $pendingRequestSenders,
            'friendsCount' => count($acceptedFriendships),
            'pendingCount' => count($pendingRequests),
            'totalUsersCount' => count($allUsers) - 1, // Exclude current user
        ]);
    }

    #[Route('/send-request/{receiverId}', name: 'app_friends_send_request', methods: ['POST'])]
    public function sendFriendRequest(int $receiverId, MatchingService $matchingService): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $matchingService->sendFriendRequest((int) $currentUser->id, $receiverId);
            return $this->json(['success' => true, 'message' => 'Friend request sent']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/cancel-request/{friendshipId}', name: 'app_friends_cancel_request', methods: ['POST'])]
    public function cancelFriendRequest(int $friendshipId, MatchingService $matchingService): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $matchingService->deleteFriendship($friendshipId);
            return $this->json(['success' => true, 'message' => 'Request cancelled']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/accept/{friendshipId}', name: 'app_friends_accept', methods: ['POST'])]
    public function acceptFriendRequest(int $friendshipId, MatchingService $matchingService): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $matchingService->acceptFriendship($friendshipId);
            return $this->json(['success' => true, 'message' => 'Friend request accepted']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/reject/{friendshipId}', name: 'app_friends_reject', methods: ['POST'])]
    public function rejectFriendRequest(int $friendshipId, MatchingService $matchingService): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $matchingService->rejectFriendship($friendshipId);
            return $this->json(['success' => true, 'message' => 'Friend request rejected']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/remove/{friendshipId}', name: 'app_friends_remove', methods: ['POST'])]
    public function removeFriend(int $friendshipId, MatchingService $matchingService): JsonResponse
    {
        /** @var User|null $currentUser */
        $currentUser = $this->getUser();
        if (!$currentUser) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $matchingService->deleteFriendship($friendshipId);
            return $this->json(['success' => true, 'message' => 'Friend removed']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
