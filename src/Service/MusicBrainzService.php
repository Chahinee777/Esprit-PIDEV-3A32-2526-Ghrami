<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class MusicBrainzService
{
    private HttpClientInterface $httpClient;
    private const MUSICBRAINZ_API = 'https://musicbrainz.org/ws/2';

    /**
     * Mapping of hobby categories to music search terms
     */
    private const HOBBY_TO_MUSIC_MAP = [
        'Sports & Fitness' => ['workout', 'gym', 'running', 'sports'],
        'Arts & Crafts' => ['creative', 'art', 'indie', 'alternative'],
        'Music' => ['musical', 'instrumental', 'classical', 'jazz'],
        'Cooking' => ['upbeat', 'jazz', 'funk', 'soul'],
        'Gaming' => ['electronic', 'gaming', 'video game', 'synthwave'],
        'Reading' => ['acoustic', 'ambient', 'lo-fi', 'indie'],
        'Technology' => ['electronic', 'synth', 'techno', 'experimental'],
        'Photography' => ['ambient', 'chill', 'indie', 'lo-fi'],
        'Gardening' => ['ambient', 'nature', 'acoustic', 'folk'],
        'Writing' => ['lo-fi', 'ambient', 'indie', 'acoustic'],
        'Learning Languages' => ['world', 'folk', 'ethnic', 'cultural'],
        'Dancing' => ['dance', 'electronic', 'hip hop', 'pop'],
        'Traveling' => ['world music', 'folk', 'ethnic', 'reggae'],
        'Other' => ['chill', 'ambient', 'indie', 'alternative'],
    ];

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Get music recommendations based on hobby
     */
    public function getRecommendations(string $hobbyName, string $hobbyCategory = ''): array
    {
        try {
            // Determine search terms
            $searchTerms = $this->getSearchTerms($hobbyName, $hobbyCategory);
            $recommendations = [];

            // Search for recordings
            foreach ($searchTerms as $term) {
                $results = $this->searchRecordings($term);
                if (!empty($results)) {
                    $recommendations = array_merge($recommendations, $results);
                    // Limit to 15 total results
                    if (count($recommendations) >= 15) {
                        $recommendations = array_slice($recommendations, 0, 15);
                        break;
                    }
                }
            }

            return [
                'success' => true,
                'hobby' => $hobbyName,
                'category' => $hobbyCategory,
                'recommendations' => $recommendations,
                'count' => count($recommendations),
            ];
        } catch (\Exception $e) {
            error_log("MusicBrainz error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Unable to fetch music recommendations',
                'hobby' => $hobbyName,
            ];
        }
    }

    /**
     * Get search terms based on hobby
     */
    private function getSearchTerms(string $hobbyName, string $hobbyCategory): array
    {
        $terms = [];

        // Add category-based terms
        if (isset(self::HOBBY_TO_MUSIC_MAP[$hobbyCategory])) {
            $terms = self::HOBBY_TO_MUSIC_MAP[$hobbyCategory];
        } else {
            $terms = self::HOBBY_TO_MUSIC_MAP['Other'];
        }

        // Add hobby name as primary search term
        array_unshift($terms, trim($hobbyName));

        return array_slice($terms, 0, 3); // Limit to 3 searches
    }

    /**
     * Search for recordings on MusicBrainz
     */
    private function searchRecordings(string $query): array
    {
        try {
            $response = $this->httpClient->request('GET', self::MUSICBRAINZ_API . '/recording', [
                'query' => [
                    'query' => $query,
                    'limit' => 10,
                    'offset' => 0,
                    'fmt' => 'json',
                ],
                'headers' => [
                    'User-Agent' => 'Ghrami/1.0 (Music Recommendation Service)',
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            $data = $response->toArray();
            return $this->parseRecordings($data);
        } catch (\Exception $e) {
            error_log("MusicBrainz search error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Parse MusicBrainz API response
     */
    private function parseRecordings(array $data): array
    {
        $recordings = [];

        if (!isset($data['recordings']) || empty($data['recordings'])) {
            return [];
        }

        foreach ($data['recordings'] as $recording) {
            $tracks = [];

            // Get track info
            if (isset($recording['title'])) {
                $tracks[] = [
                    'title' => $recording['title'],
                    'artists' => $this->getArtistNames($recording),
                    'id' => $recording['id'] ?? null,
                    'date' => $recording['first-release-date'] ?? 'N/A',
                    'score' => $recording['score'] ?? 0,
                ];
            }

            // Get media (albums) info if available
            if (isset($recording['releases']) && !empty($recording['releases'])) {
                foreach ($recording['releases'] as $release) {
                    if (isset($release['title']) && !in_array($release['title'], array_column($tracks, 'title'))) {
                        $tracks[] = [
                            'title' => $release['title'] ?? 'Unknown',
                            'artists' => $this->getArtistNames($release),
                            'id' => $release['id'] ?? null,
                            'date' => $release['date'] ?? 'N/A',
                            'score' => $recording['score'] ?? 0,
                        ];
                    }
                }
            }

            $recordings = array_merge($recordings, $tracks);
        }

        // Remove duplicates and sort by score
        $recordings = array_unique($recordings, SORT_REGULAR);
        usort($recordings, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return array_slice($recordings, 0, 10);
    }

    /**
     * Extract artist names from MusicBrainz data
     */
    private function getArtistNames(array $data): string
    {
        $artists = [];

        if (isset($data['artist-credit'])) {
            foreach ($data['artist-credit'] as $credit) {
                if (is_array($credit) && isset($credit['artist']['name'])) {
                    $artists[] = $credit['artist']['name'];
                } elseif (isset($credit['name'])) {
                    $artists[] = $credit['name'];
                }
            }
        } elseif (isset($data['artists']) && is_array($data['artists'])) {
            foreach ($data['artists'] as $artist) {
                if (is_array($artist) && isset($artist['name'])) {
                    $artists[] = $artist['name'];
                } elseif (is_string($artist)) {
                    $artists[] = $artist;
                }
            }
        }

        return !empty($artists) ? implode(', ', array_slice($artists, 0, 2)) : 'Unknown Artist';
    }

    /**
     * Generate YouTube search URL for a track
     */
    public function getYouTubeSearchUrl(string $trackTitle, string $artistName): string
    {
        $query = urlencode(trim($trackTitle . ' ' . $artistName));
        return "https://www.youtube.com/results?search_query={$query}";
    }
}
