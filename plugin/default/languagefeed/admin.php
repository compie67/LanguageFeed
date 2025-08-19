<?php
if(!ossn_isAdminLoggedin()){
    echo 'Access denied';
    return;
}
?>
<div class="ossn-box">
  <div class="ossn-box-inner" style="padding:12px;">
    <h3><?= ossn_print('languagefeed:admin:retro:title'); ?></h3>
    <p><?= ossn_print('languagefeed:admin:retro:desc'); ?></p>

    <!-- Posten naar dezelfde adminpagina; geen action/CSRF gedoe -->
    <form action="languagefeed_admin" method="post">
     <label><strong><?= ossn_print('languagefeed:admin:limit'); ?></strong></label>
      <input type="number" name="limit" value="500" min="10" max="5000" style="width:140px;margin:0 8px;" />
      <button type="submit" class="btn btn-primary"><?= ossn_print('languagefeed:admin:run'); ?></button>
    </form>
  </div>
</div>
