<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ecoute_captures', 'recording_path')) {
            return;
        }

        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $table->string('recording_path', 2048)->nullable()->after('screenshot_disk');
            $table->string('recording_disk')->nullable()->after('recording_path');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ecoute_captures', 'recording_path')) {
            return;
        }

        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $table->dropColumn(['recording_path', 'recording_disk']);
        });
    }
};
