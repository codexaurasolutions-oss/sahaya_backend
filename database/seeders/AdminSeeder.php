<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('roles')->insert([
            'name' => "Admin",
            'slug' => "admin",
        ]);

        DB::table('roles')->insert([
            'name' => "Staff",
            'slug' => "staff",
        ]);

        DB::table('roles')->insert([
            'name' => "HouseHolder",
            'slug' => "householder",
        ]);

        DB::table('users')->insert([
            'user_role_id' => "1",
            'name' => "admin",
            'email' => "admin@yopmail.com",
            'phone_number' => "9988776655",
            'is_active' => "1",
            'password' => Hash::make('Admin@123'),
        ]);

        foreach (range(1, 10) as $index) {
            DB::table('users')->insert([
                'user_role_id' => "3",
                'name' => "householder" . $index,
                'email' => "householder" . $index . "@yopmail.com",
                'phone_number' => "78787878" . str_pad($index, 2, '0', STR_PAD_LEFT),
                'is_active' => "1",
                'password' => Hash::make('Householder@123'),
            ]);
        }
        
        foreach (range(1, 10) as $index) {
            $houseowner = User::where('user_role_id', 3)->inRandomOrder()->first();
            DB::table('users')->insert([
                'user_role_id' => "2",
                'name' => "Staff" . $index,
                'email' => "staff" . $index . "@yopmail.com",
                'phone_number' => "78787878" . str_pad($index, 2, '0', STR_PAD_LEFT),
                'is_active' => "1",
                'password' => Hash::make('Staff@123'),
                'parent_user_id' => $houseowner->id,
                'added_by' => $houseowner->id
            ]);
        }

        
        
         
    
    }
}
