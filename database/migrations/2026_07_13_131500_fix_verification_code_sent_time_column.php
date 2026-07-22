<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('users', 'verification_code_sent_time')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY verification_code_sent_time DATETIME NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN verification_code_sent_time TYPE TIMESTAMP(0) WITHOUT TIME ZONE USING verification_code_sent_time::timestamp');
        }
    }

    public function down()
    {
        if (!Schema::hasColumn('users', 'verification_code_sent_time')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE users MODIFY verification_code_sent_time DATE NULL');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN verification_code_sent_time TYPE DATE USING verification_code_sent_time::date');
        }
    }
};
