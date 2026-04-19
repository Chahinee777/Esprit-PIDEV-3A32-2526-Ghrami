<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class BookRecommendationService
{
    private const BASE_URL = 'https://openlibrary.org';

    public function __construct(private HttpClientInterface $httpClient) {}

    public function getRecommendations(string $hobbyName, string $category = ''): array
    {
        try {
            $query = urlencode($hobbyName . ' ' . $category);
            $response = $this->httpClient->request('GET', self::BASE_URL . '/search.json', [
                'query' => [
                    'q'      => $query,
                    'fields' => 'title,author_name,cover_i,first_publish_year,subject',
                    'limit'  => 6,
                ],
            ]);

            $data = $response->toArray();
            $books = [];

            foreach ($data['docs'] ?? [] as $book) {
                $books[] = [
                    'title'        => $book['title'] ?? 'Unknown Title',
                    'author'       => $book['author_name'][0] ?? 'Unknown Author',
                    'year'         => $book['first_publish_year'] ?? null,
                    'cover_url'    => isset($book['cover_i'])
                        ? 'https://covers.openlibrary.org/b/id/' . $book['cover_i'] . '-M.jpg'
                        : null,
                    'subjects'     => array_slice($book['subject'] ?? [], 0, 3),
                    'open_library' => 'https://openlibrary.org/search?q=' . $query,
                ];
            }

            return [
                'success' => true,
                'hobby'   => $hobbyName,
                'books'   => $books,
                'total'   => count($books),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error'   => $e->getMessage(),
                'books'   => [],
            ];
        }
    }
}
