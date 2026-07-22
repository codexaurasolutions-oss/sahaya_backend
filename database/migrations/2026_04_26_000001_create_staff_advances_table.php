<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('staff_advances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employer_id');  // house owner
            $table->unsignedBigInteger('staff_id');
            $table->decimal('amount', 12, 2);           // total advance given
            $table->decimal('remaining_balance', 12, 2); // remaining to recover
            $table->enum('deduction_type', ['full', 'installment', 'manual'])->default('manual');
            $table->decimal('installment_amount', 12, 2)->nullable(); // monthly fixed deduction
            $table->enum('status', ['active', 'closed'])->default('active');
            $table->string('remarks')->nullable();
            $table->date('given_date');
            $table->foreign('employer_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('staff_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('advance_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('advance_id');
            $table->unsignedBigInteger('staff_id');
            $table->unsignedBigInteger('employer_id');
            $table->decimal('deducted_amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->unsignedBigInteger('salary_id')->nullable(); // which salary triggered this
            $table->string('note')->nullable();
            $table->foreign('advance_id')->references('id')->on('staff_advances')->onDelete('cascade');
            $table->foreign('staff_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('employer_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('advance_transactions');
        Schema::dropIfExists('staff_advances');
    }
};
