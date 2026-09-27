<?php

return [
    'request_closed' => 'رفض النظام الطلب بسبب حالة إنهاء القيد رقم :case.',
    'evidence_changed' => 'تغيّرت أدلة إنهاء القيد؛ أعد تحميل الحالة.',
    'lifecycle_inactive' => 'لم تعد حالة دورة حياة الطالب نشطة.',
    'curriculum_missing' => 'لا يوجد منهج دراسي مرتبط بالطالب.',
    'student_required' => 'لا يوجد لك سجل طالب.',
    'student_missing' => 'الطالب غير موجود.',
    'curriculum_unavailable' => 'نسخة الخطة الدراسية المرتبطة غير متاحة.',
    'report' => [
        'headers' => [
            'student_id' => 'معرّف الطالب', 'university_id' => 'الرقم الجامعي',
            'name_en' => 'اسم الطالب بالإنجليزية', 'name_ar' => 'اسم الطالب بالعربية',
            'program_code' => 'رمز البرنامج', 'program' => 'البرنامج', 'degree_level' => 'الدرجة العلمية',
            'lifecycle' => 'حالة القيد', 'cgpa' => 'المعدل التراكمي', 'standing' => 'الوضع الأكاديمي',
            'explanation' => 'التفسير', 'unavailable' => 'البيانات غير المتاحة',
        ],
        'standings' => [
            'TerminationPending' => 'إنهاء القيد قيد النظر', 'NotYetAssessed' => 'لم يُقيّم بعد',
            'AcademicProbation' => 'المراقبة الأكاديمية', 'AcademicWarning' => 'تحذير أكاديمي',
            'GoodStanding' => 'وضع أكاديمي جيد',
        ],
        'explanations' => [
            'termination_pending' => 'توجد حالة إنهاء قيد مفتوحة تستدعي الإجراء.',
            'no_finalized_cgpa_grade' => 'لا توجد درجة نهائية محتسبة في المعدل التراكمي بعد.',
            'cgpa_below_minimum' => 'المعدل التراكمي أقل من الحد الأدنى.',
            'effective_grade_below_c_plus' => 'توجد درجة سارية أقل من C+.',
            'requirements_met' => 'متطلبات الوضع الأكاديمي الحالي مستوفاة.',
        ],
        'lifecycle' => ['Active' => 'نشط', 'Terminated' => 'تم إنهاء القيد'],
        'degrees' => ['MSc' => 'ماجستير', 'PhD' => 'دكتوراه'],
        'unavailable' => ['program' => 'البرنامج غير متاح', 'student_name' => 'اسم الطالب غير متاح'],
    ],
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
