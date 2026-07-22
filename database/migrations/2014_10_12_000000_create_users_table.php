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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->integer('user_role_id');
            $table->string('user_type')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_number_prefix')->nullable();
            $table->string('phone_number_country_code')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('social_type')->nullable();
            $table->string('social_id')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('is_active')->default('1');
            $table->unsignedBigInteger('is_approved')->default('0');
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->integer('year_of_experience')->nullable();
            $table->string('forgot_password_validate_string')->nullable();
            $table->integer('is_deleted')->default('0');
            $table->integer('is_maker')->default('0');
            $table->integer('is_receiver')->default('0');
            $table->string('image')->nullable();
            $table->string('original_image_name')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('verification_code')->nullable();
            $table->dateTime('verification_code_sent_time')->nullable();
            $table->integer('is_verified')->default('0');
            $table->integer('language')->nullable();
            $table->string('currency')->nullable();
            $table->integer('nationality')->nullable();
            $table->rememberToken()->nullable();
            $table->date('deleted_at')->nullable();
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
        Schema::dropIfExists('users');
    }
};
