<?php
/**
 * Retro-tag action (admin only) — gebruikt ENTITIES (object, lang)
 * NB: In jouw setup roepen we de retro vanuit /languagefeed_admin (POST),
 * maar deze action blijft bruikbaar voor losse calls die wél via actions lopen.
 */

if(!ossn_isAdminLoggedin()){
    ossn_trigger_message('Access denied', 'error');
    redirect(REF);
}

/** Helpers die ook in ossn_com.php staan; mini fallback als file standalone geladen wordt */
if(!function_exists('lf_norm_lang')){
    function lf_norm_lang($code){
        if(!is_string($code) || $code==='') return 'und';
        $c = strtolower($code);
        if(preg_match('/^[a-z]{2}/',$c,$m)) return $m[0];
        return $c;
    }
}
if(!function_exists('languagefeed_extract_text')){
    function languagefeed_extract_text($description){
        $desc = (string)$description;
        if($desc === '') return '';
        $plain = html_entity_decode(strip_tags($desc), ENT_QUOTES, 'UTF-8');
        $trim  = trim($plain);
        if($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')){
            $json = json_decode($trim, true);
            if(is_array($json)){
                if(isset($json['post']) && is_string($json['post'])) return trim($json['post']);
                $buf = [];
                $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($json));
                foreach($it as $v){ if(is_string($v)) $buf[] = $v; }
                if($buf) return trim(implode(' ', $buf));
            }
        }
        return $trim;
    }
}
if(!function_exists('languagefeed_get_user_language')){
    function languagefeed_get_user_language($guid=null){
        $u = $guid ? ossn_user_by_guid((int)$guid) : ossn_loggedin_user();
        if(!$u || !isset($u->language) || !$u->language){
            $site = ossn_site_settings();
            $site_lang = isset($site->language) ? $site->language : 'en';
            return lf_norm_lang($site_lang);
        }
        return lf_norm_lang($u->language);
    }
}
if(!function_exists('languagefeed_set_object_lang')){
    function languagefeed_set_object_lang($object_guid, $lang){
        $e = new OssnEntities;
        $e->owner_guid = (int)$object_guid;
        $e->type       = 'object';
        $e->subtype    = 'lang';
        $e->value      = lf_norm_lang($lang);
        return (bool)$e->add();
    }
}

$limit = (int) input('limit', 500);
if($limit < 10)   $limit = 10;
if($limit > 5000) $limit = 5000;

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
if($list){
    foreach($list as $r){
        if($o = ossn_get_object((int)$r->guid)){
            // sla over als er al een lang-entity is
            $ents = ossn_get_entities([
                'type'       => 'object',
                'subtype'    => 'lang',
                'owner_guid' => (int)$o->guid,
                'order_by'   => 'guid DESC',
                'limit'      => 1,
            ]);
            if($ents && isset($ents[0]->value) && $ents[0]->value !== '') continue;

            $text = isset($o->description) ? languagefeed_extract_text($o->description) : '';
            $lang = '';
            if(function_exists('hpp_detect_language')){
                try { $lang = (string) hpp_detect_language($text); } catch(\Throwable $e){ $lang=''; }
            }
            if($lang === ''){
                $lang = languagefeed_get_user_language($o->owner_guid);
            }
            languagefeed_set_object_lang($o->guid, $lang);
            $count++;
        }
    }
}

ossn_trigger_message("Retro-tagged {$count} posts.");
redirect('languagefeed');
