<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;
use App\Models\Region;
use App\Models\City;

class KazakhstanSeeder extends Seeder
{
    public function run(): void
    {
        // Create Kazakhstan country if it doesn't exist
        $kazakhstan = Country::firstOrCreate([
            'name' => 'Kazakhstan',
            'language' => 'kk'
        ]);

        // Kazakhstan regions (oblasts and cities of republican significance)
        $regions = [
            'Almaty' => ['Almaty', 'Taldykorgan', 'Kapchagay', 'Tekeli'],
            'Astana' => ['Astana', 'Kokshetau', 'Shchuchinsk'],
            'Shymkent' => ['Shymkent', 'Turkestan', 'Kentau'],
            'Akmola' => ['Kokshetau', 'Stepnogorsk', 'Shchuchinsk', 'Atbasar'],
            'Aktobe' => ['Aktobe', 'Kandyagash', 'Khromtau', 'Emba'],
            'Almaty Region' => ['Taldykorgan', 'Kapchagay', 'Tekeli', 'Ushtobe'],
            'Atyrau' => ['Atyrau', 'Kulsary', 'Makat', 'Dossor'],
            'Baikonur' => ['Baikonur'],
            'East Kazakhstan' => ['Oskemen', 'Semey', 'Ridder', 'Zaysan'],
            'Jambyl' => ['Taraz', 'Shymkent', 'Korday', 'Merke'],
            'Karaganda' => ['Karaganda', 'Temirtau', 'Zhezkazgan', 'Balkhash'],
            'Kostanay' => ['Kostanay', 'Rudny', 'Lisakovsk', 'Arkalyk'],
            'Kyzylorda' => ['Kyzylorda', 'Baikonur', 'Aral', 'Kazaly'],
            'Mangystau' => ['Aktau', 'Zhanaozen', 'Fort-Shevchenko', 'Beyneu'],
            'North Kazakhstan' => ['Petropavl', 'Bulaevo', 'Mamlyutka', 'Sergeyevka'],
            'Pavlodar' => ['Pavlodar', 'Ekibastuz', 'Aksu', 'Kurchatov'],
            'West Kazakhstan' => ['Oral', 'Aksai', 'Uralsk', 'Chapayev'],
            'Zhambyl' => ['Taraz', 'Shu', 'Korday', 'Merke']
        ];

        foreach ($regions as $regionName => $cities) {
            $region = Region::firstOrCreate([
                'name' => $regionName,
                'country_id' => $kazakhstan->id
            ]);

            foreach ($cities as $cityName) {
                City::firstOrCreate([
                    'name' => $cityName,
                    'region_id' => $region->id
                ]);
            }
        }
    }
}
