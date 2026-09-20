</main>
<footer class="site-footer">
  <div class="container"><?= nl2br(e(content('footer_text', ''))) ?> — نسخه <?= e(APP_VERSION) ?></div>
</footer>
<?php if ($cu = ($u ?? current_user())): ?>
<?php
// کلیدهای پوش را برای کاربر لاگین‌شده آماده کن (تا سابسکریپشن بتواند شکل بگیرد)
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
</body>
</html>
