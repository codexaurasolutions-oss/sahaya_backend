<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_advances', function (Blueprint $table) {
            $table->unsignedInteger('num_installments')->nullable()->after('installment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('staff_advances', function (Blueprint $table) {
            $table->dropColumn('num_installments');
        });
    }
};
