<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep only the most recent budget per category if duplicates exist
        $duplicates = DB::table('budgets')
            ->select('category_id')
            ->groupBy('category_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('category_id');

        foreach ($duplicates as $categoryId) {
            $keepId = DB::table('budgets')
                ->where('category_id', $categoryId)
                ->orderByDesc('id')
                ->value('id');

            DB::table('budgets')
                ->where('category_id', $categoryId)
                ->where('id', '!=', $keepId)
                ->delete();
        }

        Schema::table('budgets', function (Blueprint $table) {
            if (Schema::hasColumn('budgets', 'month')) {
                $table->dropColumn('month');
            }
        });

        // Add unique constraint on category_id if not present
        Schema::table('budgets', function (Blueprint $table) {
            $table->unique('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropUnique(['category_id']);
            $table->string('month', 7)->default(now()->format('Y-m'));
        });
    }
};
