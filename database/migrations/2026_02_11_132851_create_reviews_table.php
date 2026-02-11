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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('yandex_id')->nullable()->comment('Unique review ID from Yandex');
            $table->string('author');
            $table->unsignedTinyInteger('rating')->default(0);
            $table->text('text')->nullable();
            $table->string('branch')->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('reviewed_at')->nullable()->comment('Original review date from Yandex');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'yandex_id']);
            $table->index(['user_id', 'reviewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
