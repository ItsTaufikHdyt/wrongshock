<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_bank_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('waste_bank_id')->constrained('waste_banks')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['waste_bank_id', 'user_id']);
            $table->index(['waste_bank_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        DB::table('waste_deposits')
            ->whereNotNull('waste_bank_id')
            ->select(['waste_bank_id', 'user_id'])
            ->distinct()
            ->orderBy('waste_bank_id')
            ->orderBy('user_id')
            ->get()
            ->each(function (object $row): void {
                DB::table('waste_bank_members')->insertOrIgnore([
                    'waste_bank_id' => $row->waste_bank_id,
                    'user_id' => $row->user_id,
                    'status' => 'active',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_bank_members');
    }
};
