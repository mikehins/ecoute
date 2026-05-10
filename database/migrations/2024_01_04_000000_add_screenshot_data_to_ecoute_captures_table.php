<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecoute_captures', function (Blueprint $table) {
            // Raw base64 image data (no data-URL prefix) used to upload the screenshot
            // directly to GitHub without requiring a publicly-accessible disk URL.
            $table->mediumText('screenshot_data')->nullable()->after('screenshot_disk');
        });
    }

    public function down(): void
    {
        Schema::table('ecoute_captures', function (Blueprint $table) {
            $table->dropColumn('screenshot_data');
        });
    }
};
