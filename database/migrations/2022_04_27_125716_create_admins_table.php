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
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('user_role')->nullable();
            $table->string('user_role_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable() ;
            $table->string('phone_number')->nullable();
            $table->string('phone_number_prefix')->nullable();
            $table->string('phone_number_country_code')->nullable();
            $table->string('password')->nullable();
            $table->string('is_active')->default('1');
            $table->string('forgot_password_validate_string')->nullable();
            $table->integer('is_deleted')->default('0');
            $table->integer('mangopay_user_id')->nullable();
            $table->integer('mangopay_user_wallet_id')->nullable();
            $table->integer('department_id')->nullable();
            $table->integer('designation_id')->nullable();   
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
        Schema::dropIfExists('admins');
    }
};
