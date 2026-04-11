<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Transcribe audio to text using Groq Whisper API
 * Matches the desktop implementation with manual multipart construction
 */
class VoiceTranscriptionService
{
    private const GROQ_WHISPER_URL = 'https://api.groq.com/openai/v1/audio/transcriptions';
    private const GROQ_MODEL = 'whisper-large-v3-turbo';

    public function __construct(
        private readonly string $groqApiKey,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Transcribe audio from file path using Groq Whisper
     */
    public function transcribe(string $audioPath): ?string
    {
        if (empty($this->groqApiKey) || !file_exists($audioPath)) {
            return null;
        }

        try {
            $audioContent = file_get_contents($audioPath);
            if ($audioContent === false) {
                throw new \RuntimeException('Could not read audio file.');
            }

            return $this->callGroqWhisper($audioContent, 'audio.wav', 'audio/wav');
        } catch (\Throwable $e) {
            error_log('VoiceTranscriptionService::transcribe error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Transcribe from multipart request (for web uploads)
     * Used when audio comes from browser MediaRecorder
     */
    public function transcribeFromFile(\SplFileInfo $file): ?string
    {
        if (!$file->isFile() || !is_readable($file->getRealPath())) {
            error_log('VoiceTranscriptionService: File not readable: ' . $file->getRealPath());
            return null;
        }

        try {
            $audioContent = file_get_contents($file->getRealPath());
            if ($audioContent === false) {
                throw new \RuntimeException('Could not read audio file.');
            }

            $filename = 'audio.wav';
            if (method_exists($file, 'getClientOriginalName')) {
                $candidate = (string) $file->getClientOriginalName();
                if ($candidate !== '') {
                    $filename = $candidate;
                }
            }

            $mimeType = 'audio/wav';
            if (method_exists($file, 'getMimeType')) {
                $detected = (string) $file->getMimeType();
                if ($detected !== '') {
                    $mimeType = $detected;
                }
            }

            return $this->callGroqWhisper($audioContent, $filename, $mimeType);
        } catch (\Throwable $e) {
            error_log('VoiceTranscriptionService::transcribeFromFile error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Calls Groq Whisper API with manually constructed multipart body
     * Matches desktop implementation for consistency
     */
    private function callGroqWhisper(string $audioBytes, string $filename, string $mimeType): ?string
    {
        try {
            if (empty($this->groqApiKey)) {
                throw new \RuntimeException('Groq API key not configured.');
            }

            error_log('[VoiceTranscription] Audio bytes received: ' . strlen($audioBytes) . ' bytes');

            // Generate boundary (matches desktop: ----GhramiBoundary{timestamp})
            $boundary = '----GhramiBoundary' . round(microtime(true) * 1000);

            // Build multipart body manually (desktop-style)
            $body = '';

            // Part 1: model
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
            $body .= self::GROQ_MODEL . "\r\n";

            // Part 2: language (French)
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
            $body .= "fr\r\n";

            // Part 3: response_format
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
            $body .= "json\r\n";

            // Part 4: audio file (binary data)
            $filePartHeader = "--{$boundary}\r\n";
            $safeFilename = str_replace(['\r', '\n', '"'], '', $filename);
            $safeMimeType = str_replace(['\r', '\n'], '', $mimeType);
            $filePartHeader .= "Content-Disposition: form-data; name=\"file\"; filename=\"{$safeFilename}\"\r\n";
            $filePartHeader .= "Content-Type: {$safeMimeType}\r\n\r\n";
            $filePartFooter = "\r\n--{$boundary}--\r\n";

            // Build complete body with binary audio
            $completeBody = $body . $filePartHeader . $audioBytes . $filePartFooter;

            error_log('[VoiceTranscription] Total multipart body size: ' . strlen($completeBody) . ' bytes');
            error_log('[VoiceTranscription] Sending to Groq Whisper API...');

            // Send to Groq
            $response = $this->httpClient->request('POST', self::GROQ_WHISPER_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ],
                'body' => $completeBody,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            error_log('[VoiceTranscription] Groq API response status: ' . $statusCode);

            if ($statusCode >= 400) {
                $errorBody = $response->getContent(false);
                error_log("[VoiceTranscription] Groq API error {$statusCode}: " . $errorBody);
                return null;
            }

            // Parse JSON response: {"text": "..."}
            $json = $response->getContent();
            error_log('[VoiceTranscription] Groq API response: ' . substr($json, 0, 500));

            $data = json_decode($json, true);

            if (isset($data['text'])) {
                $text = trim((string) $data['text']);
                error_log('[VoiceTranscription] Transcription successful: ' . $text);
                return !empty($text) ? $text : null;
            }

            error_log('[VoiceTranscription] No text field in response: ' . $json);
            return null;
        } catch (\Throwable $e) {
            error_log('[VoiceTranscription] Exception: ' . $e->getMessage());
            error_log('[VoiceTranscription] Stack: ' . $e->getTraceAsString());
            return null;
        }
    }
}
