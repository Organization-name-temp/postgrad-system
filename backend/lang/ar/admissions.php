<?php

// Arabic Admissions domain lines, selected when the request negotiates `ar`.
return [
    'slot_export' => ['columns' => ['id' => 'معرّف الموعد', 'scheduled_at' => 'موعد المقابلة', 'duration_minutes' => 'المدة بالدقائق', 'mode' => 'النمط', 'location' => 'المكان', 'interviewer_id' => 'معرّف المحاور', 'interviewer_name' => 'المحاور', 'is_open' => 'التوفر'], 'values' => ['open' => 'متاح', 'booked' => 'محجوز']],
    'interview_export_columns' => ['id' => 'معرّف المقابلة', 'application_id' => 'مرجع الطلب', 'name_en' => 'الاسم بالإنجليزية', 'name_ar' => 'الاسم بالعربية', 'national_id' => 'الرقم القومي / جواز السفر', 'degree' => 'الدرجة', 'status' => 'حالة التأكيد', 'scheduled_at' => 'موعد المقابلة', 'duration_minutes' => 'المدة بالدقائق', 'mode' => 'النمط', 'location' => 'المكان', 'interviewer_id' => 'معرّف المحاور', 'interviewer_name' => 'المحاور', 'evaluation_score' => 'درجة التقييم', 'evaluation_notes' => 'ملاحظات التقييم', 'completed_at' => 'وقت الإتمام'],
    'interview_states' => ['pending_confirmation' => 'بانتظار التأكيد', 'confirmed' => 'مؤكد'],
    'interview_modes' => ['in_person' => 'حضوري', 'virtual' => 'عن بُعد'],
    'cycle_export' => [
        'columns' => [
            'id' => 'معرّف دورة القبول',
            'intake_term' => 'فصل القبول',
            'year' => 'السنة',
            'name_en' => 'الاسم بالإنجليزية',
            'name_ar' => 'الاسم بالعربية',
            'opens_at' => 'بداية التقديم',
            'closes_at' => 'نهاية التقديم',
            'is_active' => 'حالة التفعيل',
            'is_open' => 'حالة نافذة التقديم',
        ],
        'values' => [
            'Fall' => 'الخريف',
            'Spring' => 'الربيع',
            'active' => 'مفعّلة',
            'inactive' => 'غير مفعّلة',
            'open' => 'مفتوحة',
            'closed' => 'مغلقة',
        ],
    ],

    'export_columns' => ['id' => 'مرجع الطلب', 'name_en' => 'الاسم بالإنجليزية', 'name_ar' => 'الاسم بالعربية', 'national_id' => 'الرقم القومي / جواز السفر', 'degree' => 'الدرجة', 'program' => 'البرنامج', 'status' => 'الحالة', 'age_days' => 'أيام المراجعة', 'date_of_birth' => 'تاريخ الميلاد', 'nationality' => 'الجنسية', 'phone' => 'الهاتف', 'address' => 'العنوان', 'finalized_at' => 'وقت الإرسال النهائي'],
    'statuses' => ['draft' => 'مسودة', 'submitted' => 'تم الإرسال', 'email_verified' => 'تم التحقق من البريد', 'under_sa_review' => 'قيد مراجعة شؤون الطلاب', 'sa_approved' => 'وافقت شؤون الطلاب', 'returned_for_revision' => 'أُعيد للتعديل', 'under_dean_review' => 'قيد مراجعة العميد', 'interview_scheduled' => 'تم تحديد المقابلة', 'interview_completed' => 'اكتملت المقابلة', 'accepted' => 'مقبول', 'conditionally_accepted' => 'مقبول بشروط', 'waitlisted' => 'قائمة الانتظار', 'rejected' => 'مرفوض'],
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
        'invalid_previous_ejust_program' => 'اختر برنامج ماجستير سابقًا صالحًا في الجامعة المصرية اليابانية.',
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
        'not_under_sa_review' => 'هذا الطلب ليس قيد مراجعة شؤون الطلاب.',
        'stale' => 'تم تغيير هذا الطلب. يرجى تحديثه قبل تسجيل القرار.',
        'illegal' => 'لا يمكن نقل هذا الطلب من :from إلى :to.',
    ],
];
