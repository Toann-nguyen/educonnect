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
                'code' => 'TUITION',
                'name' => 'Học phí chính quy',
                'default_amount' => 3000000,
                'description' => 'Học phí hàng tháng cho học sinh',
                'is_active' => true
            ],
            [
                'code' => 'BUS',
                'name' => 'Tiền xe đưa đón',
                'default_amount' => 500000,
                'description' => 'Phí xe bus đưa đón học sinh',
                'is_active' => true
            ],
            [
                'code' => 'MEAL',
                'name' => 'Tiền ăn trưa',
                'default_amount' => 800000,
                'description' => 'Chi phí ăn trưa tại trường',
                'is_active' => true
            ],
            [
                'code' => 'UNIFORM',
                'name' => 'Đồng phục',
                'default_amount' => 300000,
                'description' => 'Chi phí đồng phục học sinh',
                'is_active' => true
            ],
            [
                'code' => 'BOOK',
                'name' => 'Sách giáo khoa',
                'default_amount' => 400000,
                'description' => 'Chi phí sách giáo khoa và vở',
                'is_active' => true
            ],
            [
                'code' => 'EXTRA_CLASS',
                'name' => 'Lớp học thêm',
                'default_amount' => 1000000,
                'description' => 'Học phí lớp học thêm/bồi dưỡng',
                'is_active' => true
            ],
            [
                'code' => 'INSURANCE',
                'name' => 'Bảo hiểm học sinh',
                'default_amount' => 200000,
                'description' => 'Bảo hiểm tai nạn học đường',
                'is_active' => true
            ],
            [
                'code' => 'ACTIVITY',
                'name' => 'Hoạt động ngoại khóa',
                'default_amount' => 150000,
                'description' => 'Chi phí các hoạt động ngoại khóa, dã ngoại',
                'is_active' => true
            ],
            [
                'code' => 'EXAM',
                'name' => 'Lệ phí thi',
                'default_amount' => 100000,
                'description' => 'Chi phí in ấn đề thi, chấm bài',
                'is_active' => true
            ],
            [
                'code' => 'OTHER',
                'name' => 'Chi phí khác',
                'default_amount' => 0,
                'description' => 'Các khoản chi phí phát sinh khác',
                'is_active' => true
            ]
        ];

        foreach ($feeTypes as $feeType) {
            FeeType::firstOrCreate(
                ['code' => $feeType['code']],
                $feeType
            );
        }

        $this->command->info('Fee types seeded successfully.');
    }
}
