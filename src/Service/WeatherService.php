<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class WeatherService
{
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Get weather data for a specific date and location
     * Uses Open-Meteo API (free, no API key needed)
     * 
     * @param float $latitude
     * @param float $longitude
     * @param \DateTimeImmutable|string|null $date Date or date string (YYYY-MM-DD)
     * @return array|null Weather data or null on error
     */
    public function getWeather(float $latitude, float $longitude, \DateTimeImmutable|string|null $date = null): ?array
    {
        try {
            if ($date === null) {
                $dateStr = (new \DateTimeImmutable())->format('Y-m-d');
            } elseif (is_string($date)) {
                $dateStr = $date;
            } else {
                $dateStr = $date->format('Y-m-d');
            }

            // Open-Meteo API - Free, no authentication needed
            $response = $this->httpClient->request('GET', 'https://archive-api.open-meteo.com/v1/archive', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'start_date' => $dateStr,
                    'end_date' => $dateStr,
                    'hourly' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m',
                    'daily' => 'temperature_2m_max,temperature_2m_min,weather_code,precipitation_sum',
                    'temperature_unit' => 'celsius',
                    'timezone' => 'auto',
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $this->parseWeatherData($data, $dateStr);
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Weather API error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get current weather for a location
     */
    public function getCurrentWeather(float $latitude, float $longitude): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m',
                    'temperature_unit' => 'celsius',
                    'timezone' => 'auto',
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $this->parseCurrentWeather($data);
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Current weather API error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get weather forecast for next 7 days
     */
    public function getWeatherForecast(float $latitude, float $longitude, int $days = 7): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'daily' => 'temperature_2m_max,temperature_2m_min,weather_code,precipitation_sum,wind_speed_10m_max',
                    'forecast_days' => $days,
                    'temperature_unit' => 'celsius',
                    'timezone' => 'auto',
                ],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return $this->parseForecast($data);
            }

            return null;
        } catch (\Exception $e) {
            $this->logger->error('Forecast API error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse daily weather data
     */
    private function parseWeatherData(array $data, string $date): array
    {
        if (!isset($data['daily'])) {
            return [];
        }

        $daily = $data['daily'];
        $index = 0;

        return [
            'date' => $date,
            'temperature_max' => $daily['temperature_2m_max'][$index] ?? null,
            'temperature_min' => $daily['temperature_2m_min'][$index] ?? null,
            'weather_code' => $daily['weather_code'][$index] ?? null,
            'weather_description' => $this->getWeatherDescription($daily['weather_code'][$index] ?? 0),
            'precipitation' => $daily['precipitation_sum'][$index] ?? 0,
            'emoji' => $this->getWeatherEmoji($daily['weather_code'][$index] ?? 0),
            'good_for_activity' => $this->isGoodWeatherForActivity($daily['weather_code'][$index] ?? 0),
        ];
    }

    /**
     * Parse current weather data
     */
    private function parseCurrentWeather(array $data): array
    {
        $current = $data['current'] ?? [];

        return [
            'temperature' => $current['temperature_2m'] ?? null,
            'humidity' => $current['relative_humidity_2m'] ?? null,
            'weather_code' => $current['weather_code'] ?? null,
            'weather_description' => $this->getWeatherDescription($current['weather_code'] ?? 0),
            'wind_speed' => $current['wind_speed_10m'] ?? null,
            'emoji' => $this->getWeatherEmoji($current['weather_code'] ?? 0),
        ];
    }

    /**
     * Parse forecast data
     */
    private function parseForecast(array $data): array
    {
        $daily = $data['daily'] ?? [];
        $forecast = [];

        for ($i = 0; $i < count($daily['time']); $i++) {
            $forecast[] = [
                'date' => $daily['time'][$i],
                'temperature_max' => $daily['temperature_2m_max'][$i] ?? null,
                'temperature_min' => $daily['temperature_2m_min'][$i] ?? null,
                'weather_code' => $daily['weather_code'][$i] ?? null,
                'weather_description' => $this->getWeatherDescription($daily['weather_code'][$i] ?? 0),
                'precipitation' => $daily['precipitation_sum'][$i] ?? 0,
                'emoji' => $this->getWeatherEmoji($daily['weather_code'][$i] ?? 0),
                'good_for_activity' => $this->isGoodWeatherForActivity($daily['weather_code'][$i] ?? 0),
            ];
        }

        return $forecast;
    }

    /**
     * Get human-readable weather description from WMO code
     */
    public function getWeatherDescription(int $code): string
    {
        $descriptions = [
            0 => 'Clear sky',
            1 => 'Clear sky with few clouds',
            2 => 'Partly cloudy',
            3 => 'Overcast',
            45 => 'Foggy',
            48 => 'Foggy',
            51 => 'Light drizzle',
            53 => 'Moderate drizzle',
            55 => 'Heavy drizzle',
            61 => 'Slight rain',
            63 => 'Moderate rain',
            65 => 'Heavy rain',
            71 => 'Slight snow',
            73 => 'Moderate snow',
            75 => 'Heavy snow',
            80 => 'Slight rain showers',
            81 => 'Moderate rain showers',
            82 => 'Violent rain showers',
            85 => 'Snow showers',
            86 => 'Heavy snow showers',
            95 => 'Thunderstorm',
            96 => 'Thunderstorm with hail',
            99 => 'Thunderstorm with hail',
        ];

        return $descriptions[$code] ?? 'Unknown';
    }

    /**
     * Get weather emoji from WMO code
     */
    public function getWeatherEmoji(int $code): string
    {
        if ($code <= 1) return '☀️';
        if ($code <= 3) return '⛅';
        if ($code <= 48) return '🌫️';
        if ($code <= 77) return '🌧️';
        if ($code <= 86) return '❄️';
        return '⛈️';
    }

    /**
     * Check if weather is good for outdoor activities
     */
    public function isGoodWeatherForActivity(int $code): bool
    {
        // Good: clear, partly cloudy
        // Bad: rain, snow, thunderstorm, fog
        $badWeatherCodes = [45, 48, 51, 53, 55, 61, 63, 65, 71, 73, 75, 80, 81, 82, 85, 86, 95, 96, 99];
        return !in_array($code, $badWeatherCodes);
    }
}
