<?php

namespace App\Enums;

enum PermissionEnum: string
{
    case MANAGE_USERS = 'manage_users';
    case VIEW_USERS = 'view_users';
    case CREATE_USERS = 'create_users';
    case EDIT_USERS = 'edit_users';
    case DELETE_USERS = 'delete_users';

    case MANAGE_ROLES = 'manage_roles';
    case MANAGE_PERMISSIONS = 'manage_permissions';

    case MANAGE_SCHOOL_STRUCTURE = 'manage_school_structure';
    case MANAGE_CLASSES = 'manage_classes';
    case MANAGE_ACADEMIC_YEARS = 'manage_academic_years';

    case MANAGE_SCHEDULES = 'manage_schedules';
    case VIEW_SCHEDULES = 'view_schedules';
    case MANAGE_GRADES = 'manage_grades';
    case VIEW_GRADES = 'view_grades';
    case MANAGE_ATTENDANCE = 'manage_attendance';
    case VIEW_ATTENDANCE = 'view_attendance';

    case MANAGE_FINANCES = 'manage_finances';
    case VIEW_INVOICES = 'view_invoices';
    case MANAGE_INVOICES = 'manage_invoices';
    case MANAGE_PAYMENTS = 'manage_payments';

    case MANAGE_LIBRARY = 'manage_library';
    case VIEW_LIBRARY = 'view_library';
    case BORROW_BOOKS = 'borrow_books';

    case RECORD_DISCIPLINE = 'record_discipline';
    case MANAGE_DISCIPLINE = 'manage_discipline';
    case VIEW_DISCIPLINE = 'view_discipline';

    case MANAGE_EVENTS = 'manage_events';
    case VIEW_EVENTS = 'view_events';
    case REGISTER_EVENTS = 'register_events';

    public function label(): string
    {
        return match ($this) {
            self::MANAGE_USERS => 'Quản lý người dùng',
            self::VIEW_USERS => 'Xem danh sách người dùng',
            self::CREATE_USERS => 'Tạo người dùng',
            self::EDIT_USERS => 'Chỉnh sửa người dùng',
            self::DELETE_USERS => 'Xóa người dùng',
            self::MANAGE_ROLES => 'Quản lý vai trò',
            self::MANAGE_PERMISSIONS => 'Quản lý quyền hạn',
            self::MANAGE_SCHOOL_STRUCTURE => 'Quản lý cấu trúc trường',
            self::MANAGE_CLASSES => 'Quản lý lớp học',
            self::MANAGE_ACADEMIC_YEARS => 'Quản lý năm học',
            self::MANAGE_SCHEDULES => 'Quản lý thời khóa biểu',
            self::VIEW_SCHEDULES => 'Xem thời khóa biểu',
            self::MANAGE_GRADES => 'Quản lý điểm số',
            self::VIEW_GRADES => 'Xem điểm số',
            self::MANAGE_ATTENDANCE => 'Quản lý điểm danh',
            self::VIEW_ATTENDANCE => 'Xem điểm danh',
            self::MANAGE_FINANCES => 'Quản lý tài chính',
            self::VIEW_INVOICES => 'Xem hóa đơn',
            self::MANAGE_INVOICES => 'Quản lý hóa đơn',
            self::MANAGE_PAYMENTS => 'Quản lý thanh toán',
            self::MANAGE_LIBRARY => 'Quản lý thư viện',
            self::VIEW_LIBRARY => 'Xem thư viện',
            self::BORROW_BOOKS => 'Mượn sách',
            self::RECORD_DISCIPLINE => 'Ghi nhận kỷ luật',
            self::MANAGE_DISCIPLINE => 'Quản lý kỷ luật',
            self::VIEW_DISCIPLINE => 'Xem kỷ luật',
            self::MANAGE_EVENTS => 'Quản lý sự kiện',
            self::VIEW_EVENTS => 'Xem sự kiện',
            self::REGISTER_EVENTS => 'Đăng ký sự kiện',
        };
    }

    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}


