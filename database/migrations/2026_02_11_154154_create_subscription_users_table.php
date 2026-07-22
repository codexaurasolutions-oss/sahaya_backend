<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('subscription_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->constrained('subscriptions')->onDelete('cascade');
            $table->string('role')->nullable();
            // Payment & Order Info
            $table->string('transaction_id')->nullable()->index();
            $table->string('type')->nullable();
            $table->string('order_id')->nullable()->index();
            $table->string('order_number')->nullable();
            $table->string('reference_id')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('currency', 10)->default('INR');
            $table->string('payment_mode')->nullable(); 
            // example: razorpay, stripe, cash
            $table->string('payment_status')->default('pending'); 
            // pending, paid, failed, refunded
            $table->json('payment_response')->nullable();
            // Subscription Period
            $table->boolean('for_entry')->default(false);
            $table->dateTime('start_date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->string('status')->default('active'); 
            // active, expired, cancelled
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_users');
    }
};
