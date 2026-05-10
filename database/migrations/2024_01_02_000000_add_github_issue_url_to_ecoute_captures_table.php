<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $table->string('github_issue_url')->nullable()->after('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $table->dropColumn('github_issue_url');
        });
    }
};
