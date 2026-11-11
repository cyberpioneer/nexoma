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
        Schema::table('users', function (Blueprint $table) {
            $table->string('preferred_language', 2)->nullable()->after('stripe_current_period_end');
            $table->string('bio', 420)->nullable()->after('preferred_language');
            $table->string('search_location', 120)->nullable()->after('bio');
            $table->unsignedSmallInteger('search_radius')->nullable()->after('search_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['preferred_language', 'bio', 'search_location', 'search_radius']);
        });
    }
};
