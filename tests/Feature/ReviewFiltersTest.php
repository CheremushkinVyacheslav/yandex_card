<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\ParseRun;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Organization $org;
    private ParseRun $run1;
    private ParseRun $run2;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'Тест', 'yandex_url' => 'https://yandex.ru/maps/org/1/reviews/',
            'average_rating' => 4.2, 'total_ratings' => 10, 'total_reviews' => 4,
        ]);
        $this->run1 = ParseRun::create(['organization_id' => $this->org->id, 'status' => 'success']);
        $this->run2 = ParseRun::create(['organization_id' => $this->org->id, 'status' => 'success']);

        // старый отзыв без изменений, новый, изменённый и удалённый
        Review::create(['organization_id' => $this->org->id, 'external_id' => 'a', 'author' => 'A', 'author_name' => 'A', 'text' => 'old', 'rating' => 5, 'review_date' => now()]);
        Review::create(['organization_id' => $this->org->id, 'external_id' => 'b', 'author' => 'B', 'author_name' => 'B', 'text' => 'new one', 'rating' => 4, 'review_date' => now(), 'created_in_run_id' => $this->run2->id]);
        Review::create(['organization_id' => $this->org->id, 'external_id' => 'c', 'author' => 'C', 'author_name' => 'C', 'text' => 'changed', 'rating' => 3, 'review_date' => now(), 'updated_in_run_id' => $this->run2->id]);
        Review::create(['organization_id' => $this->org->id, 'external_id' => 'd', 'author' => 'D', 'author_name' => 'D', 'text' => 'gone', 'rating' => 2, 'review_date' => now(), 'is_deleted' => true, 'deleted_in_run_id' => $this->run2->id]);

        $this->token = User::factory()->create()->createToken('t')->plainTextToken;
    }

    private function apiGet(string $uri): array
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson($uri)
            ->assertOk()
            ->json();
    }

    public function test_default_feed_hides_deleted(): void
    {
        $j = $this->apiGet("/api/reviews?organization_id={$this->org->id}");
        $this->assertCount(3, $j['data']);
        $this->assertEquals($this->run2->id, $j['meta']['last_run_id']);
    }

    public function test_only_new_filter(): void
    {
        $j = $this->apiGet("/api/reviews?organization_id={$this->org->id}&only_new=1");
        $this->assertCount(1, $j['data']);
        $this->assertSame('B', $j['data'][0]['author']);
    }

    public function test_only_changed_filter(): void
    {
        $j = $this->apiGet("/api/reviews?organization_id={$this->org->id}&only_changed=1");
        $this->assertCount(1, $j['data']);
        $this->assertSame('C', $j['data'][0]['author']);
    }

    public function test_show_deleted_only(): void
    {
        $j = $this->apiGet("/api/reviews?organization_id={$this->org->id}&show_deleted_only=1");
        $this->assertCount(1, $j['data']);
        $this->assertTrue((bool) $j['data'][0]['is_deleted']);
    }

    public function test_new_first_sort(): void
    {
        $j = $this->apiGet("/api/reviews?organization_id={$this->org->id}&sort=new_first");
        $this->assertSame('B', $j['data'][0]['author']);
    }

    public function test_parse_runs_history(): void
    {
        $j = $this->apiGet("/api/parse-runs?organization_id={$this->org->id}");
        $this->assertCount(2, $j['data']);
        $this->assertEquals($this->run2->id, $j['data'][0]['id']);
    }

    public function test_diff_filters_need_second_run(): void
    {
        $org = Organization::create([
            'name' => 'Одна', 'yandex_url' => 'https://yandex.ru/maps/org/2/reviews/',
            'average_rating' => 5, 'total_ratings' => 1, 'total_reviews' => 1,
        ]);
        $run = ParseRun::create(['organization_id' => $org->id, 'status' => 'success']);
        Review::create(['organization_id' => $org->id, 'external_id' => 'x', 'author' => 'X', 'author_name' => 'X', 'text' => 't', 'rating' => 5, 'review_date' => now(), 'created_in_run_id' => $run->id]);

        $j = $this->apiGet("/api/reviews?organization_id={$org->id}&only_new=1");
        // без базовой линии фильтр не применяется, но meta честно сообщает состояние
        $this->assertCount(1, $j['data']);
        $this->assertEquals(1, $j['meta']['runs_count']);
        $this->assertFalse((bool) $j['meta']['has_baseline']);
        $this->assertEquals(0, $j['meta']['new_count']);
    }
}
