<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('id');
            $table->string('address', 500)->nullable()->after('name');
            $table->decimal('rating', 2, 1)->nullable()->after('address');
            $table->integer('rating_count')->default(0)->after('rating');
            $table->integer('review_count')->default(0)->after('rating_count');
            $table->json('rubrics')->nullable()->after('review_count');
            $table->string('status')->default('pending')->after('rubrics');
            $table->timestamp('parsed_at')->nullable()->after('status');
            $table->text('last_error')->nullable()->after('parsed_at');
            $table->unique('external_id');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->string('external_id', 100)->nullable()->after('organization_id');
            $table->string('author')->nullable()->after('external_id');
            $table->string('author_level', 100)->nullable()->after('author');
            $table->text('business_reply')->nullable()->after('text');
            $table->timestamp('business_reply_date')->nullable()->after('business_reply');
            $table->integer('likes')->default(0)->after('business_reply_date');
            $table->integer('dislikes')->default(0)->after('likes');
            $table->unique(['organization_id', 'external_id'], 'reviews_org_external_unique');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['external_id']);
            $table->dropColumn(['external_id', 'address', 'rating', 'rating_count', 'review_count', 'rubrics', 'status', 'parsed_at', 'last_error']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('reviews_org_external_unique');
            $table->dropColumn(['external_id', 'author', 'author_level', 'business_reply', 'business_reply_date', 'likes', 'dislikes']);
        });
    }
};
