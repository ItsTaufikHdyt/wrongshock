<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WasteItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'category' => 'Daun Kering',
                'output' => 'Kompos',
                'unit' => 'Kilogram (Kg)',
                'price' => 400,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Sisa Makanan',
                'output' => 'Kompos',
                'unit' => 'Kilogram (Kg)',
                'price' => 300,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Minyak Jelantah',
                'output' => 'Kriya',
                'unit' => 'Kilogram (Kg)',
                'price' => 3000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Tutup Botol (Le Minerale)',
                'output' => 'Kriya',
                'unit' => 'Kilogram (Kg)',
                'price' => 2300,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Tutup Botol (Aqua)',
                'output' => 'Kriya',
                'unit' => 'Kilogram (Kg)',
                'price' => 2400,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Tutup Botol (Cleo)',
                'output' => 'Kriya',
                'unit' => 'Kilogram (Kg)',
                'price' => 2400,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Botol Plastik Tanpa Tutup - Le Minerale',
                'output' => 'Kriya',
                'unit' => 'Kilogram (Kg)',
                'price' => 1300,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Botol Plastik Tanpa Tutup - Aqua',
                'output' => 'Kriya',
                'unit' => 'Kilogram (Kg)',
                'price' => 1400,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Botol Plastik Tanpa Tutup - Cleo',
                'output' => 'Kriya',
                'unit' => 'Kilogram (Kg)',
                'price' => 1300,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Kertas Bekas',
                'output' => 'Kriya',
                'unit' => 'Kilogram (Kg)',
                'price' => 700,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Kardus',
                'output' => 'Kriya',
                'unit' => 'Kilogram (Kg)',
                'price' => 400,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category' => 'Piring Telur',
                'output' => 'Kriya',
                'unit' => 'Kilogram (Kg)',
                'price' => 300,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($items as $item) {
            DB::table('waste_items')->updateOrInsert(
                [
                    'category' => $item['category'],
                    'output' => $item['output'],
                    'unit' => $item['unit'],
                ],
                ['price' => $item['price'], 'updated_at' => now(), 'created_at' => $item['created_at']],
            );
        }
    }
}
