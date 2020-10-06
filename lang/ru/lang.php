<?php

return [
    'plugin' => [
        'name' => 'Страницы',
        'description' => 'Страницы и меню.',
    ],
    'page' => [
        'menu_label' => 'Страницы',
        'template_title' => '%s Страницы',
        'delete_confirmation' => 'Вы действительно хотите удалить выбранные страницы? Это также удалит имеющиеся подстраницы.',
        'no_records' => 'Страниц не найдено',
        'delete_confirm_single' => 'Вы действительно хотите удалить эту страницу? Это также удалит имеющиеся подстраницы.',
        'new' => 'Новая страница',
        'add_subpage' => 'Добавить подстраницу',
        'invalid_url' => 'Некорректный формат URL. URL должен начинаться с прямого слеша и может содержать цифры, латинские буквы и следующие символы: _-/.',
        'url_not_unique' => 'Это URL уже используется другой страницей.',
        'layout' => 'Шаблон',
        'layouts_not_found' => 'Шаблоны не найдены',
        'saved' => 'Страница была успешно сохранена.',
        'tab' => 'Страницы',
        'manage_pages' => 'Управление страницами',
        'manage_menus' => 'Управление меню',
        'access_snippets' => 'Доступ к сниппетами',
        'manage_content' => 'Управление содержимым',
    ],
    'menu' => [
        'menu_label' => 'Меню',
        'delete_confirmation' => 'Вы действительно хотите удалить выбранные пункты меню?',
        'no_records' => 'Меню не найдены',
        'new' => 'Новое меню',
        'new_name' => 'Новое меню',
        'new_code' => 'novoe-menyu',
        'delete_confirm_single' => 'Вы действительно хотите удалить это меню?',
        'saved' => 'Меню было успешно сохранено.',
        'name' => 'Имя',
        'code' => 'Код',
        'items' => 'Пункты меню',
        'add_subitem' => 'Добавить подменю',
        'code_required' => 'Поле Код обязательно',
        'invalid_code' => 'Некорректный формат Кода. Код может содержать цифры, латинские буквы и следующие символы: _-/'
    ],
    'menuitem' => [
        'title' => 'Название',
        'editor_title' => 'Редактировать пункт меню',
        'type' => 'Тип',
        'allow_nested_items' => 'Разрешить вложенные',
        'allow_nested_items_comment' => 'Вложенные пункты могут быть динамически сгенерированы статической страницей или другими типами элементов',
        'url' => 'URL',
        'reference' => 'Ссылка',
        'title_required' => 'Название обязательно',
        'unknown_type' => 'Неизвестный тип меню',
        'unnamed' => 'Безымянный пункт',
        'add_item' => 'Добавить пункт (<u>i</u>)',
        'new_item' => 'Новый пункт',
        'replace' => 'Заменять этот пункт его сгенерированными потомками',
        'replace_comment' => 'Отметьте для переноса генерируемых пунктов меню на один уровень с этим пунктом. Сам этот пункт будет скрыт.',
        'cms_page' => 'Страницы CMS',
        'cms_page_comment' => 'Выберите открываемую по клику страницу.',
        'reference_required' => 'Необходима ссылка для пункта меню.',
        'url_required' => 'Необходим URL',
        'cms_page_required' => 'Пожалуйста, выберите страницу CMS',
        'code' => 'Код',
        'code_comment' => 'Введите код пункта меню, если хотите иметь к нему доступ через API.'
    ],
    'content' => [
        'menu_label' => 'Содержимое',
        'cant_save_to_dir' => 'Сохранение файлов содержимого в директорию static-pages запрещено.'
    ],
    'sidebar' => [
        'add' => 'Добавить',
        'search' => 'Поиск...'
    ],
    'object' => [
        'invalid_type' => 'Неизвестный тип объекта',
        'not_found' => 'Запрашиваемый объект не найден.'
    ],
    'editor' => [
        'title' => 'Название',
        'new_title' => 'Название новой страницы',
        'content' => 'Содержимое',
        'url' => 'URL',
        'filename' => 'Имя Файла',
        'layout' => 'Шаблон',
        'description' => 'Описание',
        'preview' => 'Предпросмотр',
        'enter_fullscreen' => 'Войти в полноэкранный режим',
        'exit_fullscreen' => 'Выйти из полноэкранного режима',
        'hidden' => 'Скрытый',
        'hidden_comment' => 'Скрытые страницы доступны только вошедшим администраторам.',
        'navigation_hidden' => 'Спрятать в навигации',
        'navigation_hidden_comment' => 'Отметьте, чтобы скрыть эту страницу в генерируемых меню и хлебных крошках.',
    ],
    'snippet' => [
        'partialtab' => 'Сниппеты',
        'code' => 'Код сниппета',
        'code_comment' => 'Введите код, чтобы сделать этот фрагмент доступным как сниппет в расширении Страницы.',
        'name' => 'Имя',
        'name_comment' => 'Имя отображается в списке сниппетов расширения Страницы и на странице, когда сниппет добавлен.',
        'no_records' => 'Сниппеты не найдены',
        'menu_label' => 'Сниппеты',
        'column_property' => 'Название свойства',
        'column_type' => 'Тип',
        'column_code' => 'Код',
        'column_default' => 'По умолчанию',
        'column_options' => 'Опции',
        'column_type_string' => 'Строка',
        'column_type_checkbox' => 'Чекбокс',
        'column_type_dropdown' => 'Выпадающий список',
        'not_found' => 'Сниппет с запрошенным кодом %s не найден в теме.',
        'property_format_error' => 'Код свойства должен начинаться с латинской буквы и может содержать только латинские буквы и цифры',
        'invalid_option_key' => 'Некорректный ключ выпадающего списка: %s. Ключ может содержать только цифры, латинские буквы и символы _ и -'
    ],
    'component' => [
        'static_page_description' => 'Выводит страницу в CMS шаблоне.',
        'static_menu_description' => 'Выводит меню в CMS шаблоне.',
        'static_menu_menu_code' => 'Укажите код меню, которое должно быть показано',
        'static_breadcrumbs_description' => 'Выводит хлебные крошки для страницы.',
    ],
];
