<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiContentService
{
    private string $groqApiKey;
    private string $groqModel;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        ParameterBagInterface $params
    ) {
        $this->groqApiKey = $params->get('groq_api_key') ?? '';
        $this->groqModel = $params->get('groq_text_model') ?? 'llama-3.1-8b-instant';
    }

    public function completePostText(string $currentText): string
    {
        if (empty($this->groqApiKey)) {
            throw new \RuntimeException('AI text completion is not configured. Set GROQ_API_KEY.');
        }

        $prompt = mb_substr(trim($currentText), 0, 3000);
        if ($prompt === '') {
            throw new \RuntimeException('Post text is empty.');
        }

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->groqModel,
                'temperature' => 0.7,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Complete the user social post naturally, positively, and concisely. Return only the completion text without quotes.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => 120,
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('AI text completion request failed.');
        }

        $data = $response->toArray(false);
        $completion = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($completion === '') {
            throw new \RuntimeException('AI did not return text.');
        }

        return $completion;
    }

    /**
     * @return array{bytes: string, mime: string, extension: string}
     */
    public function generateImage(string $prompt): array
    {
        $token = trim((string) ($_ENV['HUGGINGFACE_API_TOKEN'] ?? ''));
        $model = trim((string) ($_ENV['HUGGINGFACE_IMAGE_MODEL'] ?? 'black-forest-labs/FLUX.1-schnell'));

        if ($token === '') {
            throw new \RuntimeException('AI image generation is not configured. Set HUGGINGFACE_API_TOKEN.');
        }

        $cleanPrompt = mb_substr(trim($prompt), 0, 1200);
        if ($cleanPrompt === '') {
            throw new \RuntimeException('Prompt is empty.');
        }

        $response = $this->httpClient->request('POST', 'https://api-inference.huggingface.co/models/' . $model, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'inputs' => $cleanPrompt,
                'options' => [
                    'wait_for_model' => true,
                ],
            ],
            'timeout' => 90,
        ]);

        $status = $response->getStatusCode();
        $contentType = strtolower((string) $response->getHeaders(false)['content-type'][0] ?? '');
        $content = $response->getContent(false);

        if ($status >= 400) {
            throw new \RuntimeException('AI image generation request failed.');
        }

        if (str_contains($contentType, 'application/json')) {
            $payload = json_decode($content, true);
            $err = is_array($payload) ? (string) ($payload['error'] ?? 'Unknown image generation error.') : 'Image generation error.';
            throw new \RuntimeException($err);
        }

        $mime = $this->detectMime($contentType, $content);
        $extension = $mime === 'image/png' ? 'png' : ($mime === 'image/webp' ? 'webp' : 'jpg');

        return [
            'bytes' => $content,
            'mime' => $mime,
            'extension' => $extension,
        ];
    }

    private function detectMime(string $contentType, string $bytes): string
    {
        if (str_contains($contentType, 'image/png')) {
            return 'image/png';
        }
        if (str_contains($contentType, 'image/webp')) {
            return 'image/webp';
        }
        if (str_contains($contentType, 'image/jpeg') || str_contains($contentType, 'image/jpg')) {
            return 'image/jpeg';
        }

        if (str_starts_with($bytes, "\x89PNG")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return 'image/jpeg';
    }

    /**
     * Generate a caption for an image using Groq's vision API
     * Analyzes the actual image content and creates a social media caption
     * 
     * @param $imagePath The file path to the image
     * @param $originalFileName Optional: The original filename (for fallback captions)
     */
    public function analyzeImageForCaption(string $imagePath, ?string $originalFileName = null): string
    {
        if (!file_exists($imagePath)) {
            throw new \RuntimeException('Image file not found: ' . $imagePath);
        }

        if (empty($this->groqApiKey)) {
            throw new \RuntimeException('AI caption generation is not configured. Set GROQ_API_KEY.');
        }

        // Read image and convert to base64 for Groq vision API capability
        $imageContent = file_get_contents($imagePath);
        if ($imageContent === false) {
            throw new \RuntimeException('Could not read image file.');
        }

        $base64Image = base64_encode($imageContent);
        $mimeType = $this->detectMimeType($imagePath);
        $dataUri = 'data:' . $mimeType . ';base64,' . $base64Image;

        // Call Groq with vision support - using the correct API format
        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => $this->groqModel,
                    'temperature' => 0.8,
                    'max_tokens' => 120,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'image_url',
                                    'image_url' => ['url' => $dataUri],
                                ],
                                [
                                    'type' => 'text',
                                    'text' => 'Describe this image in one short sentence suitable for a social media caption. Create an engaging caption in French (1-2 sentences max). Include 1-2 relevant emojis. Make it authentic and fun.',
                                ],
                            ],
                        ],
                    ],
                ],
                'timeout' => 60,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400) {
                $errorData = $response->toArray(false);
                $errorMsg = $errorData['error']['message'] ?? 'Unknown error';
                
                // Log the error but try fallback
                error_log('Groq vision error: ' . $errorMsg);
                
                // Fallback: Just ask Groq to create a caption from the filename
                return $this->captionFromFilename($imagePath, $originalFileName);
            }

            $data = $response->toArray(false);
            $caption = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            
            if ($caption === '') {
                return $this->captionFromFilename($imagePath, $originalFileName);
            }

            return $caption;
        } catch (\Throwable $e) {
            error_log('Groq vision exception: ' . $e->getMessage());
            return $this->captionFromFilename($imagePath);
        }
    }

    /**
     * Fallback: Create caption from filename when vision isn't available
     */
    private function captionFromFilename(string $imagePath, ?string $originalFileName = null): string
    {
        // Use original filename if provided, otherwise extract from path
        if ($originalFileName) {
            $fileName = pathinfo($originalFileName, PATHINFO_FILENAME);
        } else {
            $fileName = pathinfo($imagePath, PATHINFO_FILENAME);
        }
        $fileName = str_replace(['-', '_'], ' ', $fileName);

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->groqModel,
                'temperature' => 0.8,
                'max_tokens' => 120,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a social media expert. Create a short, engaging caption in French (1-2 sentences). Include 1-2 relevant emojis.',
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Create a social media caption for a photo about: ' . $fileName,
                    ],
                ],
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('AI caption generation failed');
        }

        $data = $response->toArray(false);
        return trim((string) ($data['choices'][0]['message']['content'] ?? 'Amazing photo!'));
    }

    /**
     * Detect MIME type from file content
     */
    /**
     * Detect MIME type from file's byte signature (magic numbers)
     * Matches desktop implementation exactly
     * 
     * PNG signature: 89 50 4E 47
     * JPEG signature: FF D8
     */
    private function detectMimeType(string $filePath): string
    {
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return 'image/jpeg'; // fallback
        }

        $bytes = fread($handle, 8);
        fclose($handle);

        if (strlen($bytes) >= 4) {
            // PNG signature: 89 50 4E 47
            if ($bytes[0] === chr(0x89) && $bytes[1] === chr(0x50) && 
                $bytes[2] === chr(0x4E) && $bytes[3] === chr(0x47)) {
                return 'image/png';
            }

            // JPEG signature: FF D8
            if ($bytes[0] === chr(0xFF) && $bytes[1] === chr(0xD8)) {
                return 'image/jpeg';
            }
        }

        // Fallback - same as desktop
        return 'image/jpeg';
    }

    private function getMimeTypeFromPath(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
