<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ecoute_captures', 'diagnostics')) {
            return;
        }

        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $table->json('diagnostics')->nullable()->after('nearby_text');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('ecoute_captures', 'diagnostics')) {
            return;
        }

        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $table->dropColumn('diagnostics');
        });
    }
};
