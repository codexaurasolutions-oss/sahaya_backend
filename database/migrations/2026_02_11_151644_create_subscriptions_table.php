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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->integer('validity'); // number of days / months (based on your logic)
            $table->string('type')->nullable();
            // Example: monthly, yearly, trial, etc.
            $table->string('razorpay_order_id')->nullable();
            $table->string('role_id')->nullable(); // housekeeping = 1, staff = 2.
            $table->json('extra')->nullable(); // JSON column
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
        Schema::dropIfExists('subscriptions');
    }
};
