<?php
/**
 * View: languagefeed/page
 * Params:
 * - posts: array<OssnObject> (subtype 'wall')
 * - filter: ['mode'=>..., 'set'=>[...] ]
 * - me: user
 */

/* -------- helpers (view-local) -------- */

/** Haal nette tekst uit description (ondersteunt JSON {"post":"..."}) */
function lf_view_extract_text($description){
    $desc = (string) $description;
    if($desc === '') return '';

    // Plain text uit (eventuele) HTML
    $plain = html_entity_decode(strip_tags($desc), ENT_QUOTES, 'UTF-8');
    $trim  = trim($plain);

    // JSON payload?
    if($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')){
        $json = json_decode($trim, true);
        if(is_array($json)){
            if(isset($json['post']) && is_string($json['post'])){
                return trim($json['post']);
            }
            // alle stringwaarden samenvoegen
            $buf = [];
            $it = new RecursiveIteratorIterator(new RecursiveArrayIterator($json));
            foreach($it as $v){ if(is_string($v)) $buf[] = $v; }
            if($buf) return trim(implode(' ', $buf));
            return ''; // geen bruikbare string
        }
    }
    return $trim;
}

/** Label voor taalcode */
function lf_view_label_lang($code){
    static $map = [
        'nl'=>'Nederlands','en'=>'English','de'=>'Deutsch','fr'=>'Français','es'=>'Español',
        'it'=>'Italiano','pt'=>'Português','pl'=>'Polski','sv'=>'Svenska','da'=>'Dansk','und'=>'?'
    ];
    $c = strtolower((string)$code);
    return $map[$c] ?? strtoupper($c ?: 'UND');
}

/** Relative time helper (simpel) */
function lf_view_timeago($time){
    if(empty($time)) return '';
    $ts = is_numeric($time) ? (int)$time : strtotime($time);
    if(!$ts) return '';
    $diff = time() - $ts;
    if($diff < 60)   return $diff . 's';
    if($diff < 3600) return floor($diff/60) . 'm';
    if($diff < 86400) return floor($diff/3600) . 'h';
    return date('Y-m-d H:i', $ts);
}

/** Haal taalcode voor badge:
 *  1) $post->data['lang']
 *  2) entity lookup: type=object, subtype=lang, owner_guid=$post->guid
 */
function lf_view_get_lang_for_badge($post){
    // 1) uit data[]
    if(isset($post->data) && is_array($post->data) && !empty($post->data['lang'])){
        return strtolower($post->data['lang']);
    }
    // 2) uit entities
    $ents = ossn_get_entities([
        'type'       => 'object',
        'subtype'    => 'lang',
        'owner_guid' => (int)$post->guid,
        'order_by'   => 'guid DESC',
        'limit'      => 1,
    ]);
    if($ents && isset($ents[0]->value) && $ents[0]->value !== ''){
        return strtolower($ents[0]->value);
    }
    return '';
}

/* -------- topbar filter -------- */

$posts  = $params['posts']  ?? [];
$filter = $params['filter'] ?? ['mode'=>'all','set'=>[]];

function lf_view_build_url($val){
    if($val === 'all' || $val === 'auto'){
        return ossn_site_url('languagefeed?lang='.$val);
    }
    if(is_array($val)) $val = implode(',', $val);
    return ossn_site_url('languagefeed?lang='.$val);
}

echo '<div class="languagefeed-bar">';
echo '  <div class="grp"><strong>'.ossn_print('languagefeed:choose').':</strong> ';
echo '    <a class="'.($filter['mode']==='all'?'active':'').'" href="'.lf_view_build_url('all').'">'.ossn_print('languagefeed:all').'</a>';
echo '    <a class="'.($filter['mode']==='auto'?'active':'').'" href="'.lf_view_build_url('auto').'">'.ossn_print('languagefeed:auto').'</a>';
echo '  </div>';

if($filter['mode']!=='all' && !empty($filter['set'])){
    echo '  <div class="grp">| ';
    foreach((array)$filter['set'] as $c){
        echo '<span>'.lf_view_label_lang($c).'</span> ';
    }
    echo '  </div>';
}
echo '  <div style="margin-left:auto"><a href="'.ossn_site_url('languagefeed_prefs').'">'.ossn_print('languagefeed:prefs').'</a></div>';
echo '</div>';

/* -------- no posts -------- */

if(!$posts){
    echo '<div class="ossn-no-posts">'.ossn_print('languagefeed:none').'</div>';
    return;
}

/* -------- list -------- */

foreach($posts as $o){

    // => Maak een kopie met opgeschoonde description (JSON -> tekst)
    $clean_text = isset($o->description) ? lf_view_extract_text($o->description) : '';
    $o_mod = clone $o;
    $o_mod->description = $clean_text;

    // 1) Probeer de standaard OssnWall item view (met likes/reacties etc.)
    $standard = ossn_plugin_view('components/OssnWall/templates/wall/item', ['post' => $o_mod]);
    if($standard){
        echo $standard;
        continue;
    }

    // 2) Fallback: compacte weergave (zoals je nu hebt)
    $owner = ossn_user_by_guid($o->owner_guid);
    $name  = $owner ? $owner->fullname : ('#'.$o->owner_guid);
    $user_url = $owner ? ossn_site_url("u/{$owner->username}") : '#';

    // Veilig tonen (links/linebreaks)
    $html = function_exists('ossn_view_text')
        ? ossn_view_text($clean_text)
        : nl2br(htmlentities($clean_text, ENT_QUOTES, 'UTF-8'));

    // Taalbadge: nu ook via entity
    $lang = lf_view_get_lang_for_badge($o);

    // Tijd
    $timeago = lf_view_timeago($o->time_created ?? $o->time ?? '');

    echo '<div class="ossn-wall-item" style="border:1px solid #eee;border-radius:6px;padding:10px 12px;margin-bottom:12px;background:#fff;">';

    // Header
    echo '  <div class="ossn-wall-item-meta" style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">';
    echo '    <a href="'.htmlentities($user_url).'" style="font-weight:bold;text-decoration:none;">'.htmlentities($name).'</a>';
    if($timeago){
        echo '    <span style="color:#777;">· '.$timeago.'</span>';
    }
    if($lang){
        echo '    <span style="margin-left:auto;font-size:12px;padding:2px 6px;border:1px solid #ddd;border-radius:10px;background:#fafafa;" title="Language">'.htmlentities(lf_view_label_lang($lang)).'</span>';
    }
    echo '  </div>';

    // Content
    echo '  <div class="ossn-wall-item-content" style="line-height:1.45;">'.$html.'</div>';

    echo '</div>';
}
