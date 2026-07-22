<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('terminations', function (Blueprint $table) {
            $table->boolean('is_blacklist')->default(false)->after('remarks');
            $table->unsignedBigInteger('reported_by')->nullable()->after('is_blacklist');
            $table->string('police_station_name')->nullable()->after('reported_by');
            $table->string('police_station_contact')->nullable()->after('police_station_name');
            $table->text('police_station_address')->nullable()->after('police_station_contact');
            $table->text('fir_photo')->nullable()->after('police_station_address');

            $table
                ->foreign('reported_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('terminations', function (Blueprint $table) {
            $table->dropForeign(['reported_by']);
            $table->dropColumn([
                'is_blacklist',
                'reported_by',
                'police_station_name',
                'police_station_contact',
                'police_station_address',
                'fir_photo',
            ]);
        });
    }
};
