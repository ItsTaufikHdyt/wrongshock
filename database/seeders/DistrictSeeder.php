<?php

namespace Database\Seeders;

use App\Models\District as Kecamatan;
use Illuminate\Database\Seeder;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kec = [
            [
                'id' => '1',
                'name' => 'Bontang Barat',
            ],
            [
                'id' => '2',
                'name' => 'Bontang Selatan',
            ],
            [
                'id' => '3',
                'name' => 'Bontang Utara',
            ],
        ];

        foreach ($kec as $key => $kec) {
            Kecamatan::query()->updateOrCreate(['id' => $kec['id']], ['name' => $kec['name']]);
        }
    }
}
