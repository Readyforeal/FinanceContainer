<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_income', 10, 2)->default(0);
            $table->decimal('total_spent', 10, 2)->default(0);
            $table->decimal('needs_spent', 10, 2)->default(0);
            $table->decimal('wants_spent', 10, 2)->default(0);
            $table->decimal('savings_spent', 10, 2)->default(0);
            $table->text('ai_analysis')->nullable();
            $table->text('ai_advice')->nullable();
            $table->json('habit_flags')->nullable();
            $table->timestamps();
            $table->unique(['type', 'period_start']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('summaries');
    }
};
