<?php

return [
    'plugin' => [
        'name' => 'صفحات',
        'description' => 'Pages & menus features.',
    ],
    'page' => [
        'menu_label' => 'صفحات',
        'delete_confirmation' => 'آیا از حذف صفحات انتخاب شده اطمینان دارید؟ اگر صفحات دارای زیر صفحه باشند آنها نیز حذف خواهند شد.',
        'no_records' => 'صفحه ای یافت نشد',
        'delete_confirm_single' => 'آیا از حذف این صفحه اطمینان دارید؟ اگر این صفحه دارای زیر صفحه باشد آنها نیز حذف خواهند شد.',
        'new' => 'صفحه ی جدید',
        'add_subpage' => 'افزودن زیر صفحه',
        'invalid_url' => 'قالب آدرس نا معتبر است. آدرس باید با اسلش شروع شود و میتواند شامل حروف لاتین اعداد و این کاراکتر ها باشد: _-/',
        'url_not_unique' => 'این آدرس توسط صفحه ی دیگری استفاده شده است.',
        'layout' => 'طرح بندی',
        'layouts_not_found' => 'هیچ طرحبدی ای وجود ندارد',
        'saved' => 'صفحه با موفقیت ذخیره شد.',
        'tab' => 'صفحات',
        'manage_pages' => 'Manage the static pages',
        'manage_menus' => 'Manage the static menus',
        'access_snippets' => 'Manage snippets',
        'manage_content' => 'Manage content'
    ],
    'menu' => [
        'menu_label' => 'فهرست ها',
        'delete_confirmation' => 'آیا از حذف فهرست انتخاب شده اطمینان دارید؟',
        'no_records' => 'فهرستی ای یافت نشد',
        'new' => 'فهرست جدید',
        'new_name' => 'فهرست جدید',
        'new_code' => 'new-menu',
        'delete_confirm_single' => 'آیا از حذف این فهرست اطمینان دارید؟',
        'saved' => 'فهرست با موفقیت ذخیره شد.',
        'name' => 'نام',
        'code' => 'کد',
        'items' => 'موارد فهرست',
        'add_subitem' => 'افزودن زیر فهرست',
        'no_records' => 'موردی یافت نشد',
        'code_required' => 'وارد کردن کد اجباریست',
        'invalid_code' => 'قالب کد نا معتبر است. کد میتواند شامل اعداد، حروف لاتین و این کاراکتر ها باشد: _-'
    ],
    'menuitem' => [
        'title' => 'عنوان',
        'editor_title' => 'ویرایش فهرست',
        'type' => 'نوع',
        'allow_nested_items' => 'استفاده از موارد تو در تو',
        'allow_nested_items_comment' => 'موارد تو در تو به صورت خودکار توسط صفحات ایتاتیک و برخی از دیگر موارد ایجاد می شوند',
        'url' => 'آدرس',
        'reference' => 'مرجع',
        'title_required' => 'وارد کردن عنوان اجباریست',
        'unknown_type' => 'نوع نامشخص فهرست',
        'unnamed' => 'فهرست بدون نام',
        'add_item' => 'افزودن فهرست',
        'new_item' => 'مورد جدید برای فهرست',
        'replace' => 'جایگرینی این مورد با زیر مورد های ایجاد شده',
        'replace_comment' => 'اگر میخواهید زیر فهرست های ایجاد شده هم سطح با این مورد قرار بگیرند این گزینه را فعال نمایید. خود فهرست بصورت خودکار مخفی خواهد شد.',
        'cms_page' => 'صفحه ی مدیریت محتوی',
        'cms_page_comment' => 'صفحه ای را که میخواهید بهنگام انتخاب این فهرست باز شود را انتخاب نمایید.',
        'reference_required' => 'وارد کردن مرجه موارد فهرست الزامیست.',
        'url_required' => 'وارد کردن آدرس الزامیست',
        'cms_page_required' => 'لطفا یک صفحه را انتخاب کنید',
        'code' => 'کد',
        'code_comment' => 'اگر میخواهید از طریق کد ها به ابن مورد از فهرست دسترسی پیدا کنید کد آن را وارد نمایید.'
    ],
    'content' => [
        'menu_label' => 'محتوی',
        'cant_save_to_dir' => 'مجوز ذخیره ی داده ها در پوشهی صفحات استاتسک وجود ندارد.'
    ],
    'sidebar' => [
        'add' => 'افزودن',
        'search' => 'جستجو...'
    ],
    'object' => [
        'invalid_type' => 'نوع شیء نا مشخص ایت',
        'not_found' => 'شیء درخواستی یافت نشد.'
    ],
    'editor' => [
        'title' => 'عنوان',
        'new_title' => 'عنوان صفحه ی جدید',
        'content' => 'محتوی',
        'url' => 'آدرس',
        'filename' => 'نام فایل',
        'layout' => 'طرح بندی',
        'description' => 'توضیحات',
        'preview' => 'پیش نمایش',
        'enter_fullscreen' => 'حالت تمام صفحه',
        'exit_fullscreen' => 'خروج از حالت تمام صفحه',
        'hidden' => 'مخفی',
        'hidden_comment' => 'صفحات مخفی توسط کاربران وارد شده به سایت قابل دسترس می باشند.',
        'navigation_hidden' => 'مخفی کردن در فهرست',
        'navigation_hidden_comment' => 'اگر میخواهید صفحه مورد نظر در فهرست هایی که خودکار ایجاد می شوند و یا راهنمای سایت دیده نشوند این گزینه را انتخاب نمایید.'
    ],
    'snippet' => [
        'partialtab' => 'Snippet',
        'code' => 'Snippet code',
        'code_comment' => 'Enter a code to make this partial available as a snippet in the Static Pages plugin.',
        'name' => 'Name',
        'name_comment' => 'The name is displayed in the snippet list in the Static Pages sidebar and on a Page when a snippet is added.',
        'no_records' => 'No snippets found',
        'menu_label' => 'Snippets',
        'column_property' => 'Property title',
        'column_type' => 'Type',
        'column_code' => 'Code',
        'column_default' => 'Default',
        'column_options' => 'Options',
        'column_type_string' => 'String',
        'column_type_checkbox' => 'Checkbox',
        'column_type_dropdown' => 'Dropdown',
        'not_found' => 'Snippet with the requested code :code was not found in the theme.',
        'property_format_error' => 'Property code should start with a Latin letter and can contain only Latin letters and digits',
        'invalid_option_key' => 'Invalid drop-down option key: %s. Option keys can contain only digits, Latin letters and characters _ and -'
    ]
];
