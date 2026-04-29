<?php

namespace App\Tests\Service;

use App\Service\ContentModerationService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class ContentModerationServiceTest extends TestCase
{
    private ContentModerationService $service;
    private HttpClientInterface $httpClient;
    private string $groqApiKey = 'test-key';

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->service = new ContentModerationService(
            $this->httpClient,
            $this->groqApiKey,
        );
    }

    public function testFallbackModerationWithoutSwearing(): void
    {
        // Create service without API key to test fallback
        $service = new ContentModerationService($this->httpClient, '');
        
        $result = $service->checkContent('This is a nice post about my hobby!', 'post');

        $this->assertTrue($result['approved']);
        $this->assertEquals('safe', $result['severity']);
        $this->assertEmpty($result['flagged_words']);
    }

    public function testFallbackModerationWithSwearing(): void
    {
        $service = new ContentModerationService($this->httpClient, '');
        
        $result = $service->checkContent('This post is damn shit and full of crap!', 'post');

        $this->assertFalse($result['approved']);
        $this->assertEquals('blocked', $result['severity']);
        $this->assertGreaterThan(0, count($result['flagged_words']));
    }

    public function testFallbackModerationWithWarning(): void
    {
        $service = new ContentModerationService($this->httpClient, '');
        
        $result = $service->checkContent('This is a damn post', 'post');

        // Warning severity still approves (user can post but flag appears)
        $this->assertTrue($result['approved']);
        $this->assertEquals('warning', $result['severity']);
        $this->assertContains('damn', $result['flagged_words']);
    }

    public function testGroqModerationApproved(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'approved' => true,
                            'severity' => 'safe',
                            'reason' => 'Content is appropriate',
                            'flagged_words' => [],
                        ]),
                    ],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->checkContent('Hello friends! Let\'s go hiking!', 'post');

        $this->assertTrue($result['approved']);
        $this->assertEquals('safe', $result['severity']);
    }

    public function testGroqModerationBlocked(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('toArray')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'approved' => false,
                            'severity' => 'blocked',
                            'reason' => 'Hate speech detected',
                            'flagged_words' => ['offensive_word'],
                        ]),
                    ],
                ],
            ],
        ]);

        $this->httpClient->method('request')->willReturn($response);

        $result = $this->service->checkContent('Hate speech content', 'post');

        $this->assertFalse($result['approved']);
        $this->assertEquals('blocked', $result['severity']);
    }

    public function testGroqApiFailureFallsBack(): void
    {
        $this->httpClient->method('request')->willThrowException(
            new \Exception('Groq API error')
        );

        $result = $this->service->checkContent('some content', 'post');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('approved', $result);
        $this->assertArrayHasKey('reason', $result);
    }

    public function testCommentModeration(): void
    {
        $service = new ContentModerationService($this->httpClient, '');
        
        $result = $service->checkContent('Great post!', 'comment');

        $this->assertTrue($result['approved']);
        $this->assertEquals('safe', $result['severity']);
    }
}
