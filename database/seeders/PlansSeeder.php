<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/** Tariffs per spec. Prices in so'm per month. */
class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'code' => 'start',
                'name' => 'Start',
                'audience' => 'Sinab ko\'rish uchun',
                'price' => 0,
                'included_requests' => 0,
                'extra_price' => null,
                'included_ai' => 3,
                'features' => [
                    'Ishlar va hujjatlarni yuritish',
                    'Huquqiy asosni tekshirish',
                    '3 ta AI tahlil',
                    'Audit jurnali',
                ],
            ],
            [
                'code' => 'advocate',
                'name' => 'Advokat',
                'audience' => 'Yakka tartibdagi advokatlar',
                'price' => 149_000,
                'included_requests' => 10,
                'extra_price' => 15_000,
                'included_ai' => 20,
                'features' => [
                    "Oyiga 10 ta idoraviy so'rov",
                    "Qo'shimcha so'rov — 15 000 so'm",
                    "Yuristlarni jamoaga qo'shish",
                    'AI tahlil va da\'vo arizasi loyihasi',
                    'PDF javoblar va audit jurnali',
                ],
            ],
            [
                'code' => 'firm',
                'name' => 'Firma',
                'audience' => 'Advokatlik firmalari va byurolari',
                'price' => 590_000,
                'included_requests' => 60,
                'extra_price' => 10_000,
                'included_ai' => 100,
                'features' => [
                    "Oyiga 60 ta idoraviy so'rov",
                    "Qo'shimcha so'rov — 10 000 so'm",
                    'Cheksiz jamoa a\'zolari',
                    'Kengaytirilgan AI tahlil',
                    'Ustuvor texnik yordam',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['code' => $plan['code']], $plan);
        }
    }
}
