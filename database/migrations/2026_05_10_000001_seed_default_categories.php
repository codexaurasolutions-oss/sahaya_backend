<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        $roles = [
            'Driver / Chauffeur',
            'Cook / Chef',
            'House Cleaner / Maid',
            'Baby Sitter / Nanny',
            'Housekeeper',
            'Gardener',
            'Security Guard',
            'Nurse / Caretaker',
            'Tutor / Teacher',
            'Plumber',
            'Electrician',
            'Carpenter',
            'Painter',
            'Sweeper',
            'Laundry / Ironing',
            'Dog Walker',
            'Personal Attendant',
            'Physiotherapist',
            'Elder Care',
            'Chef / Baker',
        ];

        foreach ($roles as $role) {
            $exists = DB::table('categories')
                ->where('name', $role)
                ->exists();
            if (!$exists) {
                DB::table('categories')->insert([
                    'name' => $role,
                    'is_deleted' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        $roles = [
            'Driver / Chauffeur', 'Cook / Chef', 'House Cleaner / Maid',
            'Baby Sitter / Nanny', 'Housekeeper', 'Gardener', 'Security Guard',
            'Nurse / Caretaker', 'Tutor / Teacher', 'Plumber', 'Electrician',
            'Carpenter', 'Painter', 'Sweeper', 'Laundry / Ironing', 'Dog Walker',
            'Personal Attendant', 'Physiotherapist', 'Elder Care', 'Chef / Baker',
        ];
        DB::table('categories')->whereIn('name', $roles)->delete();
    }
};
