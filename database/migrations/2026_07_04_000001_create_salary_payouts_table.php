<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('salary_payouts')) {
        Schema::create('salary_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_id')->nullable();
            $table->foreignId('staff_id')->nullable();
            $table->foreignId('houseowner_id')->nullable();
            $table->foreignId('bank_account_id')->nullable();
            $table->foreignId('requested_by')->nullable();
            $table->string('contact_id')->nullable()->index();
            $table->string('fund_account_id')->nullable()->index();
            $table->string('payout_id')->nullable()->index();
            $table->string('reference_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('INR');
            $table->string('mode', 30)->default('bank_transfer');
            $table->string('purpose', 50)->default('salary');
            $table->string('status', 30)->default('initiated');
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('narration')->nullable();
            $table->boolean('queue_if_low_balance')->default(true);
            $table->string('utr')->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
        }
    }

    public function down()
    {
        Schema::dropIfExists('salary_payouts');
    }
};
