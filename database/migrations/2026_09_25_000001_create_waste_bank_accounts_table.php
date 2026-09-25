<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('waste_bank_id')->constrained('waste_banks')->restrictOnDelete();
            $table->bigInteger('balance')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'waste_bank_id']);
            $table->index('user_id');
            $table->index('waste_bank_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_bank_accounts');
    }
};
