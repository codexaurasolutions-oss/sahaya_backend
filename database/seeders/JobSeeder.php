<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Job;
use App\Models\User;
use App\Models\JobApplication;

class JobSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        foreach (range(1, 10) as $index) {
            $user = User::where('user_role_id', 3)->inRandomOrder()->first();
            $cities = [
                ['city' => 'London', 'state' => 'Greater London'],
                ['city' => 'Manchester', 'state' => 'Greater Manchester'],
                ['city' => 'Birmingham', 'state' => 'West Midlands'],
            ];

            $location = $cities[array_rand($cities)];
            Job::create([
                'title' => 'Full-Time Nanny Required for Two Children ' . $index,
                'description' => 'Looking for an experienced nanny to care for two children.',
                'compensation' => rand(5000,90500),
                'expected_compensation' => rand(5000,90500),
                'compensation_type' => 'monthly',
                'street_address' => '123 Main Street',
                'city' => $location['city'],
                'state' => $location['state'],
                'zip_code' => 'SW1A 1AA',
                'commitment_type' => 'full-time',
                'preferred_hours' => '9-5',
                'preferred_days' => 'Monday to Friday',
                'status' => 'open',
                'childcare_experience' => true,
                'cooking_required' => true,
                'driving_license_required' => false,
                'first_aid_certified' => true,
                'pet_care_required' => false,
                'additional_requirements' => 'Must have at least 3 years of experience.',
                'required_skills' => 'Childcare, Meal Preparation, Communication',
                'created_by' => $user?->id ?? 1,
            ]);
        }

        foreach (range(1, 10) as $index) {
            $user = User::where('user_role_id', 3)->inRandomOrder()->first();
            $job = Job::inRandomOrder()->first();
            $staff = User::where('user_role_id', 2)->inRandomOrder()->first();

            JobApplication::create([
                'job_id' => $job?->id ?? 1,
                'user_id' => $staff?->id ?? 1,
                'application_status' => 'accepted',
                'cover_letter' => 'I am very interested in this position and have ' . $index . ' years of experience.',
                'expected_salary' => rand(9000,80500),
                'available_from' => now()->addDays(7),
                'is_advance' => $user->id % 2 == 0 ? true : false,
            ]);
        }
    }
}
