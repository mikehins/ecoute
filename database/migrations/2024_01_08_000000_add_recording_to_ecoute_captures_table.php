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
            if (! Schema::hasColumn('ecoute_captures', 'recording_path')) {
                $table->string('recording_path', 500)->nullable()->after('screenshot_disk');
            }
            if (! Schema::hasColumn('ecoute_captures', 'recording_disk')) {
                $table->string('recording_disk', 50)->nullable()->after('recording_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $columns = [];
            if (Schema::hasColumn('ecoute_captures', 'recording_disk')) {
                $columns[] = 'recording_disk';
            }
            if (Schema::hasColumn('ecoute_captures', 'recording_path')) {
                $columns[] = 'recording_path';
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
