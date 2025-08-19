<?php
/**
 * Save prefs (POST)
 * - Relatieve redirect om /test/ duplicaties te voorkomen
 */
if (!ossn_isLoggedin()) {
    ossn_trigger_message('Access denied', 'error');
    redirect(REF);
}

$user  = ossn_loggedin_user();
$mode  = input('mode'); // all | auto | langs
$langs = input('langs');

if ($mode !== 'all' && $mode !== 'auto' && $mode !== 'langs') {
    $mode = 'auto';
}
if (!is_array($langs)) {
    $langs = [];
}
$langs = array_values(array_unique(array_map('lf_norm_lang', $langs)));

$prefs = ['mode' => $mode, 'langs' => $langs];
languagefeed_set_prefs($user->guid, $prefs);

ossn_trigger_message(ossn_print('languagefeed:prefs:saved'));

// Belangrijk: RELATIEVE redirect (voorkomt /test/https://... issues)
redirect('languagefeed_prefs');
