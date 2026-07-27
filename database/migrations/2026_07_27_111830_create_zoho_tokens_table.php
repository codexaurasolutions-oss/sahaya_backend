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
        Schema::create('zoho_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('service')->comment('crm or desk');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('api_domain')->nullable();
            $table->integer('user_id')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            $table->unique('service');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('zoho_tokens');
    }
};
