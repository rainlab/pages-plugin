<?php

return [
    'plugin' => [
        'name' => 'Seiten',
        'description' => 'Pages & menus features.',
    ],
    'page' => [
        'menu_label' => 'Seiten',
        'delete_confirmation' => 'Möchten Sie die ausgewählten Seiten wirklich löschen? Dadurch werden auch mögliche Unterseiten gelöscht.',
        'no_records' => 'Keine Seiten gefunden',
        'delete_confirm_single' => 'Möchten Sie die ausgewählte Seite wirklich löschen? Dadurch werden auch mögliche Unterseiten gelöscht.',
        'new' => 'Neue Seite',
        'add_subpage' => 'Neue Unterseite',
        'invalid_url' => 'Ungültiges URL Format. Die URL sollte mit einem Schrägstrich (Slash) starten und darf Ziffern, Buchstaben und folgenden Symbole enthalten: _-/',
        'url_not_unique' => 'Die URL wird schon von einer anderen Seite benutzt.',
        'layout' => 'Layout',
        'layouts_not_found' => 'Keine Layouts gefunden',
        'saved' => 'Die Seite wurde erfolgreich gespeichert.',
        'tab' => 'Seiten',
        'manage_pages' => 'Verwalte statische Seiten',
        'manage_menus' => 'Verwalte statische Menüs',
        'access_snippets' => 'Verwalte Snippets',
      'manage_content' => 'Verwalte den Inhalt'
    ],
    'menu' => [
        'menu_label' => 'Menüs',
        'delete_confirmation' => 'Möchten Sie die ausgewählten Menüs wirklich löschen?',
        'no_records' => 'Keine Menüs gefunden',
        'new' => 'Neues Menü',
        'new_name' => 'Menüname',
        'new_code' => 'menuename',
        'delete_confirm_single' => 'Möchten Sie das ausgewählte Menü wirklich löschen?',
        'saved' => 'Das Menü wurde erfolgreich gespeichert.',
        'name' => 'Name',
        'code' => 'Code',
        'items' => 'Menüpunkte',
        'add_subitem' => 'Neuer Menüpunkt',
        'no_records' => 'Keine Menüpunkte gefunden',
        'code_required' => 'Ein Code ist erforderlich',
        'invalid_code' => 'Ungültiges Code Format. Der Code darf Ziffern, Buchstaben und folgenden Symbole enthalten: _-/'
    ],
    'menuitem' => [
        'title' => 'Titel',
        'editor_title' => 'Menüpunkt bearbeiten',
        'type' => 'Typ',
        'allow_nested_items' => 'Erlaube verschachtelte Menüpunkte',
        'allow_nested_items_comment' => 'Verschachtelte Menüpunkte können dynamisch durch statische Seiten und einigen anderen Menüpunkt-Typen erzeugt werden.',
        'url' => 'URL',
        'reference' => 'Referenz',
        'title_required' => 'Ein Titel ist erforderlich',
        'unknown_type' => 'Unbekannter Menüpunkt-Typ',
        'unnamed' => 'Unbekannter Menüpunkt',
        'add_item' => 'Neuer Menüpunkt',
        'new_item' => 'Neuer Menüpunkt',
        'replace' => 'Ersetze diesen Menüpunkt mit seinen Unterpunkten',
        'replace_comment' => 'Verwenden Sie diese Option, um erzeugte Menüpunkte auf die gleiche Ebene von diesem zu bringen. Dieser Menüpunkt selbst wird ausgeblendet.',
        'cms_page' => 'CMS Seite',
        'cms_page_comment' => 'Wählen Sie eine Seite die geöffnet werden soll, wenn dieser Menüpunkt angeklickt wird.',
        'reference_required' => 'Eine Menüpunkt-Referenz ist erforderlich',
        'url_required' => 'Eine URL ist erforderlich',
        'cms_page_required' => 'Bitten wählen Sie eine CMS Seite',
        'code' => 'Code',
        'code_comment' => 'Geben Sie einen Menüpunkt-Code ein, wenn Sie diesen mit der API ansprechen möchten.'
    ],
    'content' => [
        'menu_label' => 'Inhalte',
        'cant_save_to_dir' => 'Das Speichern von Inhaltsdateien in den Statische-Seiten Ordner ist nicht erlaubt.'
    ],
    'sidebar' => [
        'add' => 'Neu',
        'search' => 'Suche...'
    ],
    'object' => [
        'invalid_type' => 'Unbekannter Objekttyp',
        'not_found' => 'Das angeforderte Objekt wurde nicht gefunden.'
    ],
    'editor' => [
        'title' => 'Titel',
        'new_title' => 'Titel für die neue Seite',
        'content' => 'Inhalt',
        'url' => 'URL',
        'filename' => 'Dateiname',
        'layout' => 'Layout',
        'description' => 'Beschreibung',
        'preview' => 'Vorschau',
        'enter_fullscreen' => 'Vollbildmodus einschalten',
        'exit_fullscreen' => 'Vollbildmodus verlassen',
        'hidden' => 'Verstecken',
        'hidden_comment' => 'Versteckte Seiten sind nur für eingeloggte administrations Benutzer zugänglich.',
        'navigation_hidden' => 'In der Navigation verstecken',
        'navigation_hidden_comment' => 'Setzen Sie diese Option, um diese Seite von automatisch generierten Menüs und Breadcrumbs zu verstecken.'
    ],
    'snippet' => [
        'partialtab' => 'Snippet',
        'code' => 'Snippet code',
        'code_comment' => 'Geben Sie einen Code ein, um dieses Partial als ein Snippet für das Statische-Seiten Plugin freizugeben.',
        'name' => 'Name',
        'name_comment' => 'Der Name wird in der Snippet-Liste in der Seitenleiste der Statische-Seiten angezeigt. Außerdem auf einer Seite wenn ein Snippet angelegt wird.',
        'no_records' => 'Keine Snippets gefunden',
        'menu_label' => 'Snippets',
        'column_property' => 'Titel für die Eigentschaft',
        'column_type' => 'Typ',
        'column_code' => 'Code',
        'column_default' => 'Standard',
        'column_options' => 'Optionen',
        'column_type_string' => 'Zeichenkette',
        'column_type_checkbox' => 'Checkbox',
        'column_type_dropdown' => 'Dropdown',
        'not_found' => 'Das Snippet mit dem angeforderten code :code wurde nicht im Theme gefunden.',
        'property_format_error' => 'Der Code für die Eigenschaft muss mit einem Buchstaben anfangen, und darf nur Buchstaben und Zahlen enthalten',
        'invalid_option_key' => 'Ungültiger Dropdown Optionsschlüssel: %s. Optionsschlüssel dürfen nur Zahlen, Buchstaben und die Zeichen _ und - enthalten'
    ]
];
