<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
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
            Category::firstOrCreate(
                ['name' => $role],
                ['name' => $role, 'is_deleted' => 0]
            );
        }
    }
}
