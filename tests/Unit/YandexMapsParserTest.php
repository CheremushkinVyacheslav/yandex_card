<?php

namespace Tests\Unit;

use App\Clients\YandexMapsClient;
use Symfony\Component\DomCrawler\Crawler;
use PHPUnit\Framework\TestCase;

class YandexMapsParserTest extends TestCase
{
    public function test_extract_reviews_from_html_fragment(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
<script type="application/json" class="state-view">{"stack":[{"response":{"items":[{"id":"1","title":"Тест","fullAddress":"Москва","ratingData":{"ratingValue":4.5,"ratingCount":120,"reviewCount":25}}]}}]}</script>
</head>
<body>
<div class="business-review-view" itemProp="review" itemType="http://schema.org/Review" itemScope="">
  <div class="business-review-view__author-name" itemProp="author" itemType="http://schema.org/Person" itemScope=""><span itemProp="name">Иван</span></div>
  <div class="business-review-view__author-caption">Знаток города 5 уровня</div>
  <div class="business-review-view__rating">
    <div class="business-rating-badge-view__stars">
      <span class="business-rating-badge-view__star _full"></span>
      <span class="business-rating-badge-view__star _full"></span>
      <span class="business-rating-badge-view__star _full"></span>
      <span class="business-rating-badge-view__star _full"></span>
      <span class="business-rating-badge-view__star _full"></span>
    </div>
  </div>
  <div class="business-review-view__date">27 февраля</div>
  <div class="business-review-view__body" itemProp="reviewBody">Отличное место, всё понравилось.</div>
</div>
</body>
</html>
HTML;

        $client = new YandexMapsClient();
        $crawler = new Crawler($html);

        $this->assertGreaterThan(0, $crawler->filter('[itemType="http://schema.org/Review"]')->count());
        $this->assertGreaterThan(0, $crawler->filter('script.state-view')->count());
        $this->assertEquals('Отличное место, всё понравилось.', trim($crawler->filter('[itemProp="reviewBody"]')->text()));
        $this->assertEquals('Иван', trim($crawler->filter('[itemProp="name"]')->text()));

        $date = $client->parseRussianDate('27 февраля');
        $this->assertInstanceOf(\DateTime::class, $date);
    }
}
