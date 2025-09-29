<?php

namespace Database\Seeders;

use App\Models\FeeType;
use Illuminate\Database\Seeder;

class FeeTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $feeTypes = [
            [
                'code' => 'TUITION_FEE',
                'name' => 'Học phí chính quy',
                'default_amount' => 5000000,
                'is_active' => true,
            ],
            [
                'code' => 'BUS_FEE',
                'name' => 'Phí xe đưa đón',
                'default_amount' => 1200000,
                'is_active' => true,
            ],
            [
                'code' => 'LUNCH_FEE',
                'name' => 'Tiền ăn bán trú',
                'default_amount' => 1500000,
                'is_active' => true,
            ],
            [
                'code' => 'UNIFORM_FEE',
                'name' => 'Tiền đồng phục',
                'default_amount' => 800000,
                'is_active' => true,
            ],
            [
                'code' => 'TUITION_FEE_SEMESTER_1',
                'name' => 'Học phí chính quy (Học kỳ 1)',
                'default_amount' => 20000000,
                'is_active' => true,
            ],
            [
                'code' => 'TUITION_FEE_SEMESTER_2',
                'name' => 'Học phí chính quy (Học kỳ 2)',
                'default_amount' => 25000000,
                'is_active' => true,
            ],
            [
                'code' => 'EXTRACURRICULAR_FEE',
                'name' => 'Phí hoạt động ngoại khóa',
                'default_amount' => 300000,
                'is_active' => true,
            ],
            [
                'code' => 'REGISTRATION_FEE',
                'name' => 'Phí nhập học',
                'default_amount' => 2000000,
                'is_active' => true,
            ],
        ];

        foreach ($feeTypes as $feeType) {
            FeeType::firstOrCreate(
                ['code' => $feeType['code']], // điều kiện tìm
                $feeType                      // dữ liệu để insert nếu chưa có
            );
        }

        $this->command->info('✓ Fee types seeded successfully.');
    }
}
