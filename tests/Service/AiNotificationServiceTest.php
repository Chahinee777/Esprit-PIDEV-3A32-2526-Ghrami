<?php

namespace App\Tests\Service;

use App\Entity\Notification;
use App\Service\AiNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class AiNotificationServiceTest extends TestCase
{
    private AiNotificationService $service;
    private EntityManagerInterface $em;
    private HttpClientInterface $httpClient;
    private EntityRepository $notificationRepo;
    private string $groqApiKey = 'test-key';

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->notificationRepo = $this->createMock(EntityRepository::class);

        $this->em->method('getRepository')
            ->with(Notification::class)
            ->willReturn($this->notificationRepo);

        $this->service = new AiNotificationService(
            $this->em,
            $this->httpClient,
            $this->groqApiKey,
        );
    }

    public function testBuildSmartDigestWithNoNotifications(): void
    {
        $this->notificationRepo->method('findBy')->willReturn([]);

        $result = $this->service->buildSmartDigest(1);

        $this->assertNull($result);
    }

    public function testBuildSmartDigestScoringAndSorting(): void
    {
        // Create mock notifications
        $notif1 = $this->createMockNotification(1, 'booking', 'Book confirmed');
        $notif2 = $this->createMockNotification(2, 'like', 'John liked your post');

        $this->notificationRepo->method('findBy')->willReturn([$notif1, $notif2]);

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
        // Create 5 notifications
        $notifications = array_map(
            fn($i) => $this->createMockNotification($i, 'message', "Message $i"),
            range(1, 5)
        );

        $this->notificationRepo->method('findBy')->willReturn($notifications);

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
        $notif = $this->createMockNotification(1, 'booking', 'Book confirmed');
        $this->notificationRepo->method('findBy')->willReturn([$notif]);

        // Mock high urgency score + digest
        $this->mockGroqResponses(['95', 'Your booking is confirmed!']);

        $result = $this->service->buildSmartDigest(1);

        $this->assertEquals('critical', $result['priority']);
    }

    public function testBuildSmartDigestFallbackWhenGroqFails(): void
    {
        $notif = $this->createMockNotification(1, 'booking', 'Book confirmed');
        $this->notificationRepo->method('findBy')->willReturn([$notif]);

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
        $bookingNotif = $this->createMockNotification(1, 'booking', 'Book confirmed');
        $messageNotif = $this->createMockNotification(2, 'message', 'New message');

        $this->notificationRepo->method('findBy')->willReturn([$bookingNotif, $messageNotif]);

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
        $notif = $this->createMockNotification(1, 'test', 'Test');
        $this->notificationRepo->method('findBy')->willReturn([$notif]);

        // Test with response that has score > 100 + digest
        $this->mockGroqResponses(['150', 'You have a notification.']);

        $result = $this->service->buildSmartDigest(1);

        // Should cap at 100
        $this->assertIsArray($result);
        $this->assertNotNull($result['digest']);
    }

    public function testGroqScoringNegativeValues(): void
    {
        $notif = $this->createMockNotification(1, 'test', 'Test');
        $this->notificationRepo->method('findBy')->willReturn([$notif]);

        // Test with negative response (should become 0) + digest
        $this->mockGroqResponses(['-50', 'You have a notification.']);

        $result = $this->service->buildSmartDigest(1);

        $this->assertIsArray($result);
        $this->assertNotNull($result['digest']);
    }

    /**
     * Helper: Create notification with required properties.
     */
    private function createMockNotification(int $id, string $type, string $content): Notification
    {
        $notif = new Notification();
        $notif->id = $id;
        $notif->type = $type;
        $notif->content = $content;
        $notif->createdAt = new \DateTime();
        $notif->isRead = false;

        return $notif;
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
