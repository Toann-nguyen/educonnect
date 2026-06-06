<?php

return [
    'roles' => [
        'admin' => [
            'label' => 'Admin',
            'description' => 'Quản trị viên hệ thống - có quyền truy cập toàn bộ',
            'all_permissions' => true,
        ],
        'principal' => [
            'label' => 'Hiệu trưởng',
            'description' => 'Quản lý toàn bộ hoạt động của trường',
            'permissions' => [
                'manage_school_structure',
                'manage_classes',
                'manage_academic_years',
                'manage_users',
                'view_users',
                'view_schedules',
                'manage_schedules',
                'view_grades',
                'view_attendance',
                'view_invoices',
                'view_events',
                'manage_events',
                'view_discipline',
            ],
        ],
        'teacher' => [
            'label' => 'Giáo viên',
            'description' => 'Giảng dạy và quản lý lớp học',
            'permissions' => [
                'view_schedules',
                'manage_schedules',
                'manage_grades',
                'view_grades',
                'manage_attendance',
                'view_attendance',
                'record_discipline',
                'view_discipline',
                'manage_events',
                'view_events',
            ],
        ],
        'student' => [
            'label' => 'Học sinh',
            'description' => 'Học sinh của trường',
            'permissions' => [
                'view_schedules',
                'view_grades',
                'view_attendance',
                'view_library',
                'borrow_books',
                'view_events',
                'register_events',
            ],
        ],
        'parent' => [
            'label' => 'Phụ huynh',
            'description' => 'Phụ huynh học sinh',
            'permissions' => [
                'view_schedules',
                'view_grades',
                'view_attendance',
                'view_invoices',
                'view_events',
            ],
        ],
        'accountant' => [
            'label' => 'Kế toán',
            'description' => 'Quản lý tài chính nhà trường',
            'permissions' => [
                'manage_finances',
                'view_invoices',
                'manage_invoices',
                'manage_payments',
            ],
        ],
        'librarian' => [
            'label' => 'Thủ thư',
            'description' => 'Quản lý thư viện',
            'permissions' => [
                'manage_library',
                'view_library',
                'borrow_books',
            ],
        ],
        'red_scarf' => [
            'label' => 'Đoàn viên',
            'description' => 'Đoàn viên ghi nhận kỷ luật',
            'permissions' => [
                'record_discipline',
                'view_discipline',
                'view_events',
                'register_events',
            ],
        ],
    ],

    'role_hierarchy' => [
        'admin' => [],
        'principal' => ['teacher'],
        'teacher' => ['student'],
        'student' => [],
        'parent' => [],
        'accountant' => [],
        'librarian' => [],
        'red_scarf' => ['student'],
    ],

    'default_role' => 'student',

    'super_admin_role' => 'admin',
];


