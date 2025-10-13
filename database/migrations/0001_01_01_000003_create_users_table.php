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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('number')->unique()->index();
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('district_id'); 
            $table->unsignedBigInteger('sub_district_id'); 
            $table->string('address')->nullable();
            $table->bigInteger('balance')->default(0);
            $table->string('image')->nullable();
            $table->tinyInteger('status')->default(1)->comment('1=active, 0=inactive');
            $table->timestamps();

            $table->foreign('district_id')->references('id')->on('districts')->onDelete('cascade');
            $table->foreign('sub_district_id')->references('id')->on('sub_districts')->onDelete('cascade');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
