<?php

namespace App\Service;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

class GoogleMeetService
{
    private string $serviceAccountPath;
    private string $calendarId;
    private string $timezone;
    private ?string $lastError = null;

    public function __construct()
    {
        $this->serviceAccountPath = (string) ($_ENV['GOOGLE_CALENDAR_SERVICE_ACCOUNT_JSON'] ?? '');
        $this->calendarId = (string) ($_ENV['GOOGLE_CALENDAR_ID'] ?? 'primary');
        $this->timezone = (string) ($_ENV['APP_TIMEZONE'] ?? 'UTC');
    }

    public function isConfigured(): bool
    {
        return $this->serviceAccountPath !== '' && is_file($this->serviceAccountPath);
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function createMeetLink(
        string $scheduledAt,
        int $durationMinutes,
        string $summary,
        string $description = ''
    ): ?string {
        $this->lastError = null;

        if (!$this->isConfigured()) {
            $this->lastError = 'Service-account Google Calendar is not configured.';
            return null;
        }

        try {
            $start = new \DateTimeImmutable($scheduledAt, new \DateTimeZone($this->timezone));
            $end = $start->modify(sprintf('+%d minutes', max(10, $durationMinutes)));

            $client = new Client();
            $client->setAuthConfig($this->serviceAccountPath);
            $client->addScope(Calendar::CALENDAR_EVENTS);

            $calendar = new Calendar($client);

            $event = new Event([
                'summary' => $summary,
                'description' => $description,
                'start' => new EventDateTime([
                    'dateTime' => $start->format(DATE_RFC3339),
                    'timeZone' => $this->timezone,
                ]),
                'end' => new EventDateTime([
                    'dateTime' => $end->format(DATE_RFC3339),
                    'timeZone' => $this->timezone,
                ]),
                'conferenceData' => [
                    'createRequest' => [
                        'requestId' => bin2hex(random_bytes(10)),
                        'conferenceSolutionKey' => [
                            'type' => 'hangoutsMeet',
                        ],
                    ],
                ],
            ]);

            $created = $calendar->events->insert($this->calendarId, $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'none',
            ]);

            $link = (string) ($created->getHangoutLink() ?? '');
            if ($link !== '') {
                return $link;
            }

            $conferenceData = $created->getConferenceData();
            if ($conferenceData && $conferenceData->getEntryPoints()) {
                foreach ($conferenceData->getEntryPoints() as $entryPoint) {
                    if ($entryPoint->getEntryPointType() === 'video' && $entryPoint->getUri()) {
                        return (string) $entryPoint->getUri();
                    }
                }
            }

            $this->lastError = 'Google Calendar event created but no Meet video entry-point was returned.';
            return null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('Google Meet link creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Creates a Google Meet link using a user's personal Google access token.
     * This aligns with Desktop's model where each user creates from their own Calendar.
     *
     * @param string $userAccessToken User's Google OAuth access token
     * @param string $scheduledAt DateTime string for meeting start (ISO 8601)
     * @param int $durationMinutes Duration in minutes (minimum 10)
     * @param string $summary Meeting title
     * @param string $description Meeting description
     * @return string|null Google Meet link URL or null on failure
     */
    public function createMeetLinkWithUserToken(
        string $userAccessToken,
        string $scheduledAt,
        int $durationMinutes,
        string $summary,
        string $description = ''
    ): ?string {
        $this->lastError = null;

        if ($userAccessToken === '') {
            $this->lastError = 'Missing user Google access token.';
            return null;
        }

        try {
            $start = new \DateTimeImmutable($scheduledAt, new \DateTimeZone($this->timezone));
            $end = $start->modify(sprintf('+%d minutes', max(10, $durationMinutes)));

            $client = new Client();
            $client->setAccessToken([
                'access_token' => $userAccessToken,
            ]);
            $client->addScope(Calendar::CALENDAR_EVENTS);

            $calendar = new Calendar($client);

            $event = new Event([
                'summary' => $summary,
                'description' => $description,
                'start' => new EventDateTime([
                    'dateTime' => $start->format(DATE_RFC3339),
                    'timeZone' => $this->timezone,
                ]),
                'end' => new EventDateTime([
                    'dateTime' => $end->format(DATE_RFC3339),
                    'timeZone' => $this->timezone,
                ]),
                'conferenceData' => [
                    'createRequest' => [
                        'requestId' => bin2hex(random_bytes(10)),
                        'conferenceSolutionKey' => [
                            'type' => 'hangoutsMeet',
                        ],
                    ],
                ],
            ]);

            $created = $calendar->events->insert('primary', $event, [
                'conferenceDataVersion' => 1,
                'sendUpdates' => 'none',
            ]);

            $link = (string) ($created->getHangoutLink() ?? '');
            if ($link !== '') {
                return $link;
            }

            $conferenceData = $created->getConferenceData();
            if ($conferenceData && $conferenceData->getEntryPoints()) {
                foreach ($conferenceData->getEntryPoints() as $entryPoint) {
                    if ($entryPoint->getEntryPointType() === 'video' && $entryPoint->getUri()) {
                        return (string) $entryPoint->getUri();
                    }
                }
            }

            $this->lastError = 'Google Calendar event created but no Meet video entry-point was returned.';
            return null;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('Google Meet link creation with user token failed: ' . $e->getMessage());
            return null;
        }
    }
}
