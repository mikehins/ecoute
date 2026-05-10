<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecoute_captures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Selector fields
            $table->string('element_selector')->index();
            $table->string('parent_selector')->nullable();
            $table->json('fallback_selectors')->nullable();

            // Element content for AI context
            $table->text('element_html');
            $table->text('parent_html')->nullable();

            // data-* attributes stored separately (not embedded in HTML)
            $table->json('attributes')->nullable();

            // Surrounding context
            $table->json('nearby_text');
            $table->text('user_prompt');

            // Page metadata
            $table->json('interaction'); // { page_title, url, timestamp, input_method }

            // Deduplication — SHA-256 of (element_selector|normalised_prompt|url)
            $table->string('deduplication_hash', 64)->index();
            $table->index(['deduplication_hash', 'created_at']);

            // Screenshot
            $table->string('screenshot_path')->nullable();
            $table->string('screenshot_disk', 32)->nullable();

            // AI output
            $table->json('ai_response')->nullable(); // { title, description, type, suggested_fix }
            $table->string('prompt_version', 20)->nullable(); // e.g. "v1" — NOT embedded in ai_response JSON

            // Status
            $table->string('status', 20)->default('pending')->index(); // pending|processing|completed|failed
            $table->text('failure_reason')->nullable();

            $table->timestamps();
            $table->timestamp('processed_at')->nullable();

            $table->index('created_at');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecoute_captures');
    }
};
