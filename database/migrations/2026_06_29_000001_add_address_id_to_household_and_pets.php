<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_household_informations', function (Blueprint $table) {
            $table->foreignId('address_id')->nullable()->after('user_id')->constrained('user_addresses')->onDelete('cascade');
        });

        Schema::table('user_pet_details', function (Blueprint $table) {
            $table->foreignId('address_id')->nullable()->after('user_id')->constrained('user_addresses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('user_household_informations', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
            $table->dropColumn('address_id');
        });

        Schema::table('user_pet_details', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
            $table->dropColumn('address_id');
        });
    }
};
