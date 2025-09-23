<?php

namespace Database\Seeders;

use App\Models\Subject;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SubjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subjects = [
            ['name' => 'Toán học', 'subject_code' => 'MATH'],
            ['name' => 'Vật lý', 'subject_code' => 'PHYS'],
            ['name' => 'Hóa học', 'subject_code' => 'CHEM'],
            ['name' => 'Ngữ văn', 'subject_code' => 'LIT'],
            ['name' => 'Lịch sử', 'subject_code' => 'HIST'],
            ['name' => 'Địa lý', 'subject_code' => 'GEO'],
            ['name' => 'Sinh học', 'subject_code' => 'BIO'],
            ['name' => 'Tiếng Anh', 'subject_code' => 'ENG'],
        ];
        foreach ($subjects as $subject) {
            // Dùng firstOrCreate để tránh tạo trùng lặp nếu chạy seeder nhiều lần
            Subject::firstOrCreate(['subject_code' => $subject['subject_code']], $subject);
        }
    }
}
