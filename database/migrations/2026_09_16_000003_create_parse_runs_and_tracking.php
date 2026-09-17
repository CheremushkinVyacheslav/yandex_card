<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parse_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->onDelete('cascade');
            $table->string('status')->default('parsing'); // parsing|success|failed
            $table->string('parser_version')->default('v2.0-ssr');
            $table->integer('found_total')->default(0);
            $table->integer('new_count')->default(0);
            $table->integer('changed_count')->default(0);
            $table->integer('deleted_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['organization_id', 'id']);
        });

        Schema::create('review_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->onDelete('cascade');
            $table->foreignId('parse_run_id')->constrained('parse_runs')->onDelete('cascade');
            $table->string('field', 50); // text|rating|author
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();
            $table->index(['parse_run_id', 'review_id']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false)->after('dislikes');
            $table->foreignId('created_in_run_id')->nullable()->after('is_deleted')->constrained('parse_runs')->nullOnDelete();
            $table->foreignId('updated_in_run_id')->nullable()->after('created_in_run_id')->constrained('parse_runs')->nullOnDelete();
            $table->foreignId('deleted_in_run_id')->nullable()->after('updated_in_run_id')->constrained('parse_runs')->nullOnDelete();
            $table->index(['organization_id', 'is_deleted'], 'reviews_org_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_org_deleted_idx');
            $table->dropConstrainedForeignId('deleted_in_run_id');
            $table->dropConstrainedForeignId('updated_in_run_id');
            $table->dropConstrainedForeignId('created_in_run_id');
            $table->dropColumn(['is_deleted']);
        });
        Schema::dropIfExists('review_changes');
        Schema::dropIfExists('parse_runs');
    }
};
