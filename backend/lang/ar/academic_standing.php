<?php

return [
    'request_closed' => 'رفض النظام الطلب بسبب حالة إنهاء القيد رقم :case.',
    'evidence_changed' => 'تغيّرت أدلة إنهاء القيد؛ أعد تحميل الحالة.',
    'lifecycle_inactive' => 'لم تعد حالة دورة حياة الطالب نشطة.',
    'curriculum_missing' => 'لا يوجد منهج دراسي مرتبط بالطالب.',
    'student_required' => 'لا يوجد لك سجل طالب.',
    'student_missing' => 'الطالب غير موجود.',
    'curriculum_unavailable' => 'نسخة الخطة الدراسية المرتبطة غير متاحة.',
    'exports' => [
        'headers' => [
            'case_id' => 'رقم الحالة',
            'student_id' => 'رقم الطالب',
            'state' => 'الحالة',
            'advisor_id' => 'رقم المرشد الأكاديمي',
            'advisor_recommendation' => 'توصية المرشد الأكاديمي',
            'department_head_id' => 'رقم رئيس القسم',
            'council_id' => 'رقم عضو المجلس',
            'closed_reason' => 'سبب الإغلاق',
            'closed_at' => 'وقت الإغلاق',
        ],
        'states' => [
            'AwaitingAdvisor' => 'بانتظار المرشد الأكاديمي',
            'AwaitingDepartmentHead' => 'بانتظار رئيس القسم',
            'AwaitingCouncil' => 'بانتظار المجلس',
            'Dismissed' => 'مرفوضة',
            'Terminated' => 'تم إنهاء القيد',
            'TriggerCleared' => 'زوال سبب الإجراء',
            'EvidenceChanged' => 'تغيّرت الأدلة',
        ],
        'recommendations' => ['continue' => 'استمرار', 'dismiss' => 'رفض'],
    ],
];
