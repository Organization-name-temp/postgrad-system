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
        'not_gradable' => 'هذا التسجيل ليس سطرًا قابلًا للتقدير في كشف الدرجات هذا.',
    ],

    'sheet' => [
        'not_editable' => 'لم يعد كشف الدرجات هذا مفتوحًا لإدخال الدرجات.',
        'illegal_transition' => 'لا يمكن نقل كشف الدرجات من :from إلى :to.',
        'blank_lines_remain' => 'لا يزال في كشف الدرجات هذا :count سطر (أسطر) غير مُقدَّر.',
        'return_note_required' => 'يجب كتابة ملاحظة عند إعادة كشف الدرجات إلى أستاذ المقرر.',
        'not_your_department' => 'كشف الدرجات هذا لا يتبع قسمك.',
    ],

];
