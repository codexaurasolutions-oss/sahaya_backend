<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'type')) {
                $table->string('type')->nullable()->after('message');
            }

            if (!Schema::hasColumn('notifications', 'job_id')) {
                $table->unsignedBigInteger('job_id')->nullable()->after('type');
            }

            if (!Schema::hasColumn('notifications', 'application_id')) {
                $table->unsignedBigInteger('application_id')->nullable()->after('job_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $drops = [];

            if (Schema::hasColumn('notifications', 'application_id')) {
                $drops[] = 'application_id';
            }

            if (Schema::hasColumn('notifications', 'job_id')) {
                $drops[] = 'job_id';
            }

            if (Schema::hasColumn('notifications', 'type')) {
                $drops[] = 'type';
            }

            if (!empty($drops)) {
                $table->dropColumn($drops);
            }
        });
    }
};
