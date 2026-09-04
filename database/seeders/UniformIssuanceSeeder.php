<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class UniformIssuanceSeeder extends Seeder
{
    public function run(): void
    {
        // Pull existing IDs dynamically so we never guess wrong
        $siteId = DB::table('sites')->pluck('id')->first();
        $uniformIssuanceTypeId = DB::table('uniform_issuance_types')->pluck('id')->first();
        $positionIds = DB::table('positions')->pluck('id')->toArray();
        $uniformItemIds = DB::table('uniform_items')->pluck('id')->toArray();
        $uniformItemVariantIds = DB::table('uniform_item_variants')->pluck('id')->toArray();

        // Guard clauses — bail early with a clear message if tables are empty
        if (!$siteId) {
            $this->command->error('No sites found. Please seed the sites table first.');
            return;
        }
        if (!$uniformIssuanceTypeId) {
            $this->command->error('No uniform_issuance_types found. Please seed that table first.');
            return;
        }
        if (empty($positionIds)) {
            $this->command->error('No positions found. Please seed the positions table first.');
            return;
        }
        if (empty($uniformItemIds)) {
            $this->command->error('No uniform_items found. Please seed that table first.');
            return;
        }
        if (empty($uniformItemVariantIds)) {
            $this->command->error('No uniform_item_variants found. Please seed that table first.');
            return;
        }

        $now = Carbon::now();

        // 1. Create the Uniform Issuance (Issued)
        $issuanceId = DB::table('uniform_issuances')->insertGetId([
            'site_id'                  => $siteId,
            'uniform_issuance_type_id' => $uniformIssuanceTypeId,
            'uniform_issuance_status'  => 'issued',
            'pending_at'               => $now->copy()->subDays(5)->toDateString(),
            'partial_at'               => null,
            'issued_at'                => $now->toDateString(),
            'cancelled_at'             => null,
            'signed_receiving_copy'    => 'signed_copy_' . Str::random(8) . '.pdf',
            'notes'                    => 'Seeded uniform issuance record.',
            'created_at'               => $now,
            'updated_at'               => $now,
        ]);

        $employeeStatuses = ['reliever', 'posted'];
        $firstNames = ['Juan', 'Maria', 'Jose', 'Ana', 'Pedro', 'Rosa', 'Carlos', 'Elena', 'Miguel', 'Sofia', 'Antonio', 'Carmen', 'Luis', 'Isabel', 'Manuel', 'Teresa', 'Ricardo', 'Patricia', 'Fernando', 'Gloria'];
        $lastNames  = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Ramos', 'Aquino', 'Castillo', 'Del Rosario', 'Villanueva', 'Pascual', 'Gonzales', 'Manalo', 'Domingo', 'Salazar', 'Fernandez'];

        // 2. Create 20 Recipients (all issued)
        for ($i = 0; $i < 20; $i++) {
            $recipientId = DB::table('uniform_issuance_recipients')->insertGetId([
                'uniform_issuance_id' => $issuanceId,
                'transaction_id'      => 'TXN-' . strtoupper(Str::random(10)) . '-' . ($i + 1),
                'employee_name'       => $firstNames[$i] . ' ' . $lastNames[$i],
                'position_id'         => $positionIds[array_rand($positionIds)],
                'employee_status'     => $employeeStatuses[array_rand($employeeStatuses)],
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            // 3. Create 1-3 items per recipient (fully released since "issued")
            $itemCount = rand(1, 3);
            for ($j = 0; $j < $itemCount; $j++) {
                $quantity = rand(1, 5);

                DB::table('uniform_issuance_items')->insert([
                    'uniform_issuance_recipient_id' => $recipientId,
                    'uniform_item_id'               => $uniformItemIds[array_rand($uniformItemIds)],
                    'uniform_item_variant_id'       => $uniformItemVariantIds[array_rand($uniformItemVariantIds)],
                    'uniform_issuance_type_id'      => $uniformIssuanceTypeId,
                    'quantity'                      => $quantity,
                    'released_quantity'             => $quantity,
                    'remaining_quantity'             => 0,
                    'created_at'                    => $now,
                    'updated_at'                    => $now,
                ]);
            }
        }

        $this->command->info('Seeded 1 Uniform Issuance with 20 recipients (status: issued).');
    }
}