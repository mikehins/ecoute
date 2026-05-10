<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ecoute_captures', 'screenshot_data')) {
            return;
        }

        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $table->dropColumn('screenshot_data');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('ecoute_captures', 'screenshot_data')) {
            return;
        }

        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $table->mediumText('screenshot_data')->nullable()->after('screenshot_disk');
        });
    }
};
