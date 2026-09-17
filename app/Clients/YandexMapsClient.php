<?php

namespace App\Clients;

use App\Exceptions\CaptchaDetectedException;
use App\Exceptions\SourceChangedException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;

class YandexMapsClient
{
    protected GuzzleClient $client;

    public function __construct()
    {
        $this->client = new GuzzleClient([
            'timeout' => 15,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
            ],
            'cookies' => CookieJar::fromArray([], true),
        ]);
    }

    public function fetchPage(string $url): string
    {
        # случайный UA
        $response = $this->client->request('GET', $url, [
            'headers' => [
                'User-Agent' => $this->getRandomUserAgent(),
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en;q=0.8',
                'Accept-Encoding' => 'gzip, deflate, br',
            ],
        ]);
        $body = (string) $response->getBody();
        $status = $response->getStatusCode();

        if ($status === 403 || $status === 429) {
            throw new CaptchaDetectedException('HTTP ' . $status . ' — возможная капча или блокировка');
        }

        # только showcaptcha
        if (str_contains($body, 'showcaptcha')) {
            throw new CaptchaDetectedException('Обнаружена капча в HTML');
        }

        return $body;
    }

    # пул UA
    private function getRandomUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];

        return $agents[array_rand($agents)];
    }

    public function fetchStateView(Crawler $crawler): ?array
    {
        $script = $crawler->filter('script.state-view')->text('');
        if (empty($script)) {
            throw new SourceChangedException('Отсутствует state-view в <head>');
        }

        $data = json_decode($script, true);
        if (!is_array($data) || empty($data['stack'])) {
            throw new SourceChangedException('Неверный формат state-view JSON');
        }

        return $data;
    }

    public function detectMarkupChange(Crawler $crawler): bool
    {
        $reviews = $crawler->filter('[itemType="http://schema.org/Review"]');
        $stateViewExists = $crawler->filter('script.state-view')->count() > 0;
        $authorExists = $crawler->filter('[itemProp="name"]')->count() > 0;
        $bodyExists = $crawler->filter('.business-review-view__body, [itemProp="reviewBody"]')->count() > 0;

        return !$stateViewExists || $reviews->count() === 0 || !$authorExists || !$bodyExists;
    }

    public function parseRussianDate(string $text): ?\DateTime
    {
        $text = mb_strtolower(trim($text));

        $months = [
            'января' => 1, 'февраля' => 2, 'марта' => 3, 'апреля' => 4,
            'мая' => 5, 'июня' => 6, 'июля' => 7, 'августа' => 8,
            'сентября' => 9, 'октября' => 10, 'ноября' => 11, 'декабря' => 12,
        ];

        foreach ($months as $monthName => $monthNum) {
            if (str_contains($text, $monthName)) {
                $day = (int) preg_replace('/\D/', '', $text);
                if ($day < 1 || $day > 31) $day = 1;
                $year = date('Y');
                if (str_contains($text, 'вчера')) {
                    return new \DateTime('yesterday');
                }
                return new \DateTime("$year-$monthNum-$day");
            }
        }

        if (str_contains($text, 'дня назад') || str_contains($text, 'дней назад')) {
            $days = (int) preg_replace('/\D/', '', $text);
            return (new \DateTime())->modify("-$days days");
        }

        if (str_contains($text, 'час') || str_contains($text, 'часа')) {
            $hours = (int) preg_replace('/\D/', '', $text);
            return (new \DateTime())->modify("-$hours hours");
        }

        return new \DateTime($text);
    }
}
