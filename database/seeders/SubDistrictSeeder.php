<?php

namespace Database\Seeders;

use App\Models\SubDistrict as Kelurahan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubDistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         $kel = [
            //Bontang Barat
            [
                'id' => '1',
                'district_id' => '1',
                'name' => 'Belimbing',
            ],
            [
                'id' => '2',
                'district_id' => '1',
                'name' => 'Kanaan',
            ],
            [
                'id' => '3',
                'district_id' => '1',
                'name' => 'Telihan',
            ],
            //Bontang Selatan
            [
                'id' => '4',
                'district_id' => '2',
                'name' => 'Berbas Pantai',
            ],
            [
                'id' => '5',
                'district_id' => '2',
                'name' => 'Berbas Tengah',
            ],
            [
                'id' => '6',
                'district_id' => '2',
                'name' => 'Bontang Lestari',
            ],
            [
                'id' => '7',
                'district_id' => '2',
                'name' => 'Satimpo',
            ],
            [
                'id' => '8',
                'district_id' => '2',
                'name' => 'Tanjung Laut',
            ],
            [
                'id' => '9',
                'district_id' => '2',
                'name' => 'Tanjung Laut Indah',
            ],
            //Bontang Utara
            [
                'id' => '10',
                'district_id' => '3',
                'name' => 'Api Api',
            ],
            [
                'id' => '11',
                'district_id' => '3',
                'name' => 'Bontang Baru',
            ],
            [
                'id' => '12',
                'district_id' => '3',
                'name' => 'Bontang Kuala',
            ],
            [
                'id' => '13',
                'district_id' => '3',
                'name' => 'Guntung',
            ],
            [
                'id' => '14',
                'district_id' => '3',
                'name' => 'Gunung Elai',
            ],
            [
                'id' => '15',
                'district_id' => '3',
                'name' => 'Loktuan',
            ],
        ];

        foreach ($kel as $key => $kel) {
            Kelurahan::create($kel);
        }
    }
}
