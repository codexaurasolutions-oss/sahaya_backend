<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateJobsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('compensation', 10, 2)->nullable();
            $table->decimal('expected_compensation', 10, 2)->nullable();
            $table->string('compensation_type')->nullable(); // hourly, monthly, etc.
            $table->string('street_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('commitment_type')->nullable(); // full-time, part-time
            $table->string('preferred_hours')->nullable();
            $table->string('preferred_days')->nullable();
            $table->string('status')->default('open'); // open, closed, pending
            
            // Boolean fields as booleans
            $table->boolean('childcare_experience')->default(false);
            $table->boolean('cooking_required')->default(false);
            $table->boolean('driving_license_required')->default(false);
            $table->boolean('first_aid_certified')->default(false);
            $table->boolean('pet_care_required')->default(false);
            
            $table->text('additional_requirements')->nullable();
            $table->text('required_skills')->nullable();
            
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            
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
        Schema::dropIfExists('jobs');
    }
}
