<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DisciplineTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding discipline types...');

        $disciplineTypes = [
            // Light violations (1-2 points)
            [
                'code' => 'LATE',
                'name' => 'Đi học trễ',
                'severity_level' => 'light',
                'default_penalty_points' => 1,
                'description' => 'Đến lớp sau giờ quy định không có lý do chính đáng',
                'is_active' => true,
            ],
            [
                'code' => 'UNIFORM_VIOLATION',
                'name' => 'Vi phạm trang phục',
                'severity_level' => 'light',
                'default_penalty_points' => 1,
                'description' => 'Không mặc đồng phục đúng quy định',
                'is_active' => true,
            ],
            [
                'code' => 'LITTERING',
                'name' => 'Xả rác bừa bãi',
                'severity_level' => 'light',
                'default_penalty_points' => 1,
                'description' => 'Vứt rác không đúng nơi quy định',
                'is_active' => true,
            ],
            [
                'code' => 'NOISE_DISRUPTION',
                'name' => 'Gây ồn ào',
                'severity_level' => 'light',
                'default_penalty_points' => 2,
                'description' => 'Gây ồn ào, làm mất trật tự trong giờ học hoặc giờ nghỉ',
                'is_active' => true,
            ],

            // Medium violations (3-5 points)
            [
                'code' => 'ABSENT_NO_EXCUSE',
                'name' => 'Vắng học không phép',
                'severity_level' => 'medium',
                'default_penalty_points' => 3,
                'description' => 'Nghỉ học không có giấy phép hoặc lý do chính đáng',
                'is_active' => true,
            ],
            [
                'code' => 'HOMEWORK_NOT_DONE',
                'name' => 'Không làm bài tập',
                'severity_level' => 'medium',
                'default_penalty_points' => 2,
                'description' => 'Thường xuyên không hoàn thành bài tập được giao',
                'is_active' => true,
            ],
            [
                'code' => 'DISRESPECT_TEACHER',
                'name' => 'Thiếu tôn trọng giáo viên',
                'severity_level' => 'medium',
                'default_penalty_points' => 5,
                'description' => 'Có thái độ không tôn trọng, cãi lại giáo viên',
                'is_active' => true,
            ],
            [
                'code' => 'PHONE_IN_CLASS',
                'name' => 'Sử dụng điện thoại trong giờ học',
                'severity_level' => 'medium',
                'default_penalty_points' => 3,
                'description' => 'Sử dụng điện thoại di động khi chưa được phép',
                'is_active' => true,
            ],
            [
                'code' => 'SKIP_CLASS',
                'name' => 'Trốn tiết học',
                'severity_level' => 'medium',
                'default_penalty_points' => 5,
                'description' => 'Bỏ học giữa chừng, không có mặt tại lớp khi đã đến trường',
                'is_active' => true,
            ],

            // Serious violations (6-10 points)
            [
                'code' => 'FIGHT',
                'name' => 'Đánh nhau',
                'severity_level' => 'serious',
                'default_penalty_points' => 10,
                'description' => 'Tham gia đánh nhau gây thương tích',
                'is_active' => true,
            ],
            [
                'code' => 'CHEAT',
                'name' => 'Gian lận trong thi cử',
                'severity_level' => 'serious',
                'default_penalty_points' => 8,
                'description' => 'Sao chép bài, mang tài liệu vào phòng thi',
                'is_active' => true,
            ],
            [
                'code' => 'VANDALISM',
                'name' => 'Phá hoại tài sản',
                'severity_level' => 'serious',
                'default_penalty_points' => 7,
                'description' => 'Làm hư hỏng cơ sở vật chất của trường',
                'is_active' => true,
            ],
            [
                'code' => 'BULLYING',
                'name' => 'Bắt nạt bạn học',
                'severity_level' => 'serious',
                'default_penalty_points' => 10,
                'description' => 'Hành vi bắt nạt, ức hiếp bạn học',
                'is_active' => true,
            ],
            [
                'code' => 'THEFT',
                'name' => 'Trộm cắp',
                'severity_level' => 'serious',
                'default_penalty_points' => 10,
                'description' => 'Lấy trộm tài sản của bạn học hoặc của trường',
                'is_active' => true,
            ],

            // Very serious violations (15+ points)
            [
                'code' => 'DRUGS',
                'name' => 'Sử dụng chất cấm',
                'severity_level' => 'very_serious',
                'default_penalty_points' => 20,
                'description' => 'Sử dụng, mua bán, hoặc tàng trữ chất cấm',
                'is_active' => true,
            ],
            [
                'code' => 'WEAPON',
                'name' => 'Mang vũ khí',
                'severity_level' => 'very_serious',
                'default_penalty_points' => 20,
                'description' => 'Mang vũ khí hoặc hung khí nguy hiểm vào trường',
                'is_active' => true,
            ],
            [
                'code' => 'VIOLENCE',
                'name' => 'Bạo lực nghiêm trọng',
                'severity_level' => 'very_serious',
                'default_penalty_points' => 20,
                'description' => 'Hành vi bạo lực gây thương tích nghiêm trọng',
                'is_active' => true,
            ],
            [
                'code' => 'GANG_ACTIVITY',
                'name' => 'Hoạt động băng nhóm',
                'severity_level' => 'very_serious',
                'default_penalty_points' => 15,
                'description' => 'Tham gia hoặc tổ chức băng nhóm gây rối',
                'is_active' => true,
            ],
        ];

        foreach ($disciplineTypes as $type) {
            DisciplineType::firstOrCreate(
                ['code' => $type['code']],
                $type
            );
        }

        $this->command->info('Discipline types seeded successfully.');
    }
}
