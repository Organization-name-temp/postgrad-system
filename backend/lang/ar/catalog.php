<?php

// Arabic Catalog domain validation lines. Selected by the Accept-Language
// middleware; mirrors lang/en/catalog.php.
return [
    'reference_columns' => [
        'id' => 'المرجع',
        'code' => 'الرمز',
        'name_en' => 'الاسم بالإنجليزية',
        'name_ar' => 'الاسم بالعربية',
        'is_active' => 'حالة التفعيل',
        'department_id' => 'مرجع القسم',
        'faculty_id' => 'مرجع الكلية',
        'degree_level' => 'الدرجة',
        'prep_required_ch' => 'ساعات الإعداد المطلوبة',
        'core_required_ch' => 'الساعات الأساسية المطلوبة',
        'elective_required_ch' => 'الساعات الاختيارية المطلوبة',
        'total_required_ch' => 'إجمالي الساعات المطلوبة',
    ],
    'export_columns' => [
        'id' => 'مرجع المقرر',
        'department_id' => 'مرجع القسم',
        'code' => 'رمز المقرر',
        'name_en' => 'الاسم بالإنجليزية',
        'name_ar' => 'الاسم بالعربية',
        'level' => 'المستوى',
        'research_type' => 'نوع البحث',
        'lecture_ch' => 'ساعات المحاضرات',
        'tutorial_ch' => 'ساعات التمارين',
        'lab_ch' => 'ساعات المعمل',
        'total_ch' => 'إجمالي الساعات',
        'is_active' => 'حالة التفعيل',
    ],
    'export_values' => [
        'MSc' => 'ماجستير العلوم',
        'PhD' => 'دكتوراه الفلسفة',
        'PBL' => 'التعلّم القائم على المشكلات',
        'Seminar' => 'حلقة بحث',
        'active' => 'نشط',
        'inactive' => 'غير نشط',
    ],
    'credit_hours_unbalanced' => 'يجب أن يساوي مجموع ساعات المحاضرات والتمارين والمعمل الإجمالي.',
    'curriculum_duplicate' => 'هذا المقرر مُدرج بالفعل ضمن هذه الفئة في هذا البرنامج.',
    'preparatory_role_duplicate' => 'دور المتطلب التمهيدي هذا مُعيّن بالفعل في هذا البرنامج.',
    'preparatory_role_required' => 'يتطلب كل مدخل تمهيدي في الخطة الدراسية دور متطلب تمهيدي.',
    'key_skills_must_be_departmental' => 'يجب تعيين المهارات الأساسية لمقرر يملكه قسم هذا البرنامج.',
    'preparatory_role_only_for_preparatory' => 'لا يمكن تعيين دور متطلب تمهيدي إلا لمدخلات الخطة الدراسية التمهيدية.',
];
