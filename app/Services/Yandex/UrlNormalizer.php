<?php

namespace App\Services\Yandex;

# ссылки к одному виду
final class UrlNormalizer
{
    private const CANONICAL_PATTERN = '#^https://yandex\.ru/maps/org/(\d+)/reviews/$#';

    # в canonical вид
    public function normalize(string $url): string
    {
        $id = $this->extractId($url);

        return "https://yandex.ru/maps/org/{$id}/reviews/";
    }

    # достать ID
    public function extractId(string $url): string
    {
        $url = trim($url);

        # 1. голая схема
        if (str_starts_with(strtolower($url), 'ymapsbm1://')) {
            $id = $this->extractOidParam($url);
            if ($id !== null) {
                return $id;
            }
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            throw new \InvalidArgumentException("Не удалось распознать ссылку: {$url}");
        }

        $host = strtolower($parts['host']);
        if (!str_contains($host, 'yandex') && $host !== 'ya.ru') {
            throw new \InvalidArgumentException("Не ссылка на Яндекс Карты: {$url}");
        }

        # 2. путь canonical
        $path = $parts['path'] ?? '';
        if (preg_match('#^/maps/org/(?:[^/]+/)?(\d+)/?(?:reviews/?)?$#', $path, $m)) {
            return $m[1];
        }

        # 3. ссылка с карты
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            $poiUri = $query['poi']['uri'] ?? null;
            if (is_string($poiUri) && $poiUri !== '') {
                # двойное кодирование
                if (str_contains($poiUri, '%')) {
                    $poiUri = urldecode($poiUri);
                }
                $id = $this->extractOidParam($poiUri);
                if ($id !== null) {
                    return $id;
                }
            }
        }

        throw new \InvalidArgumentException("Не удалось извлечь ID организации из ссылки: {$url}");
    }

    # достать oid
    private function extractOidParam(string $url): ?string
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!is_string($query) || $query === '') {
            return null;
        }
        parse_str($query, $params);
        $oid = $params['oid'] ?? null;

        return is_string($oid) && ctype_digit($oid) ? $oid : null;
    }
}
