<?php
/**
 * Preferences UI
 * - Post direct naar /languagefeed_prefs (geen action/CSRF benodigd)
 */
if(!ossn_isLoggedin()){
    echo 'Access denied';
    return;
}

$me        = ossn_loggedin_user();
$prefs     = languagefeed_get_prefs($me->guid);
$supported = LANGFEED_SUPPORTED;
?>
<div class="ossn-box">
  <div class="ossn-box-inner" style="padding:12px;">
    <h3><?= ossn_print('languagefeed:prefs:title'); ?></h3>
    <p><?= ossn_print('languagefeed:prefs:desc'); ?></p>

    <!-- Post naar dezelfde pagina om action/CSRF problemen te vermijden -->
    <form action="languagefeed_prefs" method="post" accept-charset="utf-8">
      <div style="margin:10px 0;">
        <label><strong><?= ossn_print('languagefeed:prefs:mode'); ?></strong></label><br>
        <label><input type="radio" name="mode" value="all"  <?= ($prefs['mode'] ?? 'auto')==='all'  ? 'checked' : '' ?>> <?= ossn_print('languagefeed:prefs:mode:all'); ?></label><br>
        <label><input type="radio" name="mode" value="auto" <?= ($prefs['mode'] ?? 'auto')==='auto' ? 'checked' : '' ?>> <?= ossn_print('languagefeed:prefs:mode:auto'); ?></label><br>
        <label><input type="radio" name="mode" value="langs" <?= ($prefs['mode'] ?? 'auto')==='langs' ? 'checked' : '' ?>> <?= ossn_print('languagefeed:prefs:mode:langs'); ?></label>
      </div>

      <div style="margin:10px 0;">
        <label><strong><?= ossn_print('languagefeed:prefs:langs'); ?></strong></label><br>
        <?php foreach($supported as $code): ?>
            <?php $checked = in_array($code, (array)($prefs['langs'] ?? []), true) ? 'checked' : ''; ?>
            <label style="margin-right:10px">
                <input type="checkbox" name="langs[]" value="<?= $code ?>" <?= $checked ?>> <?= strtoupper($code) ?>
            </label>
        <?php endforeach; ?>
      </div>

      <button type="submit" class="btn btn-primary"><?= ossn_print('languagefeed:prefs:save'); ?></button>
    </form>
  </div>
</div>
