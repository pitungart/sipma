<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Master data lebih dulu: program butuh fakultas, pendaftaran butuh negara.
        $this->call([
            CountrySeeder::class,
            FacultySeeder::class,
            ProgramSeeder::class,
            NumberFormatSeeder::class,
        ]);

        User::firstOrCreate(
            ['email' => 'superadmin@sipma.test'],
            [
                'name' => 'Super Admin KUI',
                'password' => 'password',
                'role' => UserRole::SuperAdmin,
                'email_verified_at' => now(),
            ],
        );
    }
}
