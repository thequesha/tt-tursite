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
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('rating', 2, 1)->nullable()->after('yandex_url');
            $table->unsignedInteger('total_reviews')->nullable()->after('rating');
            $table->timestamp('last_synced_at')->nullable()->after('total_reviews');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['rating', 'total_reviews', 'last_synced_at']);
        });
    }
};
