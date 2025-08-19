<?php
/**
 * 🇳🇱 Nederlandse vertalingen — LanguageFeed
 * Component: LanguageFeed
 */

$nl = array(
    // Menu / titels
    'languagefeed:menu:title'        => 'Taalfeed',
    'languagefeed:title'             => 'Taalfeed',
    'languagefeed:choose'            => 'Taal',
    'languagefeed:all'               => 'Alles',
    'languagefeed:auto'              => 'Mijn taal',
    'languagefeed:none'              => 'Geen berichten gevonden.',

    // Voorkeuren
    'languagefeed:prefs'             => 'Taalvoorkeuren',
    'languagefeed:prefs:title'       => 'Mijn Taalfeed',
    'languagefeed:prefs:desc'        => 'Kies hoe je feed posts filtert.',
    'languagefeed:prefs:mode'        => 'Filtermodus',
    'languagefeed:prefs:mode:all'    => 'Alle talen tonen',
    'languagefeed:prefs:mode:auto'   => 'Alleen mijn taal',
    'languagefeed:prefs:mode:langs'  => 'Alleen geselecteerde talen',
    'languagefeed:prefs:langs'       => 'Selecteer talen',
    'languagefeed:prefs:save'        => 'Opslaan',
    'languagefeed:prefs:saved'       => 'Voorkeuren opgeslagen.',

    // Beheer
    'languagefeed:admin:title'       => 'LanguageFeed Beheer',
    'languagefeed:admin:short'       => 'LF Beheer',
    'languagefeed:admin:retro:title' => 'Retro-tag berichten',
    'languagefeed:admin:retro:desc'  => 'Zet "lang" op recente wall-berichten (detectie ⇒ fallback op taal van eigenaar).',
    'languagefeed:admin:limit'       => 'Aantal recente berichten',
    'languagefeed:admin:run'         => 'Retro uitvoeren',
    'languagefeed:admin:ran'         => '%s berichten getagd.',
);

ossn_register_languages('nl', $nl);
