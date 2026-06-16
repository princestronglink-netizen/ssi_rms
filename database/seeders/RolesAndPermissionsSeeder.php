<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // HR Permissions
        $hrPermissions = [
            
        ];

        // Operations Permissions (full access)
        $operationsPermissions = [
            
        ];

        // Payroll Permissions
        $payrollPermissions = [
            
        ];

        // Finance Permissions
        $financePermissions = [
           
        ];

        // Purchasing Permissions
        $purchasingPermissions = [
            
        ];

        // Create all permissions
        foreach (array_merge(
            $hrPermissions,
            $operationsPermissions,
            $payrollPermissions,
            $financePermissions,
            $purchasingPermissions
        ) as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Super Admin — all permissions
        Role::firstOrCreate(['name' => 'super_admin'])
            ->givePermissionTo(Permission::all());

        // HR roles
        Role::firstOrCreate(['name' => 'hr_admin_specialist'])
            ->givePermissionTo($hrPermissions);
        Role::firstOrCreate(['name' => 'hr_manager'])
            ->givePermissionTo($this->filterViewPermissions($hrPermissions));

        // Operations roles — full access
        Role::firstOrCreate(['name' => 'operation_specialist'])
            ->givePermissionTo($operationsPermissions);
        Role::firstOrCreate(['name' => 'operation_manager'])
            ->givePermissionTo($operationsPermissions);

        // Payroll roles
        Role::firstOrCreate(['name' => 'payroll_specialist'])
            ->givePermissionTo($payrollPermissions);
        Role::firstOrCreate(['name' => 'payroll_manager'])
            ->givePermissionTo($this->filterViewPermissions($payrollPermissions));

        // Finance roles
        Role::firstOrCreate(['name' => 'finance_specialist'])
            ->givePermissionTo($financePermissions);
        Role::firstOrCreate(['name' => 'finance_manager'])
            ->givePermissionTo($this->filterViewPermissions($financePermissions));

        // Purchasing roles
        Role::firstOrCreate(['name' => 'purchasing_specialist'])
            ->givePermissionTo($purchasingPermissions);
        Role::firstOrCreate(['name' => 'purchasing_manager'])
            ->givePermissionTo($this->filterViewPermissions($purchasingPermissions));

        // Sample users
        $users = [
            ['name' => 'Super Admin',               'email' => 'superadmin@stronglink.com',     'role' => 'super_admin'],
            ['name' => 'HR Admin Specialist',       'email' => 'hrspec@stronglink.com',         'role' => 'hr_admin_specialist'],
            ['name' => 'HR Manager',                'email' => 'hrmanager@stronglink.com',      'role' => 'hr_manager'],
            ['name' => 'Operations Specialist',     'email' => 'operations@stronglink.com',     'role' => 'operation_specialist'],
            ['name' => 'Operations Manager',        'email' => 'operationsmgr@stronglink.com',  'role' => 'operation_manager'],
            ['name' => 'Payroll Specialist',        'email' => 'payroll@stronglink.com',        'role' => 'payroll_specialist'],
            ['name' => 'Payroll Manager',           'email' => 'payrollmgr@stronglink.com',     'role' => 'payroll_manager'],
            ['name' => 'Finance Specialist',        'email' => 'finance@stronglink.com',        'role' => 'finance_specialist'],
            ['name' => 'Finance Manager',           'email' => 'financemgr@stronglink.com',     'role' => 'finance_manager'],
            ['name' => 'Purchasing Specialist',     'email' => 'purchasing@stronglink.com',     'role' => 'purchasing_specialist'],
            ['name' => 'Purchasing Manager',        'email' => 'purchasingmgr@stronglink.com',  'role' => 'purchasing_manager'],
        ];

        foreach ($users as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name'     => $data['name'],
                    'password' => bcrypt('password123'),
                ]
            );
            $user->syncRoles([$data['role']]);
        }
    }

    /**
     * Filter only "view_" permissions for managers (read-only)
     */
    private function filterViewPermissions(array $permissions): array
    {
        return array_filter($permissions, fn($perm) => str_starts_with($perm, 'view_'));
    }
}