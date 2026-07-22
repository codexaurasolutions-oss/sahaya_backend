<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLeaveTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Seed some default data
        DB::table('leave_types')->insert([
            ['name' => 'Sick Leave', 'description' => 'Leave for health reasons', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Casual Leave', 'description' => 'Leave for personal reasons', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Annual Leave', 'description' => 'Yearly paid leave', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('leave_types');
    }
}
