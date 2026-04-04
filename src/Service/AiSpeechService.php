<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiSpeechService
{
    public function __construct(private readonly HttpClientInterface $httpClient)
    {
    }

    public function transcribeAudio(UploadedFile $audioFile): string
    {
        $apiKey = trim((string) ($_ENV['GROQ_API_KEY'] ?? ''));
        $model = trim((string) ($_ENV['GROQ_STT_MODEL'] ?? 'whisper-large-v3-turbo'));

        if ($apiKey === '') {
            throw new \RuntimeException('Speech-to-text is not configured. Set GROQ_API_KEY.');
        }

        if (!$audioFile->isValid()) {
            throw new \RuntimeException('Invalid audio file upload.');
        }

        $mime = (string) ($audioFile->getMimeType() ?? 'audio/webm');
        if (!str_starts_with($mime, 'audio/')) {
            throw new \RuntimeException('Only audio files are supported for transcription.');
        }

        $formData = new FormDataPart([
            'model' => $model,
            'file' => DataPart::fromPath(
                $audioFile->getPathname(),
                $audioFile->getClientOriginalName() ?: ('recording.' . ($audioFile->guessExtension() ?: 'webm')),
                $mime
            ),
        ]);

        $headers = $formData->getPreparedHeaders()->toArray();
        $headers[] = 'Authorization: Bearer ' . $apiKey;

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/audio/transcriptions', [
            'headers' => $headers,
            'body' => $formData->bodyToIterable(),
            'timeout' => 120,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException('Speech transcription request failed.');
        }

        $data = $response->toArray(false);
        $text = trim((string) ($data['text'] ?? ''));
        if ($text === '') {
            throw new \RuntimeException('No transcription text returned.');
        }

        return $text;
    }
}
