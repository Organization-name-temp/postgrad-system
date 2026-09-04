<?php

// Arabic Catalog domain validation lines. Selected by the Accept-Language
// middleware; mirrors lang/en/catalog.php.
return [
    'credit_hours_unbalanced' => 'يجب أن يساوي مجموع ساعات المحاضرات والتمارين والمعمل الإجمالي.',
    'curriculum_duplicate' => 'هذا المقرر مُدرج بالفعل ضمن هذه الفئة في هذا البرنامج.',
    'preparatory_role_duplicate' => 'دور المتطلب التمهيدي هذا مُعيّن بالفعل في هذا البرنامج.',
    'preparatory_role_required' => 'يتطلب كل مدخل تمهيدي في الخطة الدراسية دور متطلب تمهيدي.',
    'key_skills_must_be_departmental' => 'يجب تعيين المهارات الأساسية لمقرر يملكه قسم هذا البرنامج.',
    'preparatory_role_only_for_preparatory' => 'لا يمكن تعيين دور متطلب تمهيدي إلا لمدخلات الخطة الدراسية التمهيدية.',
];
