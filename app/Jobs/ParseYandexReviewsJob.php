<?php

namespace App\Jobs;

use App\Services\YandexMapsParser;
use App\Exceptions\CaptchaDetectedException;
use App\Exceptions\SourceChangedException;
use App\Models\Organization;
use App\Models\ReviewSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ParseYandexReviewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(protected Organization $organization) {}

    public function handle(YandexMapsParser $parser): void
    {
        try {
            $parser->parseOrganization($this->organization->yandex_url, $this->organization->id);
        } catch (CaptchaDetectedException $e) {
            $this->organization->update([
                'status' => 'captcha',
                'last_error' => $e->getMessage(),
            ]);
            Log::warning('Captcha detected for org ' . $this->organization->id);
            throw $e; // retry
        } catch (SourceChangedException $e) {
            $this->organization->update([
                'status' => 'failed',
                'last_error' => 'Разметка изменилась: ' . $e->getMessage(),
            ]);
            Log::error('Source changed for org ' . $this->organization->id, ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ParseYandexReviewsJob failed for organization ' . $this->organization->id . ': ' . $exception->getMessage());
    }
}
