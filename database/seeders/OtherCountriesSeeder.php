<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Country;
use App\Models\Region;
use Illuminate\Database\Seeder;

class OtherCountriesSeeder extends Seeder
{
    public function run(): void
    {
        // Create other countries with their regions and cities
        $countries = [
            'Russia' => [
                'Moscow' => ['Moscow', 'Zelenograd', 'Troitsk'],
                'Saint Petersburg' => ['Saint Petersburg', 'Pushkin', 'Peterhof'],
                'Novosibirsk' => ['Novosibirsk', 'Berdske', 'Ob'],
                'Yekaterinburg' => ['Yekaterinburg', 'Nizhny Tagil', 'Kamensk-Uralsky'],
                'Kazan' => ['Kazan', 'Naberezhnye Chelny', 'Almetyevsk']
            ],
            'Uzbekistan' => [
                'Tashkent' => ['Tashkent', 'Chirchiq', 'Angren'],
                'Samarkand' => ['Samarkand', 'Kattakurgan', 'Urgut'],
                'Bukhara' => ['Bukhara', 'Gijduvan', 'Romitan'],
                'Andijan' => ['Andijan', 'Khanabad', 'Asaka'],
                'Fergana' => ['Fergana', 'Margilan', 'Kokand']
            ],
            'Kyrgyzstan' => [
                'Bishkek' => ['Bishkek', 'Tokmok', 'Kara-Balta'],
                'Osh' => ['Osh', 'Kara-Suu', 'Nookat'],
                'Jalal-Abad' => ['Jalal-Abad', 'Kok-Jangak', 'Tash-Kumyr'],
                'Talas' => ['Talas', 'Kyzyl-Adyr', 'Manas'],
                'Naryn' => ['Naryn', 'At-Bashy', 'Kochkor']
            ],
            'Tajikistan' => [
                'Dushanbe' => ['Dushanbe', 'Vahdat', 'Rogun'],
                'Khujand' => ['Khujand', 'Buston', 'Isfara'],
                'Kulob' => ['Kulob', 'Muminobod', 'Vose'],
                'Qurghonteppa' => ['Qurghonteppa', 'Vahsh', 'Dangara'],
                'Istaravshan' => ['Istaravshan', 'Guliston', 'Shahriston']
            ],
            'Turkmenistan' => [
                'Ashgabat' => ['Ashgabat', 'Abadan', 'Bereket'],
                'Türkmenabat' => ['Türkmenabat', 'Atamyrat', 'Farap'],
                'Daşoguz' => ['Daşoguz', 'Boldumsaz', 'Görogly'],
                'Mary' => ['Mary', 'Baýramaly', 'Serhetabat'],
                'Balkanabat' => ['Balkanabat', 'Türkmenbaşy', 'Gumdag']
            ],
            'Azerbaijan' => [
                'Baku' => ['Baku', 'Sumqayit', 'Ganja'],
                'Ganja' => ['Ganja', 'Naftalan', 'Dashkasan'],
                'Sumqayit' => ['Sumqayit', 'Absheron', 'Khizi'],
                'Shirvan' => ['Shirvan', 'Salyan', 'Neftchala'],
                'Lankaran' => ['Lankaran', 'Astara', 'Masalli']
            ],
            'Georgia' => [
                'Tbilisi' => ['Tbilisi', 'Rustavi', 'Gori'],
                'Kutaisi' => ['Kutaisi', 'Zestaponi', 'Samtredia'],
                'Batumi' => ['Batumi', 'Kobuleti', 'Khelvachauri'],
                'Zugdidi' => ['Zugdidi', 'Tsalenjikha', 'Chkhorotsku'],
                'Poti' => ['Poti', 'Khobi', 'Senaki']
            ],
            'Armenia' => [
                'Yerevan' => ['Yerevan', 'Gyumri', 'Vanadzor'],
                'Gyumri' => ['Gyumri', 'Artik', 'Maralik'],
                'Vanadzor' => ['Vanadzor', 'Tashir', 'Stepanavan'],
                'Vagharshapat' => ['Vagharshapat', 'Metsamor', 'Armavir'],
                'Ijevan' => ['Ijevan', 'Dilijan', 'Berd']
            ]
        ];

        foreach ($countries as $countryName => $regions) {
            $country = Country::firstOrCreate([
                'name' => $countryName,
                'language' => $this->getLanguageForCountry($countryName)
            ]);

            foreach ($regions as $regionName => $cities) {
                $region = Region::firstOrCreate([
                    'name' => $regionName,
                    'country_id' => $country->id
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

    private function getLanguageForCountry(string $countryName): string
    {
        $languageMap = [
            'Russia' => 'ru',
            'Uzbekistan' => 'uz',
            'Kyrgyzstan' => 'ky',
            'Tajikistan' => 'tg',
            'Turkmenistan' => 'tk',
            'Azerbaijan' => 'az',
            'Georgia' => 'ka',
            'Armenia' => 'hy'
        ];

        return $languageMap[$countryName] ?? 'en';
    }
}
