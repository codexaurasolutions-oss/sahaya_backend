<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMissingUserTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. User Addresses
        if (!Schema::hasTable('user_addresses')) {
            Schema::create('user_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('street')->nullable();
                $table->string('city')->nullable();
                $table->string('state')->nullable();
                $table->string('pincode')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }

        // 2. User Pet Details
        if (!Schema::hasTable('user_pet_details')) {
            Schema::create('user_pet_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('pet_type')->nullable();
                $table->integer('pet_count')->nullable();
                $table->timestamps();
            });
        }

        // 3. User Household Information
        if (!Schema::hasTable('user_household_informations')) {
            Schema::create('user_household_informations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('residence_type')->nullable();
                $table->integer('number_of_rooms')->nullable();
                $table->json('languages_spoken')->nullable(); // Cast as array in model
                $table->integer('adults_count')->nullable();
                $table->integer('children_count')->nullable();
                $table->integer('elderly_count')->nullable();
                $table->text('special_requirements')->nullable();
                $table->timestamps();
            });
        }

        // 4. User Work Info
        if (!Schema::hasTable('user_work_infos')) {
            Schema::create('user_work_infos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('primary_role')->nullable(); // Cast as array in model, but text/json in DB
                $table->json('skills')->nullable();
                $table->json('languages_spoken')->nullable();
                $table->string('total_experience')->nullable();
                $table->string('education')->nullable();
                $table->text('additional_info')->nullable();
                $table->string('voice_note')->nullable();
                $table->string('emergency_contact_number')->nullable();
                $table->string('emergency_contact_name')->nullable();
                $table->json('working_days')->nullable();
                $table->string('pay_frequency')->nullable();
                $table->string('salary')->nullable();
                $table->date('joining_date')->nullable();
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
        Schema::dropIfExists('user_work_infos');
        Schema::dropIfExists('user_household_informations');
        Schema::dropIfExists('user_pet_details');
        Schema::dropIfExists('user_addresses');
    }
}
