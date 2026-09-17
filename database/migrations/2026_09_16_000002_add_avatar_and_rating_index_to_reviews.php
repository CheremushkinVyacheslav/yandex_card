<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->string('avatar_url', 500)->nullable()->after('author');
            $table->index(['organization_id', 'rating'], 'reviews_org_rating_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_org_rating_idx');
            $table->dropColumn('avatar_url');
        });
    }
};
