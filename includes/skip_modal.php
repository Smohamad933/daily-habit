<?php /* مودال «چرا انجام نشد؟» — خوراک تحلیل هوشمند عادت‌ها */ ?>
<div class="overlay" id="skipOverlay">
  <div class="modal">
    <h2><?= app_icon('info') ?> چرا این کار انجام نشد؟</h2>
    <p class="muted small">پاسخ شما به هوش مصنوعی کمک می‌کند عادت‌های بازدارنده را شناسایی کند و زمان بهتری برای انجام «<b id="skipTaskName"></b>» پیدا کند.</p>
    <form id="skipForm">
      <div class="reason-grid">
        <?php foreach (skip_categories() as $k => $c): ?>
        <label class="reason-opt"><input type="radio" name="category" value="<?= e($k) ?>"><?= e($c['label']) ?></label>
        <?php endforeach; ?>
      </div>
      <textarea id="skipNote" placeholder="توضیح بیشتر (اختیاری) — مثلاً: دوستم زنگ زد برای بیرون رفتن و قبول کردم…" style="min-height:70px"></textarea>
      <div class="inline-fields mt">
        <button class="btn block" type="submit">ثبت و زمان‌بندی مجدد هوشمند</button>
        <button class="btn ghost" type="button" onclick="document.getElementById('skipOverlay').classList.remove('show')">انصراف</button>
      </div>
    </form>
  </div>
</div>
