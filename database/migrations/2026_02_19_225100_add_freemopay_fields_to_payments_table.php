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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('transaction_reference')->nullable()->after('reference');
            $table->string('phone')->nullable()->after('phone_number');
            $table->json('provider_response')->nullable()->after('gateway_response');
            $table->timestamp('completed_at')->nullable()->after('paid_at');
            $table->text('failure_reason')->nullable()->after('completed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['transaction_reference', 'phone', 'provider_response', 'completed_at', 'failure_reason']);
        });
    }
};
