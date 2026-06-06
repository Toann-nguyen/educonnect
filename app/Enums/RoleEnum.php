<?php

namespace App\Enums;

enum RoleEnum: string
{
    case ADMIN = 'admin';
    case PRINCIPAL = 'principal';
    case TEACHER = 'teacher';
    case STUDENT = 'student';
    case PARENT = 'parent';
    case ACCOUNTANT = 'accountant';
    case LIBRARIAN = 'librarian';
    case RED_SCARF = 'red_scarf';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Quản trị viên',
            self::PRINCIPAL => 'Hiệu trưởng',
            self::TEACHER => 'Giáo viên',
            self::STUDENT => 'Học sinh',
            self::PARENT => 'Phụ huynh',
            self::ACCOUNTANT => 'Kế toán',
            self::LIBRARIAN => 'Thủ thư',
            self::RED_SCARF => 'Đoàn viên',
        };
    }

    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}


