<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLastWorkExperiencesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('last_work_experiences')) {
            Schema::create('last_work_experiences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('role')->nullable();
                $table->date('join_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('salary')->nullable();
                $table->string('working_hours')->nullable();
                $table->string('house_sold')->nullable(); // Assuming string as it might be 'yes'/'no' or address
                $table->string('owner_name')->nullable();
                $table->string('contact_number')->nullable();
                $table->string('state')->nullable();
                $table->string('city')->nullable();
                $table->softDeletes();
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
        Schema::dropIfExists('last_work_experiences');
    }
}
