<?php

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Service\AiNotificationService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AiNotificationServiceTest extends TestCase
{
    private AiNotificationService $service;
    private EntityManagerInterface $em;
    private HttpClientInterface $httpClient;
    private Connection $connection;
    private string $groqApiKey = 'test-key';

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->connection = $this->createMock(Connection::class);

        $this->em->method('getConnection')
            ->willReturn($this->connection);

        $this->service = new AiNotificationService(
            $this->em,
            $this->httpClient,
            $this->groqApiKey,
        );
    }

    public function testBuildSmartDigestWithNoNotifications(): void
    {
        $this->connection->method('fetchAllAssociative')
            ->willReturn([]);

        $result = $this->service->buildSmartDigest(1);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('digest', $result);
        $this->assertEquals(0, $result['count_total']);
        $this->assertEquals([], $result['notifications']);
        $this->assertEquals('low', $result['priority']);
    }

    public function testBuildSmartDigestScoringAndSorting(): void
    {
        // Create raw DB rows
        $rows = [
            ['type' => 'booking', 'content' => 'Book confirmed', 'created_at' => '2024-01-01 10:00:00', 'is_read' => 0],
            ['type' => 'like', 'content' => 'John liked your post', 'created_at' => '2024-01-01 09:00:00', 'is_read' => 0],
        ];

        $this->connection->method('fetchAllAssociative')
            ->willReturn($rows);

        // Mock Groq responses: score + digest
        $this->mockGroqResponses(['90', '30', 'Your booking was confirmed and John liked your post.']);

        $result = $this->service->buildSmartDigest(1, 2);

        $this->assertIsArray($result);
        $this->assertNotNull($result['digest']);
        $this->assertCount(2, $result['notifications']);
        // booking (scored 90) should come first
        $this->assertEquals('booking', $result['notifications'][0]->type);
        // Average: (90 + 30) / 2 = 60 -> high
        $this->assertEquals('high', $result['priority']);
    }

    public function testBuildSmartDigestLimitsToMaxNotifications(): void
    {
        // Create 5 raw DB rows
        $rows = array_map(function ($i) {
            return [
                'type' => 'message',
                'content' => "Message $i",
                'created_at' => '2024-01-0' . $i . ' 10:00:00',
                'is_read' => 0,
            ];
        }, range(1, 5));

        $this->connection->method('fetchAllAssociative')
            ->willReturn($rows);

        // Mock scoring responses + digest (5 scores + 1 digest)
        $this->mockGroqResponses(['50', '50', '50', '50', '50', 'You have 3 new messages.']);

        $result = $this->service->buildSmartDigest(1, 3);

        $this->assertIsArray($result);
        $this->assertCount(3, $result['notifications']);
        $this->assertEquals(5, $result['count_total']);
        $this->assertEquals(3, $result['count_condensed']);
    }

    public function testBuildSmartDigestPriorityCalculation(): void
    {
        $row = ['type' => 'booking', 'content' => 'Book confirmed', 'created_at' => '2024-01-01 10:00:00', 'is_read' => 0];
        $this->connection->method('fetchAllAssociative')
            ->willReturn([$row]);

        // Mock high urgency score + digest
        $this->mockGroqResponses(['95', 'Your booking is confirmed!']);

        $result = $this->service->buildSmartDigest(1);

        $this->assertEquals('critical', $result['priority']);
    }

    public function testBuildSmartDigestFallbackWhenGroqFails(): void
    {
        $row = ['type' => 'booking', 'content' => 'Book confirmed', 'created_at' => '2024-01-01 10:00:00', 'is_read' => 0];
        $this->connection->method('fetchAllAssociative')
            ->willReturn([$row]);

        // Mock Groq failure
        $this->httpClient->method('request')->willThrowException(
            new \Exception('Groq API error')
        );

        $result = $this->service->buildSmartDigest(1);

        $this->assertIsArray($result);
        // Should still return result with fallback values
        $this->assertNotNull($result['digest']);
    }

    public function testExtractActionsFromNotifications(): void
    {
        // Test that actions are properly extracted
        $rows = [
            ['type' => 'booking', 'content' => 'Book confirmed', 'created_at' => '2024-01-01 10:00:00', 'is_read' => 0],
            ['type' => 'message', 'content' => 'New message', 'created_at' => '2024-01-01 09:00:00', 'is_read' => 0],
        ];

        $this->connection->method('fetchAllAssociative')
            ->willReturn($rows);

        // Mock scoring: 2 scores + 1 digest
        $this->mockGroqResponses(['85', '75', 'You have actions to take.']);

        $result = $this->service->buildSmartDigest(1, 2);

        $this->assertIsArray($result['actions']);
        $this->assertGreaterThan(0, count($result['actions']));
        
        // Verify action structure
        foreach ($result['actions'] as $action) {
            $this->assertArrayHasKey('label', $action);
            $this->assertArrayHasKey('url', $action);
        }
    }

    public function testGroqScoringEdgeCases(): void
    {
        $row = ['type' => 'test', 'content' => 'Test', 'created_at' => '2024-01-01 10:00:00', 'is_read' => 0];
        $this->connection->method('fetchAllAssociative')
            ->willReturn([$row]);

        // Test with response that has score > 100 + digest
        $this->mockGroqResponses(['150', 'You have a notification.']);

        $result = $this->service->buildSmartDigest(1);

        // Should cap at 100
        $this->assertIsArray($result);
        $this->assertNotNull($result['digest']);
    }

    public function testGroqScoringNegativeValues(): void
    {
        $row = ['type' => 'test', 'content' => 'Test', 'created_at' => '2024-01-01 10:00:00', 'is_read' => 0];
        $this->connection->method('fetchAllAssociative')
            ->willReturn([$row]);

        // Test with negative response (should become 0) + digest
        $this->mockGroqResponses(['-50', 'You have a notification.']);

        $result = $this->service->buildSmartDigest(1);

        $this->assertIsArray($result);
        $this->assertNotNull($result['digest']);
    }

    /**
     * Helper: Mock Groq API responses (queue).
     */
    private function mockGroqResponses(array $responses): void
    {
        $mockResponses = array_map(function (string $content) {
            $response = $this->createMock(ResponseInterface::class);
            $response->method('getStatusCode')->willReturn(200);
            $response->method('toArray')->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => $content,
                        ],
                    ],
                ],
            ]);
            return $response;
        }, $responses);

        $this->httpClient->expects($this->exactly(count($responses)))
            ->method('request')
            ->willReturnOnConsecutiveCalls(...$mockResponses);
    }
}
