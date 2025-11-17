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
            'Almaty City' => ['Almaty'],
            'Astana City' => ['Astana'],
            'Shymkent City' => ['Shymkent'],
            'Akmola' => ['Kokshetau', 'Stepnogorsk', 'Shchuchinsk', 'Atbasar'],
            'Aktobe' => ['Aktobe', 'Kandyagash', 'Khromtau', 'Emba'],
            'Almaty Region' => ['Taldykorgan', 'Kapshagay', 'Tekeli', 'Ushtobe'],
            'Atyrau' => ['Atyrau', 'Kulsary', 'Makat', 'Dossor'],
            'Baikonur' => ['Baikonur'],
            'East Kazakhstan' => ['Oskemen', 'Semey', 'Ridder', 'Zaysan'],
            'Jambyl' => ['Taraz', 'Shu', 'Korday', 'Merke'],
            'Karaganda' => ['Karaganda', 'Temirtau', 'Zhezkazgan', 'Balkhash'],
            'Kostanay' => ['Kostanay', 'Rudny', 'Lisakovsk', 'Arkalyk'],
            'Kyzylorda' => ['Kyzylorda', 'Aral', 'Kazaly'],
            'Mangystau' => ['Aktau', 'Zhanaozen', 'Fort-Shevchenko', 'Beyneu'],
            'North Kazakhstan' => ['Petropavl', 'Bulaevo', 'Mamlyutka', 'Sergeyevka'],
            'Pavlodar' => ['Pavlodar', 'Ekibastuz', 'Aksu', 'Kurchatov'],
            'West Kazakhstan' => ['Oral', 'Aksai', 'Chapayev'],
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
