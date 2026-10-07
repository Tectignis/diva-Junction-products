<?php if (!empty($GLOBALS['geo_admin_preview'])): ?>
<p class="geo-preview" role="note">
    Location lock is <strong>ON</strong>. You see this page because you're signed in as admin —
    visitors outside the area get the location screen. <a href="<?= e(base_url('admin/location.php')) ?>">Location settings</a>
</p>
<?php endif; ?>
