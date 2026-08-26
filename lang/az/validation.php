<?php

/**
 * Peerstack Exam System
 *
 * @author    Damir Pashayev <pashayevdamir@gmail.com>
 * @copyright 2026 Damir Pashayev. All rights reserved.
 *
 * @link      https://github.com/pasayevdemir
 */

/**
 * Framework validation messages, in the language the app is actually used in.
 *
 * Published in full rather than for the handful of rules the app reaches today:
 * a rule added later would otherwise fall through to English on a page where
 * everything else is Azerbaijani, and nothing would flag it.
 *
 * The 'attributes' block at the bottom is what turns ":attribute mütləqdir" into
 * "Ad mütləqdir" - without it a student is told off about "first_name".
 */
return [
    'accepted' => ':attribute qəbul edilməlidir.',
    'accepted_if' => ':other :value olduqda :attribute qəbul edilməlidir.',
    'active_url' => ':attribute düzgün URL olmalıdır.',
    'after' => ':attribute :date tarixindən sonra olmalıdır.',
    'after_or_equal' => ':attribute :date tarixindən sonra və ya ona bərabər olmalıdır.',
    'alpha' => ':attribute yalnız hərflərdən ibarət olmalıdır.',
    'alpha_dash' => ':attribute yalnız hərf, rəqəm, tire və alt xəttdən ibarət olmalıdır.',
    'alpha_num' => ':attribute yalnız hərf və rəqəmlərdən ibarət olmalıdır.',
    'any_of' => ':attribute düzgün deyil.',
    'array' => ':attribute massiv olmalıdır.',
    'ascii' => ':attribute yalnız birbaytlıq simvol və işarələrdən ibarət olmalıdır.',
    'before' => ':attribute :date tarixindən əvvəl olmalıdır.',
    'before_or_equal' => ':attribute :date tarixindən əvvəl və ya ona bərabər olmalıdır.',
    'between' => [
        'array' => ':attribute :min - :max arasında element saxlamalıdır.',
        'file' => ':attribute :min - :max kilobayt arasında olmalıdır.',
        'numeric' => ':attribute :min - :max arasında olmalıdır.',
        'string' => ':attribute :min - :max simvol arasında olmalıdır.',
    ],
    'boolean' => ':attribute yalnız doğru və ya yanlış ola bilər.',
    'can' => ':attribute icazəsiz dəyər saxlayır.',
    'confirmed' => ':attribute təsdiqi uyğun gəlmir.',
    'contains' => ':attribute tələb olunan dəyəri saxlamır.',
    'current_password' => 'Parol yanlışdır.',
    'date' => ':attribute düzgün tarix olmalıdır.',
    'date_equals' => ':attribute :date tarixinə bərabər olmalıdır.',
    'date_format' => ':attribute :format formatına uyğun olmalıdır.',
    'decimal' => ':attribute :decimal onluq rəqəm saxlamalıdır.',
    'declined' => ':attribute rədd edilməlidir.',
    'declined_if' => ':other :value olduqda :attribute rədd edilməlidir.',
    'different' => ':attribute və :other fərqli olmalıdır.',
    'digits' => ':attribute :digits rəqəmdən ibarət olmalıdır.',
    'digits_between' => ':attribute :min - :max rəqəm arasında olmalıdır.',
    'dimensions' => ':attribute şəkil ölçüləri yanlışdır.',
    'distinct' => ':attribute təkrarlanan dəyər saxlayır.',
    'doesnt_contain' => ':attribute aşağıdakılardan heç birini saxlamamalıdır: :values.',
    'doesnt_end_with' => ':attribute aşağıdakılardan biri ilə bitməməlidir: :values.',
    'doesnt_start_with' => ':attribute aşağıdakılardan biri ilə başlamamalıdır: :values.',
    'email' => ':attribute düzgün e-poçt ünvanı olmalıdır.',
    'encoding' => ':attribute :encoding kodlaşdırmasında olmalıdır.',
    'ends_with' => ':attribute aşağıdakılardan biri ilə bitməlidir: :values.',
    'enum' => 'Seçilmiş :attribute yanlışdır.',
    'exists' => 'Seçilmiş :attribute yanlışdır.',
    'extensions' => ':attribute aşağıdakı uzantılardan birinə malik olmalıdır: :values.',
    'file' => ':attribute fayl olmalıdır.',
    'filled' => ':attribute doldurulmalıdır.',
    'gt' => [
        'array' => ':attribute :value elementdən çox saxlamalıdır.',
        'file' => ':attribute :value kilobaytdan böyük olmalıdır.',
        'numeric' => ':attribute :value-dən böyük olmalıdır.',
        'string' => ':attribute :value simvoldan uzun olmalıdır.',
    ],
    'gte' => [
        'array' => ':attribute :value və ya daha çox element saxlamalıdır.',
        'file' => ':attribute :value kilobayt və ya daha böyük olmalıdır.',
        'numeric' => ':attribute :value-dən böyük və ya ona bərabər olmalıdır.',
        'string' => ':attribute :value simvol və ya daha uzun olmalıdır.',
    ],
    'hex_color' => ':attribute düzgün onaltılıq rəng kodu olmalıdır.',
    'image' => ':attribute şəkil olmalıdır.',
    'in' => 'Seçilmiş :attribute yanlışdır.',
    'in_array' => ':attribute :other daxilində mövcud olmalıdır.',
    'in_array_keys' => ':attribute aşağıdakı açarlardan ən azı birini saxlamalıdır: :values.',
    'integer' => ':attribute tam ədəd olmalıdır.',
    'ip' => ':attribute düzgün IP ünvanı olmalıdır.',
    'ipv4' => ':attribute düzgün IPv4 ünvanı olmalıdır.',
    'ipv6' => ':attribute düzgün IPv6 ünvanı olmalıdır.',
    'json' => ':attribute düzgün JSON mətni olmalıdır.',
    'list' => ':attribute siyahı olmalıdır.',
    'lowercase' => ':attribute kiçik hərflərlə olmalıdır.',
    'lt' => [
        'array' => ':attribute :value elementdən az saxlamalıdır.',
        'file' => ':attribute :value kilobaytdan kiçik olmalıdır.',
        'numeric' => ':attribute :value-dən kiçik olmalıdır.',
        'string' => ':attribute :value simvoldan qısa olmalıdır.',
    ],
    'lte' => [
        'array' => ':attribute :value elementdən çox saxlamamalıdır.',
        'file' => ':attribute :value kilobayt və ya daha kiçik olmalıdır.',
        'numeric' => ':attribute :value-dən kiçik və ya ona bərabər olmalıdır.',
        'string' => ':attribute :value simvol və ya daha qısa olmalıdır.',
    ],
    'mac_address' => ':attribute düzgün MAC ünvanı olmalıdır.',
    'max' => [
        'array' => ':attribute :max elementdən çox saxlamamalıdır.',
        'file' => ':attribute :max kilobaytdan böyük olmamalıdır.',
        'numeric' => ':attribute :max-dan böyük olmamalıdır.',
        'string' => ':attribute :max simvoldan uzun olmamalıdır.',
    ],
    'max_digits' => ':attribute :max rəqəmdən çox olmamalıdır.',
    'mimes' => ':attribute aşağıdakı fayl tiplərindən biri olmalıdır: :values.',
    'mimetypes' => ':attribute aşağıdakı fayl tiplərindən biri olmalıdır: :values.',
    'min' => [
        'array' => ':attribute ən azı :min element saxlamalıdır.',
        'file' => ':attribute ən azı :min kilobayt olmalıdır.',
        'numeric' => ':attribute ən azı :min olmalıdır.',
        'string' => ':attribute ən azı :min simvol olmalıdır.',
    ],
    'min_digits' => ':attribute ən azı :min rəqəmdən ibarət olmalıdır.',
    'missing' => ':attribute mövcud olmamalıdır.',
    'missing_if' => ':other :value olduqda :attribute mövcud olmamalıdır.',
    'missing_unless' => ':other :value olmadıqda :attribute mövcud olmamalıdır.',
    'missing_with' => ':values mövcud olduqda :attribute mövcud olmamalıdır.',
    'missing_with_all' => ':values mövcud olduqda :attribute mövcud olmamalıdır.',
    'multiple_of' => ':attribute :value-nin misli olmalıdır.',
    'not_in' => 'Seçilmiş :attribute yanlışdır.',
    'not_regex' => ':attribute formatı yanlışdır.',
    'numeric' => ':attribute rəqəm olmalıdır.',
    'password' => [
        'letters' => ':attribute ən azı bir hərf saxlamalıdır.',
        'mixed' => ':attribute ən azı bir böyük və bir kiçik hərf saxlamalıdır.',
        'numbers' => ':attribute ən azı bir rəqəm saxlamalıdır.',
        'symbols' => ':attribute ən azı bir simvol saxlamalıdır.',
        'uncompromised' => 'Daxil edilən :attribute məlumat sızmasında aşkarlanıb. Zəhmət olmasa başqa :attribute seçin.',
    ],
    'present' => ':attribute mövcud olmalıdır.',
    'present_if' => ':other :value olduqda :attribute mövcud olmalıdır.',
    'present_unless' => ':other :value olmadıqda :attribute mövcud olmalıdır.',
    'present_with' => ':values mövcud olduqda :attribute da mövcud olmalıdır.',
    'present_with_all' => ':values mövcud olduqda :attribute da mövcud olmalıdır.',
    'prohibited' => ':attribute qadağandır.',
    'prohibited_if' => ':other :value olduqda :attribute qadağandır.',
    'prohibited_if_accepted' => ':other qəbul edildikdə :attribute qadağandır.',
    'prohibited_if_declined' => ':other rədd edildikdə :attribute qadağandır.',
    'prohibited_unless' => ':other :values daxilində olmadıqda :attribute qadağandır.',
    'prohibits' => ':attribute :other-in mövcud olmasını qadağan edir.',
    'regex' => ':attribute formatı yanlışdır.',
    'required' => ':attribute mütləqdir.',
    'required_array_keys' => ':attribute aşağıdakı açarları saxlamalıdır: :values.',
    'required_if' => ':other :value olduqda :attribute mütləqdir.',
    'required_if_accepted' => ':other qəbul edildikdə :attribute mütləqdir.',
    'required_if_declined' => ':other rədd edildikdə :attribute mütləqdir.',
    'required_unless' => ':other :values daxilində olmadıqda :attribute mütləqdir.',
    'required_with' => ':values mövcud olduqda :attribute mütləqdir.',
    'required_with_all' => ':values mövcud olduqda :attribute mütləqdir.',
    'required_without' => ':values mövcud olmadıqda :attribute mütləqdir.',
    'required_without_all' => 'Heç bir :values mövcud olmadıqda :attribute mütləqdir.',
    'same' => ':attribute və :other uyğun gəlməlidir.',
    'size' => [
        'array' => ':attribute :size element saxlamalıdır.',
        'file' => ':attribute :size kilobayt olmalıdır.',
        'numeric' => ':attribute :size olmalıdır.',
        'string' => ':attribute :size simvol olmalıdır.',
    ],
    'starts_with' => ':attribute aşağıdakılardan biri ilə başlamalıdır: :values.',
    'string' => ':attribute mətn olmalıdır.',
    'timezone' => ':attribute düzgün saat qurşağı olmalıdır.',
    'unique' => 'Bu :attribute artıq istifadə olunub.',
    'uploaded' => ':attribute yüklənə bilmədi.',
    'uppercase' => ':attribute böyük hərflərlə olmalıdır.',
    'url' => ':attribute düzgün URL olmalıdır.',
    'ulid' => ':attribute düzgün ULID olmalıdır.',
    'uuid' => ':attribute düzgün UUID olmalıdır.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Every field name a student or an admin can actually be told off about.
    | Without these, ':attribute mütləqdir' renders the raw column name.
    |
    */

    'attributes' => [
        'first_name' => 'Ad',
        'last_name' => 'Soyad',
        'name' => 'Ad',
        'email' => 'E-poçt ünvanı',
        'password' => 'Parol',
        'password_confirmation' => 'Parol təsdiqi',
        'current_password' => 'Hazırkı parol',
        'phone_number' => 'Telefon nömrəsi',
        'fin_code' => 'FIN kod',
        'entry_password' => 'İmtahan parolu',
        'exam_id' => 'İmtahan ID',
        'exam_name' => 'İmtahanın adı',
        'description' => 'Təsvir',
        'time_limit_minutes' => 'Vaxt limiti',
        'question_text' => 'Sualın mətni',
        'question_type' => 'Sualın tipi',
        'difficulty' => 'Çətinlik',
        'answers' => 'Cavablar',
        'correct_answers' => 'Düzgün cavablar',
        'manual_score' => 'Bal',
        'admin_feedback' => 'Rəy',
        'file' => 'Fayl',
        'file_uploads' => 'Yüklənən fayllar',
        'quota_easy' => 'Asan sual sayı',
        'quota_medium' => 'Orta sual sayı',
        'quota_hard' => 'Çətin sual sayı',
        'question_bank_id' => 'Sual bankı',
        'search' => 'Axtarış',
    ],
];
