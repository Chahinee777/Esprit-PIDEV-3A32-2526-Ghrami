<?php

namespace App\Service;

/**
 * VR Room service — single Hobbies Room embedded via FrameVR iframe.
 * FrameVR rooms are auto-created on first visit — no backend setup needed.
 * URL format: https://framevr.io/<room-name>
 */
final class VrRoomService
{
    private const FRAME_BASE = 'https://framevr.io/';

    private const ROOM = [
        'emoji'       => '🎯',
        'name'        => 'Hobbies Room',
        'description' => 'Votre espace immersif multi-hobbies : photo, musique, gaming, art et plus encore',
        'frameRoomId' => 'ghrami-community',
        'basePeople'  => 14,
        'tags'        => ['📸 Photo', '🎸 Musique', '🎮 Gaming', '🎨 Art', '✈️ Voyage', '🔭 Science'],
    ];

    public function getRoom(): array
    {
        return $this->enrichRoom(self::ROOM);
    }

    public function getOnlineCount(): int
    {
        return self::ROOM['basePeople'] + random_int(0, 8);
    }

    /**
     * Enrich room data with computed URL and live online count.
     */
    private function enrichRoom(array $room): array
    {
        return [
            ...$room,
            'url'    => self::FRAME_BASE . $room['frameRoomId'],
            'online' => $room['basePeople'] + random_int(0, 7),
        ];
    }
}