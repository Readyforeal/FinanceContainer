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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plaid_connection_id')->constrained()->cascadeOnDelete();
            $table->string('plaid_account_id');
            $table->string('name');
            $table->string('type');
            $table->decimal('current_balance', 12, 2)->default(0);
            $table->decimal('available_balance', 12, 2)->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
