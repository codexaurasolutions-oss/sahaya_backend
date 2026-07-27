<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_work_infos', function (Blueprint $table) {
            $table->unsignedTinyInteger('salary_closing_date')->nullable()->after('pay_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('user_work_infos', function (Blueprint $table) {
            $table->dropColumn('salary_closing_date');
        });
    }
};
