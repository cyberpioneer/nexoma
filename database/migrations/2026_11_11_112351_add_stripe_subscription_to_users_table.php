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
            $table->string('stripe_customer_id', 64)->nullable()->unique()->after('consent_version');
            $table->string('stripe_subscription_id', 64)->nullable()->unique()->after('stripe_customer_id');
            $table->string('stripe_subscription_status', 32)->nullable()->after('stripe_subscription_id');
            $table->timestamp('stripe_current_period_end')->nullable()->after('stripe_subscription_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_customer_id',
                'stripe_subscription_id',
                'stripe_subscription_status',
                'stripe_current_period_end',
            ]);
        });
    }
};
