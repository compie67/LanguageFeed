<?php
/**
 * Component: LanguageFeed
 * Beschrijving:
 * - Gebruikerstaal via $user->language (user-entity 'language')
 * - Voorkeuren per gebruiker (user-entity 'languagefeed_prefs' als JSON)
 * - Nieuwe posts krijgen lang-tag via ENTITIES (type=object, subtype=lang)
 * - Eigen feed + prefs + admin retro (prefs & retro posten direct naar pagina; geen action-gedoe nodig)
 *
 * Vereist:
 * - Vertalingen in /components/LanguageFeed/locale/ossn.xx.php
 * - (optioneel) /components/LanguageFeed/vendors/hpp_lang_detect.php met functie hpp_detect_language($text):string
 */

define('LANGFEED_SUPPORTED', ['nl','en','de','fr','es','it','pt','pl','sv','da']);

/* =========================================================
 * INIT
 * =======================================================*/
function languagefeed_init() {
    // CSS
    ossn_extend_view('css/ossn.default', 'languagefeed/css');

    // Pages
    ossn_register_page('languagefeed',        'languagefeed_page_handler');
    ossn_register_page('languagefeed_prefs',  'languagefeed_prefs_handler');   // POST opslaan hier zelf
    ossn_register_page('languagefeed_admin',  'languagefeed_admin_handler');   // POST retro hier zelf

    // (optioneel; legacy compat, niet gebruikt door de UI)
    ossn_register_action('languagefeed/save_prefs', __DIR__ . '/actions/languagefeed/save_prefs.php');

    // Hooks
    ossn_register_callback('wall', 'post:created', 'languagefeed_on_post_created');

    // Menu
    if (ossn_isLoggedin()) {
        ossn_register_menu_link('languagefeed', ossn_print('languagefeed:menu:title'), ossn_site_url('languagefeed'), 'topbar_dropdown');
        ossn_register_menu_link('languagefeed_prefs', '[' . ossn_print('languagefeed:prefs') . ']', ossn_site_url('languagefeed_prefs'), 'topbar_dropdown');
    }
    if (ossn_isAdminLoggedin()) {
        ossn_register_menu_link('languagefeed_admin', ossn_print('languagefeed:admin:title'), ossn_site_url('languagefeed_admin'), 'admin/sidemenu');
    }

    // (optioneel) simpele detectie-stub
    $det = __DIR__ . '/vendors/hpp_lang_detect.php';
    if (is_file($det)) {
        include_once $det;
    }
}
ossn_register_callback('ossn', 'init', 'languagefeed_init');

/* =========================================================
 * HELPERS & CORE
 * =======================================================*/

/** Normaliseer ISO 639-1 code (2 letters) */
function lf_norm_lang($code) {
    if (!is_string($code) || $code === '') return 'und';
    $c = strtolower($code);
    if (preg_match('/^[a-z]{2}/', $c, $m)) return $m[0];
    return $c;
}

/** Tekstextract (ondersteunt JSON {"post":"..."} of nested arrays) */
function languagefeed_extract_text($description) {
    $desc = (string)$description;
    if ($desc === '') return '';
    $plain = html_entity_decode(strip_tags($desc), ENT_QUOTES, 'UTF-8');
    $trim  = trim($plain);

    // JSON payload?
    if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
        $json = json_decode($trim, true);
        if (is_array($json)) {
            if (isset($json['post']) && is_string($json['post'])) {
                return trim($json['post']);
            }
            $buf = [];
            $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($json));
            foreach ($it as $v) {
                if (is_string($v)) $buf[] = $v;
            }
            if ($buf) return trim(implode(' ', $buf));
        }
    }
    return $trim;
}

/** Gebruikerstaal ($user->language) met site fallback */
function languagefeed_get_user_language($guid = null) {
    $u = $guid ? ossn_user_by_guid((int)$guid) : ossn_loggedin_user();
    if (!$u || !isset($u->language) || !$u->language) {
        $site = ossn_site_settings();
        $site_lang = isset($site->language) ? $site->language : 'en';
        return lf_norm_lang($site_lang);
    }
    return lf_norm_lang($u->language);
}

/** Voorkeuren opslaan (user-entity) */
function languagefeed_set_prefs($user_guid, array $prefs) {
    $entity             = new OssnEntities;
    $entity->owner_guid = (int)$user_guid;
    $entity->type       = 'user';
    $entity->subtype    = 'languagefeed_prefs';
    $entity->value      = json_encode($prefs, JSON_UNESCAPED_UNICODE);
    return (bool)$entity->add(); // laatste wint
}

/** Voorkeuren lezen */
function languagefeed_get_prefs($user_guid) {
    $entities = ossn_get_entities([
        'type'       => 'user',
        'subtype'    => 'languagefeed_prefs',
        'owner_guid' => (int)$user_guid,
        'order_by'   => 'guid DESC',
        'limit'      => 1,
    ]);
    if ($entities && is_array($entities) && isset($entities[0]->value)) {
        $arr = json_decode($entities[0]->value, true);
        if (is_array($arr)) return $arr;
    }
    return ['mode' => 'auto', 'langs' => []];
}

/** Bepaal filter op basis van prefs van user */
function languagefeed_resolve_filter_for_user($user_guid) {
    $prefs = languagefeed_get_prefs($user_guid);
    $mode  = $prefs['mode'] ?? 'auto';
    if ($mode === 'all') {
        return ['mode' => 'all', 'set' => []];
    }
    if ($mode === 'langs') {
        $langs = array_values(array_unique(array_map('lf_norm_lang', (array)($prefs['langs'] ?? []))));
        return ['mode' => 'langs', 'set' => $langs];
    }
    return ['mode' => 'auto', 'set' => [languagefeed_get_user_language($user_guid)]];
}

/** Zet/overschrijf lang-tag via ENTITIES (compat) */
function languagefeed_set_object_lang($object_guid, $lang) {
    $e = new OssnEntities;
    $e->owner_guid = (int)$object_guid;
    $e->type       = 'object';
    $e->subtype    = 'lang';
    $e->value      = lf_norm_lang($lang);
    return (bool)$e->add();
}

/**
 * Lees lang-tag van post:
 *  1) $object->data['lang'] (indien aanwezig)
 *  2) entity lookup (type=object, subtype=lang)
 *  3) detectie uit tekst (hpp_detect_language)
 *  4) fallback: eigenaarstaal
 */
function languagefeed_get_post_lang($object) {
    if (isset($object->data) && is_array($object->data) && !empty($object->data['lang'])) {
        return lf_norm_lang($object->data['lang']);
    }
    $ents = ossn_get_entities([
        'type'       => 'object',
        'subtype'    => 'lang',
        'owner_guid' => (int)$object->guid,
        'order_by'   => 'guid DESC',
        'limit'      => 1,
    ]);
    if ($ents && isset($ents[0]->value) && $ents[0]->value !== '') {
        return lf_norm_lang($ents[0]->value);
    }
    $text = '';
    if (isset($object->description)) {
        $text = languagefeed_extract_text($object->description);
    }
    if ($text !== '' && function_exists('hpp_detect_language')) {
        try {
            $det = (string) hpp_detect_language($text);
            if ($det !== '') return lf_norm_lang($det);
        } catch (\Throwable $e) { /* ignore */ }
    }
    return languagefeed_get_user_language($object->owner_guid);
}

/* =========================================================
 * CALLBACKS
 * =======================================================*/

/** Nieuwe post: zet lang via entity */
function languagefeed_on_post_created($cb, $type, $params) {
    if (empty($params['guid'])) return;
    $post_guid = (int)$params['guid'];
    $object    = ossn_get_object($post_guid);
    if (!$object) return;

    $text = '';
    if (isset($object->description)) {
        $text = languagefeed_extract_text($object->description);
    }
    $lang = '';
    if (function_exists('hpp_detect_language')) {
        try { $lang = (string) hpp_detect_language($text); } catch (\Throwable $e) { $lang = ''; }
    }
    if ($lang === '') {
        $lang = languagefeed_get_user_language($object->owner_guid);
    }
    languagefeed_set_object_lang($post_guid, $lang);
}

/* =========================================================
 * PAGES
 * =======================================================*/

function languagefeed_page_handler($segments) {
    if (!ossn_isLoggedin()) {
        ossn_error_page();
        return true;
    }
    $user = ossn_loggedin_user();

    $req = input('lang');
    if ($req) {
        if ($req === 'all') {
            $filter = ['mode' => 'all', 'set' => []];
        } elseif ($req === 'auto') {
            $filter = ['mode' => 'auto', 'set' => [languagefeed_get_user_language($user->guid)]];
        } else {
            $items  = array_filter(array_map('trim', explode(',', $req)));
            $filter = ['mode' => 'langs', 'set' => array_values(array_unique(array_map('lf_norm_lang', $items)))];
        }
    } else {
        $filter = languagefeed_resolve_filter_for_user($user->guid);
    }

    $obj   = new OssnObject;
    $posts = $obj->searchObject([
        'type'       => 'user',
        'subtype'    => 'wall',
        'page_limit' => 50,
        'order_by'   => 'o.guid DESC',
    ]);

    $filtered = [];
    if ($posts) {
        foreach ($posts as $o) {
            if (!languagefeed_object_is_visible($o)) continue;
            $post_lang = languagefeed_get_post_lang($o);
            if (languagefeed_passes_filter($post_lang, $filter)) {
                $filtered[] = $o;
            }
        }
    }

    $title   = ossn_print('languagefeed:title');
    $content = ossn_plugin_view('languagefeed/page', [
        'posts'  => $filtered,
        'filter' => $filter,
        'me'     => $user,
    ]);
    $body = ossn_set_page_layout('newsfeed', ['content' => $content]);
    echo ossn_view_page($title, $body);
    return true;
}

/** Preferences: GET = formulier, POST = direct opslaan */
function languagefeed_prefs_handler($segments) {
    if (!ossn_isLoggedin()) {
        ossn_error_page();
        return true;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user  = ossn_loggedin_user();
        $mode  = input('mode');
        $langs = input('langs');
        if ($mode !== 'all' && $mode !== 'auto' && $mode !== 'langs') $mode = 'auto';
        if (!is_array($langs)) $langs = [];
        $langs = array_values(array_unique(array_map('lf_norm_lang', $langs)));
        $prefs = ['mode' => $mode, 'langs' => $langs];
        languagefeed_set_prefs($user->guid, $prefs);
        ossn_trigger_message(ossn_print('languagefeed:prefs:saved'));
        redirect('languagefeed_prefs');
        return true;
    }
    $title   = ossn_print('languagefeed:prefs:title');
    $content = ossn_plugin_view('languagefeed/prefs');
    $body = ossn_set_page_layout('newsfeed', ['content' => $content]);
    echo ossn_view_page($title, $body);
    return true;
}

/** Admin: GET = formulier, POST = retro uitvoeren (lang via entity) */
function languagefeed_admin_handler($segments) {
    if (!ossn_isAdminLoggedin()) {
        ossn_error_page();
        return true;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $limit = (int) input('limit', 500);
        if ($limit < 10)   $limit = 10;
        if ($limit > 5000) $limit = 5000;

        $db = new OssnDatabase;
        $params = [
            'from'     => 'ossn_object AS o',
            'wheres'   => ["o.subtype='wall'"],
            'order_by' => 'o.guid DESC',
            'limit'    => $limit,
        ];
        $db->select($params, true);
        $list = $db->fetch(true);

        $count = 0;
        if ($list) {
            foreach ($list as $r) {
                if ($o = ossn_get_object((int)$r->guid)) {
                    // als er al een lang-entity is, overslaan
                    $ents = ossn_get_entities([
                        'type'       => 'object',
                        'subtype'    => 'lang',
                        'owner_guid' => (int)$o->guid,
                        'order_by'   => 'guid DESC',
                        'limit'      => 1,
                    ]);
                    if ($ents && isset($ents[0]->value) && $ents[0]->value !== '') continue;

                    $text = isset($o->description) ? languagefeed_extract_text($o->description) : '';
                    $lang = '';
                    if (function_exists('hpp_detect_language')) {
                        try { $lang = (string) hpp_detect_language($text); } catch (\Throwable $e) { $lang = ''; }
                    }
                    if ($lang === '') {
                        $lang = languagefeed_get_user_language($o->owner_guid);
                    }
                    languagefeed_set_object_lang($o->guid, $lang);
                    $count++;
                }
            }
        }
        ossn_trigger_message(sprintf(ossn_print('languagefeed:admin:ran'), $count));
        redirect('languagefeed_admin');
        return true;
    }

    $title   = ossn_print('languagefeed:admin:title');
    $content = ossn_plugin_view('languagefeed/admin');
    $body = ossn_set_page_layout('newsfeed', ['content' => $content]);
    echo ossn_view_page($title, $body);
    return true;
}

/* =========================================================
 * MISC HELPERS
 * =======================================================*/

function languagefeed_object_is_visible($o) {
    $access = isset($o->access) ? (int)$o->access : 1; // 1=public
    if ($access === 1) return true;
    if (!ossn_isLoggedin()) return false;
    $me = ossn_loggedin_user();
    if ($o->owner_guid == $me->guid) return true;
    if (function_exists('ossn_is_friend') && ossn_is_friend($me->guid, $o->owner_guid)) return true;
    return false;
}

function languagefeed_passes_filter($post_lang, $filter) {
    $pl = lf_norm_lang($post_lang);
    if ($filter['mode'] === 'all') return true;
    return in_array($pl, (array)$filter['set'], true);
}
