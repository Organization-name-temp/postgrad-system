<?php

// Arabic Admissions domain lines, selected when the request negotiates `ar`.
return [
    // فاصل القوائم المبنية داخل رسالة (مثل تعداد المستندات الناقصة) — بالفاصلة العربية.
    'list_separator' => '، ',
    'registration' => [
        'closed' => 'باب القبول مغلق حاليًا.',
    ],
    'application' => [
        'not_editable' => 'لم يعد من الممكن تعديل هذا الطلب.',
        'already_submitted' => 'تم بالفعل إرسال الخطوة الأولى لهذا الطلب.',
        'invalid_identifier' => 'المعرّف لا يطابق نوع الهوية المحدد.',
        'gpa_above_scale' => 'لا يمكن أن يتجاوز المعدل الحد الأقصى للمقياس المختار وهو :ceiling.',
        'english_score_out_of_range' => 'يجب أن تكون درجة :test بين :min و :max.',
        'english_score_not_allowed' => 'الاختبار المحدد لا يتطلب درجة.',
    ],
    'documents' => [
        'locked' => 'لم يعد من الممكن تغيير مستندات هذا الطلب.',
        'too_many' => 'يمكنك رفع :max مستند كحد أقصى من هذا النوع.',
        // أسماء أنواع المستندات، تُستخدم لتعداد المستندات الناقصة عند الإرسال النهائي.
        'types' => [
            'graduation_certificate' => 'شهادة التخرج',
            'transcript' => 'كشف الدرجات',
            'national_id' => 'الهوية الوطنية / جواز السفر',
            'photo' => 'صورة شخصية',
            'english_certificate' => 'شهادة إتقان اللغة الإنجليزية',
            'msc_thesis' => 'رسالة الماجستير',
            'cv' => 'السيرة الذاتية',
            'recommendation_letter' => 'خطاب توصية',
            'additional_certificate' => 'شهادة إضافية',
        ],
    ],
    'program' => [
        'not_found' => 'تعذّر العثور على البرنامج المحدد.',
        'degree_mismatch' => 'البرنامج المحدد لا يطابق الدرجة التي تتقدم لها.',
        'not_qualified' => 'درجتك العلمية السابقة لا تؤهلك لهذا البرنامج.',
    ],
    'finalize' => [
        'not_ready' => 'هذا الطلب غير جاهز للإرسال النهائي.',
        'no_program' => 'اختر برنامجًا قبل الإرسال النهائي لطلبك.',
        'missing_documents' => 'ارفع المستندات المطلوبة التالية قبل الإرسال النهائي: :documents.',
    ],
    'resubmit' => [
        'not_returned' => 'لم يُعَد هذا الطلب للمراجعة.',
    ],
    'verification' => [
        'not_pending' => 'هذا الطلب لا ينتظر تأكيد البريد الإلكتروني.',
    ],
    'interview' => [
        'not_faculty' => 'الشخص المحدد ليس عضو هيئة تدريس.',
        'slot_booked' => 'هذا الموعد محجوز لمقابلة ولا يمكن تغييره.',
        'not_awaiting_scheduling' => 'هذا الطلب لا ينتظر جدولة مقابلة.',
        'already_scheduled' => 'تمت بالفعل جدولة مقابلة لهذا الطلب.',
        'not_pending' => 'هذه المقابلة لا تنتظر التأكيد.',
        'reschedule_exhausted' => 'لقد أعدت جدولة هذه المقابلة مرة واحدة بالفعل.',
        'already_completed' => 'تم إنهاء هذه المقابلة بالفعل.',
    ],
    'decision' => [
        'no_program' => 'لا يوجد برنامج لقبول المتقدم فيه لهذا الطلب.',
        'no_curriculum_version' => 'لا يحتوي البرنامج المحدد على إصدار خطة دراسية متاح للقبول.',
        'advisor_not_r03' => 'يجب أن يكون المرشد المحدد مرشدًا أكاديميًا.',
        'no_advisor' => 'لا يوجد مرشد أكاديمي متاح للتعيين.',
    ],
    'transition' => [
        'illegal' => 'لا يمكن نقل هذا الطلب من :from إلى :to.',
    ],
];
