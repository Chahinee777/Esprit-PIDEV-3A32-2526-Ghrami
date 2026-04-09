<?php

namespace App\Service;

use Psr\Log\LoggerInterface;

class CalendarService
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Generate iCal format for hobby session
     * Can be imported into any calendar app (Google Calendar, Outlook, etc.)
     */
    public function generateICalEvent(
        int $hobbyId,
        string $hobbyName,
        string $hobbyCategory,
        \DateTimeImmutable $date,
        float $hours,
        ?string $notes = null,
        ?string $location = null
    ): string
    {
        $uid = 'hobby-' . $hobbyId . '-' . $date->format('Y-m-d') . '@ghrami.local';
        $now = (new \DateTimeImmutable())->format('Ymd\THis\Z');
        $eventDate = $date->format('Ymd');
        
        // Calculate end time
        $startTime = $date->format('Ymd\T090000');
        $hoursMinutes = (int)($hours * 60);
        $endTime = $date->add(new \DateInterval('PT' . $hoursMinutes . 'M'))->format('Ymd\THis');

        $description = "Hobby: {$hobbyName}\n";
        $description .= "Category: {$hobbyCategory}\n";
        $description .= "Hours: {$hours}h\n";
        if ($notes) {
            $description .= "Notes: " . str_replace("\n", "\\n", $notes);
        }

        $ical = "BEGIN:VCALENDAR\n";
        $ical .= "VERSION:2.0\n";
        $ical .= "PRODID:-//Ghrami//Hobby Tracker//EN\n";
        $ical .= "CALSCALE:GREGORIAN\n";
        $ical .= "METHOD:PUBLISH\n";
        $ical .= "X-WR-CALNAME:Ghrami - " . $hobbyName . "\n";
        $ical .= "X-WR-TIMEZONE:UTC\n";
        $ical .= "BEGIN:VEVENT\n";
        $ical .= "UID:{$uid}\n";
        $ical .= "DTSTAMP:{$now}\n";
        $ical .= "DTSTART:{$startTime}\n";
        $ical .= "DTEND:{$endTime}\n";
        $ical .= "SUMMARY:🎯 {$hobbyName} ({$hours}h)\n";
        $ical .= "DESCRIPTION:" . str_replace("\n", "\\n", $description) . "\n";
        $ical .= "LOCATION:" . ($location ?? "Home") . "\n";
        $ical .= "CATEGORIES:" . $hobbyCategory . "\n";
        $ical .= "STATUS:CONFIRMED\n";
        $ical .= "SEQUENCE:0\n";
        $ical .= "END:VEVENT\n";
        $ical .= "END:VCALENDAR\n";

        return $ical;
    }

    /**
     * Generate Google Calendar event link
     * User can click to add event directly to Google Calendar
     */
    public function generateGoogleCalendarLink(
        string $hobbyName,
        \DateTimeImmutable $date,
        float $hours,
        ?string $details = null
    ): string
    {
        $title = "🎯 {$hobbyName} ({$hours}h)";
        $startTime = $date->format('YmdTHi00');
        $endTime = $date->add(new \DateInterval('PT' . ((int)($hours * 60)) . 'M'))->format('YmdTHi00');
        $description = $details ?? '';

        $params = [
            'action' => 'TEMPLATE',
            'text' => $title,
            'dates' => $startTime . '/' . $endTime,
            'details' => $description,
        ];

        return 'https://calendar.google.com/calendar/u/0/r/eventedit?' . http_build_query($params);
    }

    /**
     * Generate Outlook/Office365 event link
     */
    public function generateOutlookLink(
        string $hobbyName,
        \DateTimeImmutable $date,
        float $hours,
        ?string $details = null
    ): string
    {
        $title = "🎯 {$hobbyName} ({$hours}h)";
        $startTime = $date->format(\DateTimeInterface::ATOM);
        $endTime = $date->add(new \DateInterval('PT' . ((int)($hours * 60)) . 'M'))->format(\DateTimeInterface::ATOM);
        $description = $details ?? '';

        $params = [
            'subject' => $title,
            'startdt' => $startTime,
            'enddt' => $endTime,
            'body' => $description,
        ];

        return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query($params);
    }

    /**
     * Generate iCal feed for all hobbies
     */
    public function generateHobbyCalendarFeed(
        array $progressLogs,
        string $feedName = 'Ghrami Hobbies'
    ): string
    {
        $now = (new \DateTimeImmutable())->format('Ymd\THis\Z');
        
        $ical = "BEGIN:VCALENDAR\n";
        $ical .= "VERSION:2.0\n";
        $ical .= "PRODID:-//Ghrami//Hobby Tracker//EN\n";
        $ical .= "CALSCALE:GREGORIAN\n";
        $ical .= "METHOD:PUBLISH\n";
        $ical .= "X-WR-CALNAME:{$feedName}\n";
        $ical .= "X-WR-TIMEZONE:UTC\n";
        $ical .= "X-WR-CALDESC:All hobby sessions from Ghrami\n";

        foreach ($progressLogs as $log) {
            $hobbyName = $log['hobby_name'] ?? 'Hobby';
            $hobbyCategory = $log['hobby_category'] ?? 'Other';
            $date = $log['log_date'];
            $hours = $log['hours_spent'] ?? 0;
            $notes = $log['notes'] ?? '';
            $hobbyId = $log['hobby_id'] ?? 0;

            if (!($date instanceof \DateTimeImmutable)) {
                if ($date instanceof \DateTime) {
                    $date = \DateTimeImmutable::createFromMutable($date);
                } else {
                    continue;
                }
            }

            $uid = 'hobby-' . $hobbyId . '-' . $date->format('Y-m-d') . '@ghrami.local';
            $startTime = $date->format('Ymd\T090000');
            $hoursMinutes = (int)($hours * 60);
            $endTime = $date->add(new \DateInterval('PT' . $hoursMinutes . 'M'))->format('Ymd\THis');

            $description = "Hobby: {$hobbyName}\n";
            $description .= "Category: {$hobbyCategory}\n";
            $description .= "Hours: {$hours}h\n";
            if ($notes) {
                $description .= "Notes: " . str_replace("\n", "\\n", $notes);
            }

            $ical .= "BEGIN:VEVENT\n";
            $ical .= "UID:{$uid}\n";
            $ical .= "DTSTAMP:{$now}\n";
            $ical .= "DTSTART:{$startTime}\n";
            $ical .= "DTEND:{$endTime}\n";
            $ical .= "SUMMARY:🎯 {$hobbyName} ({$hours}h)\n";
            $ical .= "DESCRIPTION:" . str_replace("\n", "\\n", $description) . "\n";
            $ical .= "CATEGORIES:{$hobbyCategory}\n";
            $ical .= "STATUS:CONFIRMED\n";
            $ical .= "SEQUENCE:0\n";
            $ical .= "END:VEVENT\n";
        }

        $ical .= "END:VCALENDAR\n";

        return $ical;
    }

    /**
     * Get color code for hobby category (for calendar display)
     */
    public function getCategoryColor(string $category): string
    {
        $colors = [
            'Sports & Fitness' => '#ea4335', // Red
            'Arts & Crafts' => '#fbbc04', // Yellow
            'Music' => '#34a853', // Green
            'Cooking' => '#ea4335', // Red
            'Gaming' => '#4285f4', // Blue
            'Reading' => '#9c27b0', // Purple
            'Technology' => '#667eea', // Indigo
            'Photography' => '#ff6d00', // Orange
            'Gardening' => '#34a853', // Green
            'Writing' => '#5e35b1', // Deep Purple
            'Learning Languages' => '#1976d2', // Light Blue
            'Dancing' => '#e91e63', // Pink
            'Traveling' => '#26a69a', // Teal
        ];

        return $colors[$category] ?? '#667eea';
    }

    /**
     * Get category emoji for calendar events
     */
    public function getCategoryEmoji(string $category): string
    {
        $emojis = [
            'Sports & Fitness' => '⚽',
            'Arts & Crafts' => '🎨',
            'Music' => '🎵',
            'Cooking' => '🍳',
            'Gaming' => '🎮',
            'Reading' => '📚',
            'Technology' => '💻',
            'Photography' => '📷',
            'Gardening' => '🌱',
            'Writing' => '✏️',
            'Learning Languages' => '🗣️',
            'Dancing' => '💃',
            'Traveling' => '✈️',
        ];

        return $emojis[$category] ?? '🎯';
    }
}
