<?php /** پاورقی — قرارداد ثابت:
 *  - اسکریپت‌ها: charts.js, app.js, push.js
 *  - window.APP = { api, sw, vapid, csrf, theme } فقط وقتی کاربر لاگین است
 */ ?>
</main>

<?php if ($cu = ($u ?? current_user())): ?>
<?php
// کلیدهای پوش برای کاربر لاگین‌شده (سابسکریپشن وب‌پوش)
$vapidPublic = setting('vapid_public');
if (!$vapidPublic) {
    $vk = vapid_keys();
    $vapidPublic = $vk['public'] ?? '';
}
?>
<div id="toastWrap" aria-live="polite"></div>
<script>
window.APP = {
  api: '<?= url("api.php") ?>',
  sw: '<?= url("sw.js") ?>',
  vapid: '<?= e($vapidPublic) ?>',
  csrf: '<?= e(csrf_token()) ?>',
  theme: '<?= e($theme ?? "auto") ?>'
};
</script>
<script src="<?= url('assets/js/charts.js') ?>"></script>
<script src="<?= url('assets/js/app.js') ?>"></script>
<script src="<?= url('assets/js/push.js') ?>"></script>
<?php endif; ?>

<footer class="site-footer">
  <div class="container"><?= nl2br(e(content('footer_text', ''))) ?></div>
</footer>
</body>
</html>
