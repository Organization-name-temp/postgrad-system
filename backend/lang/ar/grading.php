<?php

// Arabic Grading domain messages. Selected by the Accept-Language middleware;
// mirrors lang/en/grading.php.
return [

    'mark' => [
        'out_of_range' => 'يجب أن تكون الدرجة نسبة مئوية بين 0 و100.',
        'too_precise' => 'يُسمح بمنزلتين عشريتين على الأكثر في الدرجة.',
    ],

    'offering' => [
        'missing' => 'لم يعد هذا الطرح الدراسي موجودًا.',
        'not_yours' => 'أنت غير مُكلَّف بتدريس هذا الطرح الدراسي.',
    ],

    'line' => [
        'not_found' => 'هذا التسجيل ليس سطرًا في كشف الدرجات هذا.',
    ],

    'student' => [
        'missing' => 'ليس لديك سجل طالب.',
    ],

    'sheet' => [
        'not_editable' => 'لم يعد كشف الدرجات هذا مفتوحًا لإدخال الدرجات.',
        'illegal_transition' => 'لا يمكن نقل كشف الدرجات من :from إلى :to.',
        'blank_lines_remain' => 'لا يزال في كشف الدرجات هذا :count سطر (أسطر) غير مُقدَّر.',
        'return_note_required' => 'يجب كتابة ملاحظة عند إعادة كشف الدرجات إلى أستاذ المقرر.',
        'not_your_department' => 'كشف الدرجات هذا لا يتبع قسمك.',
        'correction_requires_finalized' => 'لا يمكن بدء جولة تصحيح إلا من كشف درجات نهائي.',
        'correction_lines_invalid' => 'لا يمكن أن تضم جولة التصحيح إلا سطورًا من كشف الدرجات النهائي هذا.',
    ],

    'incomplete' => [
        'completion_too_low' => 'يتطلب "غير مكتمل" إنجاز :threshold% على الأقل من متطلبات المقرر.',
        'reason_required' => 'يجب كتابة سبب عند تسجيل درجة "غير مكتمل".',
        'only_on_initial_sheet' => 'لا يمكن تسجيل "غير مكتمل" إلا في كشف الدرجات الأولي للطرح الدراسي.',
        'not_yours' => 'أنت غير مُكلَّف بتدريس هذا الطرح الدراسي.',
        'not_outstanding' => 'تمت بالفعل تسوية حالة "غير مكتمل" هذه أو تحويلها.',
    ],

];
