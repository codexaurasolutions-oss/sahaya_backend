<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('attendance')) {
            Schema::create('attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
                $table->date('date');
                $table->string('status'); // present, absent, late
                $table->time('check_in_time')->nullable();
                $table->integer('late_minutes')->default(0);
                
                // Allow null leave_id, constrained to leave_types
                $table->foreignId('leave_id')->nullable()->constrained('leave_types')->nullOnDelete();
                
                $table->text('description')->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('attendance');
    }
}
