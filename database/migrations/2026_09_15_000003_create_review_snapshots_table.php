<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->integer('total_reviews');
            $table->float('average_rating');
            $table->json('metrics_snapshot'); // counts, ratings breakdown, etc.
            $table->string('parser_version');
            $table->string('status'); // ok, changed, error, unchanged
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_snapshots');
    }
};
