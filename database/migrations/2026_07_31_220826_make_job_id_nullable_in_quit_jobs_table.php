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
        Schema::table('quit_jobs', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
        });
        \DB::statement('ALTER TABLE quit_jobs MODIFY COLUMN job_id BIGINT UNSIGNED NULL');
        Schema::table('quit_jobs', function (Blueprint $table) {
            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('quit_jobs', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
        });
        \DB::statement('ALTER TABLE quit_jobs MODIFY COLUMN job_id BIGINT UNSIGNED NOT NULL');
        Schema::table('quit_jobs', function (Blueprint $table) {
            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('cascade');
        });
    }
};
