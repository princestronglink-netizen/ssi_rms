<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'Human Resources',
            'Finance',
            'IT / Technical',
            'Operations',
            'Payroll',
        ];

        foreach ($departments as $dept) {
            DB::table('departments')->insert([
                'department_name' => $dept,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}