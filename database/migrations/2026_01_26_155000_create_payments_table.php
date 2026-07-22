<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            
            $table->decimal('amount', 10, 2);
            $table->string('payment_id')->nullable();
            $table->string('order_id')->nullable();
            
            $table->text('reject_reason')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_mode')->nullable(); // Cash, UPI, Bank Transfer
            
            // Booking Details
            $table->string('full_name')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('alt_number')->nullable();
            $table->string('address')->nullable();
            $table->string('pin_code')->nullable();
            $table->integer('number_of_attendees')->nullable();
            $table->date('booking_date')->nullable();
            $table->string('event_time')->nullable();
            
            // Boolean flags for services needed
            $table->boolean('catering_needed')->default(false);
            $table->boolean('chef_needed')->default(false);
            $table->boolean('photographer_needed')->default(false);
            $table->boolean('decore_needed')->default(false);
            $table->boolean('groceries_needed')->default(false);
            
            $table->text('comment')->nullable();
            $table->string('lat')->nullable();
            $table->string('long')->nullable();
            $table->string('order_status')->nullable();
            
            $table->decimal('additional_amount', 10, 2)->nullable();
            
            // Salary Components
            $table->decimal('base_salary', 10, 2)->nullable();
            $table->decimal('performance_bonus', 10, 2)->nullable();
            $table->decimal('overtime_pay', 10, 2)->nullable();
            $table->decimal('tax_deduction', 10, 2)->nullable();
            $table->decimal('advance_payment', 10, 2)->nullable();
            $table->decimal('net_salary', 10, 2)->nullable();
            $table->string('salary_period')->nullable(); // e.g. "January 2026"

            $table->timestamps();

            // Foreign keys
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            // $table->foreign('staff_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('payments');
    }
}
