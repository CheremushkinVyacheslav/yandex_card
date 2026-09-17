<?php

namespace Tests\Unit;

use App\Services\Yandex\UrlNormalizer;
use PHPUnit\Framework\TestCase;

class UrlNormalizerTest extends TestCase
{
    private UrlNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->normalizer = new UrlNormalizer();
    }

    public function test_canonical_url_passes_through(): void
    {
        $url = 'https://yandex.ru/maps/org/vkusno_i_tochka/32369324367/reviews/';

        // slug отбрасывается — canonical вид всегда без него
        $this->assertSame(
            'https://yandex.ru/maps/org/32369324367/reviews/',
            $this->normalizer->normalize($url)
        );
        $this->assertSame('32369324367', $this->normalizer->extractId($url));
    }

    public function test_canonical_without_slug_and_reviews_suffix(): void
    {
        $this->assertSame(
            'https://yandex.ru/maps/org/32369324367/reviews/',
            $this->normalizer->normalize('https://yandex.ru/maps/org/32369324367/reviews/')
        );
        $this->assertSame(
            'https://yandex.ru/maps/org/32369324367/reviews/',
            $this->normalizer->normalize('https://yandex.ru/maps/org/vkusno_i_tochka/32369324367/')
        );
    }

    public function test_map_link_with_poi_uri_normalizes(): void
    {
        $url = 'https://yandex.ru/maps/968/cherepovets/?ll=37.971474%2C59.123534&mode=poi&poi%5Bpoint%5D=37.967912%2C59.125816&poi%5Buri%5D=ymapsbm1%3A%2F%2Forg%3Foid%3D32369324367&tab=reviews&z=15';

        $this->assertSame(
            'https://yandex.ru/maps/org/32369324367/reviews/',
            $this->normalizer->normalize($url)
        );
        $this->assertSame('32369324367', $this->normalizer->extractId($url));
    }

    public function test_bare_ymapsbm1_scheme_works(): void
    {
        $url = 'ymapsbm1://org?oid=32369324367';

        $this->assertSame(
            'https://yandex.ru/maps/org/32369324367/reviews/',
            $this->normalizer->normalize($url)
        );
        $this->assertSame('32369324367', $this->normalizer->extractId($url));
    }

    public function test_broken_link_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->normalizer->normalize('https://example.com/some/page');
    }

    public function test_yandex_search_without_org_id_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->normalizer->extractId('https://yandex.ru/maps/55/moscow/?text=burgers');
    }

    public function test_same_id_from_all_formats(): void
    {
        $urls = [
            'https://yandex.ru/maps/org/vkusno_i_tochka/32369324367/reviews/',
            'https://yandex.ru/maps/org/32369324367/reviews/',
            'https://yandex.ru/maps/org/vkusno_i_tochka/32369324367/',
            'https://yandex.ru/maps/968/cherepovets/?ll=37.97%2C59.12&mode=poi&poi%5Buri%5D=ymapsbm1%3A%2F%2Forg%3Foid%3D32369324367&tab=reviews&z=15',
            'ymapsbm1://org?oid=32369324367',
        ];

        foreach ($urls as $url) {
            $this->assertSame('32369324367', $this->normalizer->extractId($url), "Failed for: {$url}");
        }
    }
}
