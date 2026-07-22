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
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staff_id');
            $table->decimal('basic_salary', 10, 2);
            $table->decimal('performative_allowance', 10, 2)->nullable();
            $table->decimal('over_time_allowance', 10, 2)->nullable();
            $table->decimal('tax', 10, 2)->nullable();
            $table->decimal('advance_payment', 10, 2)->nullable();
            $table->decimal('net_salary', 10, 2);
            $table->string('payment_mode')->nullable();
            $table->date('payment_date')->useCurrent();
            $table->enum('status', ['pending', 'paid'])->default('pending');
            $table->foreign('staff_id')->references('id')->on('users')->onDelete('cascade');
            $table->unsignedBigInteger('houseowner_id');
            $table->foreign('houseowner_id')->references('id')->on('users')->onDelete('cascade');
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
        Schema::dropIfExists('salaries');
    }
};
