<?php

namespace Database\Seeders;

use App\Models\Admin\TeacherRole;
use Illuminate\Database\Seeder;

class TeacherRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['role_name' => 'Principal'],
            ['role_name' => 'Vice Principal'],
            ['role_name' => 'HOD'],
            ['role_name' => 'Coordinator'],
            ['role_name' => 'Lab Assistant'],
            ['role_name' => 'Sports Instructor'],
        ];

        foreach ($roles as $role) {
            TeacherRole::firstOrCreate(
                ['role_name' => $role['role_name']],
                $role
            );
        }
    }
}
