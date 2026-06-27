<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ecoute_captures', 'github_pr_url')) {
            return;
        }

        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $table->string('github_pr_url', 500)->nullable()->after('github_issue_url');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ecoute_captures', 'github_pr_url')) {
            return;
        }

        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $table->dropColumn('github_pr_url');
        });
    }
};
