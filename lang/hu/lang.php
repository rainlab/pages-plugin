<?php

return [
    'plugin' => [
        'name' => 'Lapok',
        'description' => 'Lapok, menük és kódrészletek menedzselése.',
    ],
    'page' => [
        'menu_label' => 'Lapok',
        'delete_confirmation' => 'Valóban törölni akarja a kijelölt lapokat? Ez az allapokat is törölni fogja.',
        'no_records' => 'Nem található lap',
        'delete_confirm_single' => 'Valóban törölni akarja ezt a lapot? Ez az allapokat is törölni fogja.',
        'new' => 'Új lap',
        'add_subpage' => 'Allap hozzáadása',
        'invalid_url' => 'Érvénytelen az URL cím formátuma. Perjellel kell kezdődnie, és számokat, latin betűket, valamint a következő szimbólumokat tartalmazhatja: _-/',
        'url_not_unique' => 'Már használja egy másik lap ezt az URL címet.',
        'layout' => 'Elrendezés',
        'layouts_not_found' => 'Nem található elrendezés',
        'saved' => 'A lap mentése sikerült.',
        'tab' => 'Lapok',
        'manage_pages' => 'Lapok kezelése',
        'manage_menus' => 'Menük kezelése',
        'access_snippets' => 'Kódrészletek kezelése',
        'manage_content' => 'Tartalom kezelése'
    ],
    'menu' => [
        'menu_label' => 'Menük',
        'delete_confirmation' => 'Valóban törölni akarja a kijelölt menüket?',
        'no_records' => 'Nem található menü',
        'new' => 'Új menü',
        'new_name' => 'Új menü',
        'new_code' => 'uj-menu',
        'delete_confirm_single' => 'Valóban törölni akarja ezt a menüt?',
        'saved' => 'A menü mentése sikerült.',
        'name' => 'Név',
        'code' => 'Kód',
        'items' => 'Menüpont',
        'add_subitem' => 'Almenüpont hozzáadása',
        'no_records' => 'Nem található menüpont',
        'code_required' => 'A Kód kötelező',
        'invalid_code' => 'Érvénytelen a kód formátuma. Csak számokat, latin betűket és a következő szimbólumokat tartalmazhatja: _-'
    ],
    'menuitem' => [
        'title' => 'Cím',
        'editor_title' => 'Menüpont szerkesztése',
        'type' => 'Típus',
        'allow_nested_items' => 'Beágyazott menüpontok engedélyezése',
        'allow_nested_items_comment' => 'A beágyazott menüpontokat a lap és néhány más menüpont típus dinamikusan generálhatja',
        'url' => 'URL cím',
        'reference' => 'Hivatkozás',
        'title_required' => 'A cím megadása kötelező',
        'unknown_type' => 'Ismeretlen menüponttípus',
        'unnamed' => 'Névtelen menüpont',
        'add_item' => '<u>M</u>enüpont hozzáadása',
        'new_item' => 'Új menüpont',
        'replace' => 'A menüpont kicserélése a generált gyermekeire',
        'replace_comment' => 'Ennek a jelölőnégyzetnek a használatával viheti a generált menüpontokat az ezen menüpont által azonos szintre. Maga ez a menüpont rejtett marad.',
        'cms_page' => 'Lap',
        'cms_page_comment' => 'Válassza ki a menüpontra kattintáskor megnyitni kívánt lapot.',
        'reference_required' => 'A menüpont hivatkozás kitöltése kötelező.',
        'url_required' => 'Az URL cím kitöltése kötelező',
        'cms_page_required' => 'Válasszon egy lapot',
        'code' => 'Kód',
        'code_comment' => 'Adja meg a menüpont kódját, ha az API-val akarja elérni.'
    ],
    'content' => [
        'menu_label' => 'Tartalom',
        'cant_save_to_dir' => 'A tartalomfájlok mentése a static-pages könyvtárba nem engedélyezett.'
    ],
    'sidebar' => [
        'add' => 'Hozzáadás',
        'search' => 'Keresés...'
    ],
    'object' => [
        'invalid_type' => 'Ismeretlen objektumtípus',
        'not_found' => 'A kért objektum nem található.'
    ],
    'editor' => [
        'title' => 'Cím',
        'new_title' => 'Új lap címe',
        'content' => 'Tartalom',
        'url' => 'URL cím',
        'filename' => 'Fájlnév',
        'layout' => 'Elrendezés',
        'description' => 'Leírás',
        'preview' => 'Előnézet',
        'enter_fullscreen' => 'Váltás teljes képernyős módra',
        'exit_fullscreen' => 'Kilépés a teljes képernyős módból',
        'hidden' => 'Rejtett',
        'hidden_comment' => 'A rejtett lapokhoz csak a bejelentkezett kiszolgáló oldali felhasználók férhetnek hozzá.',
        'navigation_hidden' => 'Elrejtés a navigációban',
        'navigation_hidden_comment' => 'Jelölje be ezt a jelölőnégyzetet ennek a lapnak az automatikusan generált menükből és útkövetésekből való elrejtéséhez.'
    ],
    'snippet' => [
        'partialtab' => 'Kódrészlet',
        'code' => 'Kódrészlet kódja',
        'code_comment' => 'Enter a code to make this partial available as a snippet in the Static Pages plugin.',
        'name' => 'Név',
        'name_comment' => 'The name is displayed in the snippet list in the Static Pages sidebar and on a Page when a snippet is added.',
        'no_records' => 'Nincs megjeleníthető kódrészlet.',
        'menu_label' => 'Kódrészletek',
        'column_property' => 'Cím',
        'column_type' => 'Típus',
        'column_code' => 'kód',
        'column_default' => 'Alapértelmezett',
        'column_options' => 'Lehetőségek',
        'column_type_string' => 'Szöveg',
        'column_type_checkbox' => 'Jelölőnégyzet',
        'column_type_dropdown' => 'Lenyíló lista',
        'not_found' => 'A(z) :code nevű kódrészlet nem található a témában.',
        'property_format_error' => 'Property code should start with a Latin letter and can contain only Latin letters and digits',
        'invalid_option_key' => 'Invalid drop-down option key: %s. Option keys can contain only digits, Latin letters and characters _ and -'
    ]
];