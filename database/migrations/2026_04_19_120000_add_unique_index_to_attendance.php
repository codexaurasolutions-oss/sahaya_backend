<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a unique composite index on (staff_id, date) to the attendance table
 * so concurrent / repeated auto-mark invocations can never insert duplicate
 * rows for the same staff on the same day.
 *
 * Before adding the index we must purge any existing duplicates, otherwise
 * index creation fails. We keep the lowest-id row per (staff_id, date) group
 * and delete the rest.
 */
class AddUniqueIndexToAttendance extends Migration
{
    public function up()
    {
        // 1) Delete duplicate rows keeping the earliest (smallest id) row per
        //    (staff_id, date). Using a self-join because MySQL does not allow
        //    selecting from the same table we delete from in a subquery.
        DB::statement('
            DELETE a1
            FROM attendance a1
            INNER JOIN attendance a2
                ON a1.staff_id = a2.staff_id
                AND a1.date = a2.date
                AND a1.id > a2.id
        ');

        // 2) Now it is safe to add the unique composite index.
        Schema::table('attendance', function (Blueprint $table) {
            // Skip if already present (running the migration twice).
            $indexes = collect(DB::select("SHOW INDEX FROM attendance"))
                ->pluck('Key_name')
                ->unique()
                ->toArray();

            if (!in_array('attendance_staff_id_date_unique', $indexes)) {
                $table->unique(['staff_id', 'date'], 'attendance_staff_id_date_unique');
            }
        });
    }

    public function down()
    {
        Schema::table('attendance', function (Blueprint $table) {
            $indexes = collect(DB::select("SHOW INDEX FROM attendance"))
                ->pluck('Key_name')
                ->unique()
                ->toArray();

            if (in_array('attendance_staff_id_date_unique', $indexes)) {
                $table->dropUnique('attendance_staff_id_date_unique');
            }
        });
    }
}
