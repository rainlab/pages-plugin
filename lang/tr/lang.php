<?php

return [
    'plugin' => [
        'name' => 'Sayfalar',
        'description' => 'Pages & menus features.',
    ],
    'page' => [
        'menu_label' => 'Sayfalar',
        'delete_confirmation' => 'Seçili sayfaları silmek istiyor musunuz? Alt sayfalar da silinecektir.',
        'no_records' => 'Sayfa bulunamadı',
        'delete_confirm_single' => 'Bu sayfayı silmek istiyor musunuz? Alt sayfalar da silinecektir',
        'new' => 'Yeni sayfa',
        'add_subpage' => 'Altsayfa ekle',
        'invalid_url' => 'Geçersiz URL formatı. URL eğik çizgi sembolü ile başlamalıdır ve rakam, latin harfleri ve bu sembolleri: _-/ içerebilir.',
        'url_not_unique' => 'Bu URL başka bir sayfa tarafından kullanılıyor',
        'layout' => 'Layout',
        'layouts_not_found' => 'Layouts not found',
        'saved' => 'Sayfa başarıyla kaydedildi.'
    ],
    'menu' => [
        'menu_label' => 'Menüler',
        'delete_confirmation' => 'Seçili menüleri silmek istiyor musunuz?',
        'no_records' => 'Menü bulunamadı',
        'new' => 'Yeni Menü',
        'new_name' => 'Yeni menü',
        'new_code' => 'new-menu',
        'delete_confirm_single' => 'Bu menüyü silmek istiyor musunuz?',
        'saved' => 'Menü başarıyla kaydedildi.',
        'name' => 'İsim',
        'code' => 'Kod',
        'items' => 'Menü Ögeleri',
        'add_subitem' => 'Altöge ekle',
        'no_records' => 'Öge bulunamadı',
        'code_required' => 'Kod gerekli',
        'invalid_code' => 'Geçersiz KOD formatı. Kod yalnızca rakam, Latin harfleri ve bu sembolleri: _- içerebilir.'
    ],
    'menuitem' => [
        'title' => 'Başlık',
        'editor_title' => 'Menü Ögesini Düzenle',
        'type' => 'Tür',
        'allow_nested_items' => 'İçiçe ögelere izin ver',
        'allow_nested_items_comment' => 'İç içe öğeler statik sayfa ve bazı diğer öğe türlerine göre dinamik olarak üretilen olabilir',
        'url' => 'URL',
        'reference' => 'Referans',
        'title_required' => 'Başlık gerekli',
        'unknown_type' => 'Geçersiz menü ögesi türü',
        'unnamed' => 'İsimsiz menü ögesi',
        'add_item' => '<u>Ö</u>ge Ekle',
        'new_item' => 'Yeni menü ögesi',
        'replace' => 'Bu ögeyi oluşturulan çocuklarıyla değiştir',
        'replace_comment' => 'Use this checkbox to push generated menu items to the same level with this item. This item itself will be hidden.',
        'cms_page' => 'CMS Sayfası',
        'cms_page_comment' => 'Menü ögesine tıklandığında açılacak sayfayı seçin',
        'reference_required' => 'Menü ögesi referansı gereklidir.',
        'url_required' => 'URL gereklidir',
        'cms_page_required' => 'Lütfen bir CMS sayfası seçin',
        'code' => 'Kod',
        'code_comment' => 'API ile giriş yapabilmek için menü ögesi kodunu girin.'
    ],
    'content' => [
        'menu_label' => 'İçerik',
        'cant_save_to_dir' => 'Statik sayfalar dizinine içerik dosyalarını kaydetme izni verilmez.'
    ],
    'sidebar' => [
        'add' => 'Ekle',
        'search' => 'Ara...'
    ],
    'object' => [
        'invalid_type' => 'Bilineyen nesne türü',
        'not_found' => 'İstenen nesne bulunamadı'
    ],
    'editor' => [
        'title' => 'Başlık',
        'new_title' => 'Yeni sayfa başlığı',
        'content' => 'İçerik',
        'url' => 'URL',
        'filename' => 'Dosya Adı',
        'layout' => 'Layout',
        'description' => 'Tanımlama',
        'preview' => 'Önizleme',
        'enter_fullscreen' => 'Tam Ekran moduna geç',
        'exit_fullscreen' => 'Tam Ekran modundan çık',
        'hidden' => 'Gizli',
        'hidden_comment' => 'Gizli sayfalar yalnızca yönetim paneline giriş yapmış kullanıcılar tarafından görüntülenebilir.',
        'navigation_hidden' => 'Menüde Gizle',
        'navigation_hidden_comment' => 'Otomatik olarak oluşturulan menüler ve kırıntıları gizlemek için bu kutuyu işaretleyin.'
    ]
];
