<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('role')->nullable();
            
            $table->string('transaction_id')->unique();
            $table->string('type')->nullable(); // salary, booking, etc.
            $table->string('order_id')->nullable();
            $table->string('order_number')->nullable();
            $table->string('reference_id')->nullable();
            
            $table->decimal('amount', 10, 2);
            $table->string('currency')->default('USD'); // Default as per controller but user seems to be in India (+91)
            
            $table->string('payment_mode')->nullable();
            $table->string('payment_status')->default('pending');
            $table->text('payment_response')->nullable();
            $table->string('for_entry')->nullable(); // salary_payment, etc.
            
            $table->unsignedBigInteger('created_by')->nullable();
            
            $table->timestamps();
            
             // $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
