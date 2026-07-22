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
        Schema::create('acl_admin_actions', function (Blueprint $table) {
            $table->id();
            $table->integer('admin_module_id')->nullable();
            $table->string('name')->nullable();    
           $table->string('function_name')->nullable();
            $table->integer('is_show')->default('1');
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
        Schema::dropIfExists('acl_admin_actions');
    }
};
