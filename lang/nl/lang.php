<?php

return [
    'plugin' => [
        'name' => 'Pages',
        'description' => 'Pages & menus features.',
    ],
    'page' => [
        'menu_label' => 'Pagina\'s',
        'delete_confirmation' => 'Bent u zeker dat u de geselecteerde pagina\'s wilt verwijderen? Ook eventuele subpagina\'s zullen hierdoor verwijderd worden.',
        'no_records' => 'Geen pagina\'s gevonden',
        'delete_confirm_single' => 'Bent u zeker dat u deze pagina wilt verwijderen? Ook eventuele subpagina\'s zullen hierdoor verwijderd worden.',
        'new' => 'Nieuwe pagina',
        'add_subpage' => 'Subpagina toevoegen',
        'invalid_url' => 'Ongeldige URL-structuur. De URL moet beginnen met een slash en kan enkel cijfers, Latijnse letters en deze symbolen bevatten: _-/',
        'url_not_unique' => 'Deze URL wordt al gebruikt door een andere pagina.',
        'layout' => 'Layout',
        'layouts_not_found' => 'Geen layout gevonden',
        'saved' => 'De pagina is opgeslagen.'
    ],
    'menu' => [
        'menu_label' => 'Menu\'s',
        'delete_confirmation' => 'Bent u zeker dat u de geselecteerde menu\'s wilt verwijderen?',
        'no_records' => 'Geen menu\'s gevonden',
        'new' => 'Nieuw menu',
        'new_name' => 'Nieuw menu',
        'new_code' => 'nieuw-menu',
        'delete_confirm_single' => 'Bent u zeker dat u dit menu wilt verwijderen?',
        'saved' => 'Het menu is opgeslagen.',
        'name' => 'Naam',
        'code' => 'Code',
        'items' => 'Menu items',
        'add_subitem' => 'Subitem toevoegen',
        'no_records' => 'Geen items gevonden',
        'code_required' => 'Een Code is verplicht',
        'invalid_code' => 'Ongeldige code-structuur. De Code kan enkel cijfers, Latijnse letters en deze symbolen bevatten: _-'
    ],
    'menuitem' => [
        'title' => 'Titel',
        'editor_title' => 'Bewerk Menu Item',
        'type' => 'Type',
        'allow_nested_items' => 'Accepteer geneste items',
        'allow_nested_items_comment' => 'Geneste items worden dynamisch gegenereerd door statische pagina\'s en sommige andere types.',
        'url' => 'URL',
        'reference' => 'Referentie',
        'title_required' => 'Een Titel is verplicht',
        'unknown_type' => 'Onbekend menu item type',
        'unnamed' => 'Onbenoemd menu item',
        'add_item' => '<u>I</u>tem toevoegen',
        'new_item' => 'Nieuw menu item',
        'replace' => 'Vervang dit item door de gegenereerde subitems',
        'replace_comment' => 'Wanneer u deze optie aanvinkt, zullen de gegenereerd menu items worden getoond op het niveau van dit item. Dit item zelf zal verborgen blijven.',
        'cms_page' => 'CMS Pagina',
        'cms_page_comment' => 'Selecteer een pagina om te openen wanneer op het menu item geklikt wordt.',
        'reference_required' => 'Een referentie is verplicht.',
        'url_required' => 'Een URL is verplicht',
        'cms_page_required' => 'Gelieve een CMS Pagina te selecteren',
        'code' => 'Code',
        'code_comment' => 'Geef de menu item code op indien u deze wilt benaderen via de API.'
    ],
    'content' => [
        'menu_label' => 'Inhoud',
        'cant_save_to_dir' => 'Het is niet toegelaten bestanden met inhoud op te slaan in de static-pages map.'
    ],
    'sidebar' => [
        'add' => 'Toevoegen',
        'search' => 'Zoeken...'
    ],
    'object' => [
        'invalid_type' => 'Onbekend object type',
        'not_found' => 'Het gevraagde object is niet gevonden.'
    ],
    'editor' => [
        'title' => 'Titel',
        'new_title' => 'Nieuwe pagina titel',
        'content' => 'Inhoud',
        'url' => 'URL',
        'filename' => 'Bestandsnaam',
        'layout' => 'Layout',
        'description' => 'Beschrijving',
        'preview' => 'Voorbeeld',
        'enter_fullscreen' => 'Volledig scherm openen',
        'exit_fullscreen' => 'Volledig scherm afsluiten',
        'hidden' => 'Verborgen',
        'hidden_comment' => 'Verborgen pagina\'s zijn alleen toegankelijk voor ingelogde gebruikers.',
        'navigation_hidden' => 'Verbergen in de navigatie',
        'navigation_hidden_comment' => 'Indien aangevinkt, zal deze pagina niet weergegeven worden in automatisch gegenereerde menu\'s en kruimelpaden (breadcrumbs).',
    ],
];
