<?php

namespace App\Service;

/**
 * VR Room service — provides catalog of FrameVR rooms and room management.
 * Mirrors desktop VRRoomsController.java functionality.
 *
 * FrameVR rooms are auto-created on first visit — no backend setup needed.
 * URL format: https://framevr.io/<room-name>
 */
final class VrRoomService
{
    private const FRAME_BASE = 'https://framevr.io/';

    /**
     * @var array<string, array{emoji: string, name: string, description: string, frameRoomId: string, basePeople: int}>
     */
    private array $rooms;

    public function __construct()
    {
        $this->initializeRooms();
    }

    private function initializeRooms(): void
    {
        $this->rooms = [
            'photo' => [
                'emoji' => '📸',
                'name' => 'Photographie',
                'description' => 'Galerie & atelier photo 360°',
                'frameRoomId' => 'ghrami-photo',
                'basePeople' => 12,
            ],
            'music' => [
                'emoji' => '🎸',
                'name' => 'Musique',
                'description' => 'Scène live & jam session',
                'frameRoomId' => 'ghrami-music',
                'basePeople' => 8,
            ],
            'lecture' => [
                'emoji' => '📚',
                'name' => 'Club de Lecture',
                'description' => 'Bibliothèque virtuelle & discussion',
                'frameRoomId' => 'ghrami-lecture',
                'basePeople' => 6,
            ],
            'sport' => [
                'emoji' => '🏃',
                'name' => 'Sport & Fitness',
                'description' => 'Coaching et défi sportif en VR',
                'frameRoomId' => 'ghrami-sport',
                'basePeople' => 15,
            ],
            'gaming' => [
                'emoji' => '🎮',
                'name' => 'Gaming Lounge',
                'description' => 'Salon jeux vidéo & esport',
                'frameRoomId' => 'ghrami-gaming',
                'basePeople' => 20,
            ],
            'art' => [
                'emoji' => '🎨',
                'name' => 'Art & Dessin',
                'description' => 'Studio créatif collaboratif',
                'frameRoomId' => 'ghrami-art',
                'basePeople' => 5,
            ],
            'cuisine' => [
                'emoji' => '🍳',
                'name' => 'Cuisine',
                'description' => 'Échange de recettes en immersion',
                'frameRoomId' => 'ghrami-cuisine',
                'basePeople' => 4,
            ],
            'tech' => [
                'emoji' => '💻',
                'name' => 'Tech & Dev',
                'description' => 'Hackspace et conférences tech',
                'frameRoomId' => 'ghrami-tech',
                'basePeople' => 11,
            ],
            'voyage' => [
                'emoji' => '✈️',
                'name' => 'Voyage',
                'description' => 'Destinations du monde en VR',
                'frameRoomId' => 'ghrami-voyage',
                'basePeople' => 9,
            ],
            'cinema' => [
                'emoji' => '🎭',
                'name' => 'Théâtre & Cinéma',
                'description' => 'Séances de cinéma & impro théâtre',
                'frameRoomId' => 'ghrami-cinema',
                'basePeople' => 7,
            ],
            'nature' => [
                'emoji' => '🌿',
                'name' => 'Nature & Yoga',
                'description' => 'Espaces de méditation et relaxation',
                'frameRoomId' => 'ghrami-nature',
                'basePeople' => 3,
            ],
            'science' => [
                'emoji' => '🔭',
                'name' => 'Science',
                'description' => 'Planétarium & lab virtuel',
                'frameRoomId' => 'ghrami-science',
                'basePeople' => 6,
            ],
        ];
    }

    /**
     * Get all rooms.
     *
     * @return array<int, array{emoji: string, name: string, description: string, frameRoomId: string, basePeople: int, url: string, online: int}>
     */
    public function getAllRooms(): array
    {
        $rooms = [];
        foreach ($this->rooms as $key => $room) {
            $rooms[$key] = $this->enrichRoom($room);
        }
        return $rooms;
    }

    /**
     * Get a specific room by ID.
     *
     * @return array{emoji: string, name: string, description: string, frameRoomId: string, basePeople: int, url: string, online: int}|null
     */
    public function getRoom(string $roomId): ?array
    {
        if (!isset($this->rooms[$roomId])) {
            return null;
        }

        return $this->enrichRoom($this->rooms[$roomId]);
    }

    /**
     * Search rooms by name or description.
     *
     * @return array<int, array{emoji: string, name: string, description: string, frameRoomId: string, basePeople: int, url: string, online: int}>
     */
    public function searchRooms(string $query): array
    {
        $query = strtolower(trim($query));
        if (empty($query)) {
            return $this->getAllRooms();
        }

        $results = [];
        foreach ($this->rooms as $key => $room) {
            if (
                str_contains(strtolower($room['name']), $query)
                || str_contains(strtolower($room['description']), $query)
                || str_contains(strtolower($room['frameRoomId']), $query)
            ) {
                $results[$key] = $this->enrichRoom($room);
            }
        }

        return $results;
    }

    /**
     * Get total online count across all rooms.
     */
    public function getTotalOnlineCount(): int
    {
        $total = 0;
        foreach ($this->rooms as $room) {
            $total += $room['basePeople'] + random_int(0, 5);
        }
        return $total;
    }

    /**
     * Enrich room data with computed fields (URL, online count).
     *
     * @param array{emoji: string, name: string, description: string, frameRoomId: string, basePeople: int} $room
     * @return array{emoji: string, name: string, description: string, frameRoomId: string, basePeople: int, url: string, online: int}
     */
    private function enrichRoom(array $room): array
    {
        return [
            ...$room,
            'url' => self::FRAME_BASE . $room['frameRoomId'],
            'online' => $room['basePeople'] + random_int(0, 7),
        ];
    }
}
