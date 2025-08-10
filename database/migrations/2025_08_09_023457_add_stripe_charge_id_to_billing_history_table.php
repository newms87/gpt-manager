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
        Schema::table('billing_history', function (Blueprint $table) {
            $table->string('stripe_charge_id')->nullable()->after('stripe_payment_intent_id');
            $table->index(['stripe_charge_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billing_history', function (Blueprint $table) {
            $table->dropIndex(['stripe_charge_id']);
            $table->dropColumn('stripe_charge_id');
        });
    }
};
