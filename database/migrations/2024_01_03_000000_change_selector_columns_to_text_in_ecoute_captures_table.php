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
            // Drop index if it exists, as TEXT columns cannot be indexed without a length
            // We use try-catch or check existence because it might have been dropped manually or failed before
            try {
                $table->dropIndex('ecoute_captures_element_selector_index');
            } catch (Exception $e) {
                // Index might not exist
            }

            $table->text('element_selector')->change();
            $table->text('parent_selector')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('ecoute_captures', function (Blueprint $table): void {
            $table->string('element_selector', 500)->change();
            $table->string('parent_selector', 500)->nullable()->change();
        });
    }
};
