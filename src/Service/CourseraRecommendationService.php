<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class CourseraRecommendationService
{
    private const BASE_URL = 'https://api.coursera.org/api/courses.v1';

    public function __construct(private HttpClientInterface $httpClient) {}

    public function getRecommendations(string $hobbyName, string $category = ''): array
    {
        try {
            $query = $hobbyName . ' ' . $category;

            $response = $this->httpClient->request('GET', self::BASE_URL, [
                'query' => [
                    'q'      => 'search',
                    'query'  => $query,
                    'fields' => 'name,slug,photoUrl,description,partnerIds,workload,primaryLanguages',
                    'limit'  => 6,
                    'includes' => 'partnerIds',
                ],
            ]);

            $data = $response->toArray();
            $courses = [];

            foreach ($data['elements'] ?? [] as $course) {
                $courses[] = [
                    'title'       => $course['name'] ?? 'Unknown Course',
                    'description' => isset($course['description'])
                        ? substr($course['description'], 0, 150) . '...'
                        : 'No description available.',
                    'photo'       => $course['photoUrl'] ?? null,
                    'workload'    => $course['workload'] ?? 'Self-paced',
                    'language'    => $course['primaryLanguages'][0] ?? 'English',
                    'url'         => isset($course['slug'])
                        ? 'https://www.coursera.org/learn/' . $course['slug']
                        : 'https://www.coursera.org',
                ];
            }

            return [
                'success' => true,
                'hobby'   => $hobbyName,
                'courses' => $courses,
                'total'   => count($courses),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'courses' => [],
            ];
        }
    }
}
