<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('legal_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('type'); // privacy_policy, disclaimer
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('consent_data')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('user_id');
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('legal_consents');
    }
};
