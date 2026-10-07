<?php
/**
 * Landing page (always open) and the location check: Get Started → "Are you really
 * on Diva Junction?" → Checking… → success or "Oh no, Diva!". The browser only
 * reports its position; api/geofence/validate decides and grants the pass that
 * opens the deals page.
 */
require_once __DIR__ . '/inc/geofence.php';
$geo = geofence_visitor();
$cfg = $geo['cfg'];

// Get Started runs the check only while the lock is ON and this visitor has no pass yet.
$locked = $geo['enabled'] && !$geo['pass'];

// Admins can open any screen: index.php?screen=success
$screens = [
    'welcome' => 'Welcome', 'ask' => 'Question', 'checking' => 'Checking', 'success' => 'Success', 'outside' => 'Not there',
    'denied' => 'Location blocked', 'unavailable' => 'GPS off', 'timeout' => 'Timeout', 'accuracy' => 'Weak GPS',
    'rate' => 'Too many tries', 'error' => 'Error', 'insecure' => 'Not https', 'unsupported' => 'Old browser',
];
$screen  = $_GET['screen'] ?? '';
$preview = $geo['admin'] && is_string($screen) && isset($screens[$screen]) ? $screen : '';
$current = $preview ?: 'welcome';

$dealsLink = safe_link(setting('landing_cta_link'));
$fkLink    = safe_link(setting('flipkart_link'));
$failLink  = setting_raw('fail_cta_link') !== '' ? safe_link(setting_raw('fail_cta_link')) : geofence_directions_link($cfg);
$support   = setting_raw('support_link') !== '' ? safe_link(setting_raw('support_link')) : '';
$privacy   = setting_raw('geo_privacy');

/** target/rel attributes for outbound links */
function link_attrs(string $url): string
{
    return is_external($url) ? ' target="_blank" rel="noopener"' : '';
}

/** One "Oh no, Diva!" problem card: sign, pin, texts and the Try Again button. */
function problem_card(string $panel, string $current, string $line, string $big, string $message, bool $retry = true, string $extra = ''): string
{
    global $support;
    $html = '<section class="check-card is-result" data-panel="' . $panel . '" aria-labelledby="t-' . $panel . '"' . ($panel === $current ? '' : ' hidden') . '>'
        . '<img class="check-sign" src="' . e(asset(setting('fail_sign'))) . '" alt="Oh no, Diva!" width="556" height="329">'
        . pin_icon()
        . '<h2 class="check-lines" id="t-' . $panel . '" tabindex="-1"><span class="line-sm">' . e($line) . '</span><span class="line-xl">' . e($big) . '</span></h2>'
        . '<p class="check-msg">' . $message . '</p>' . $extra;
    if ($retry) {
        $html .= '<button type="button" class="pill-btn" data-action="retry">Try Again</button>';
        if ($support !== '') {
            $html .= '<a class="check-link" href="' . e($support) . '"' . link_attrs($support) . '>' . e(setting('support_text')) . '</a>';
        }
    }
    return $html . '</section>';
}

function pin_icon(): string
{
    return '<svg class="check-pin" viewBox="0 0 130 190" aria-hidden="true">'
        . '<ellipse cx="65" cy="174.5" rx="32.5" ry="12.5" fill="none" stroke="currentColor" stroke-width="4.5"/>'
        . '<path d="M65 170 14.9 104.2A63 63 0 1 1 115.1 104.2Z" fill="currentColor"/>'
        . '<circle cx="65" cy="66" r="44" fill="var(--card)"/>'
        . '<path d="m48 51.5 34 29m0-29-34 29" stroke="var(--magenta)" stroke-width="10" stroke-linecap="round"/>'
        . '</svg>';
}

$pageTitle = setting('site_title');
$bodyClass = 'landing';
$bodyStyle = "--landing-bg: url('" . asset(setting('landing_bg')) . "'); --ask-bg: url('" . asset(setting('ask_bg')) . "')";

require __DIR__ . '/inc/head.php';
?>
<div class="flow" data-flow data-screen="<?= e($current) ?>"<?= $locked ? ' data-locked' : '' ?>
     data-api="<?= e(base_url('api/geofence/validate')) ?>" data-accuracy-limit="<?= (int) $cfg['accuracy_limit_meters'] ?>">
    <div class="backdrop" aria-hidden="true"></div>
    <div class="backdrop backdrop-ask" aria-hidden="true"></div>

    <main class="stage">
        <img class="stage-art" src="<?= e(asset(setting('landing_bg'))) ?>" alt="" fetchpriority="high">
        <img class="stage-art art-ask" src="<?= e(asset(setting('ask_bg'))) ?>" alt="" decoding="async">

        <a class="corner-icon" href="<?= e($fkLink) ?>"<?= link_attrs($fkLink) ?>>
            <img src="<?= e(asset(setting('flipkart_icon'))) ?>" alt="Flipkart">
        </a>

        <!-- 1 · welcome -->
        <section class="scene scene-welcome" aria-labelledby="welcome-title">
            <h1 class="board-title" id="welcome-title">
                <span class="board-hello"><?= e(setting('landing_hello')) ?></span>
                <span class="board-welcome"><?= e(setting('landing_welcome')) ?></span>
                <img class="board-logo" src="<?= e(asset(setting('logo'))) ?>" alt="Diva Junction">
            </h1>
            <a class="landing-cta" href="<?= e($dealsLink) ?>"<?= $locked ? ' data-action="start"' : link_attrs($dealsLink) ?>>
                <?= e(setting('landing_cta_text')) ?>
            </a>
            <?php if ($locked): ?>
            <noscript><p class="ask-privacy">Please turn on JavaScript in your browser to check your location.</p></noscript>
            <?php endif; ?>
        </section>

        <!-- 2 · are you really on Diva Junction? (the browser asks for the location now) -->
        <section class="scene scene-ask" aria-labelledby="ask-title">
            <h2 class="ask-board" id="ask-title" tabindex="-1">
                <span class="ask-line"><?= e(setting('ask_line')) ?></span>
                <span class="ask-question"><?= e_lines(setting('ask_question')) ?></span>
            </h2>
            <?php if ($privacy !== ''): ?>
            <p class="ask-privacy"><?= e_lines($privacy) ?></p>
            <?php endif; ?>
        </section>
    </main>

    <?php if ($locked || $preview): ?>
    <!-- 3–5 · checking and results -->
    <div class="check" aria-live="polite">
        <a class="corner-icon" href="<?= e($fkLink) ?>"<?= link_attrs($fkLink) ?>>
            <img src="<?= e(asset(setting('flipkart_icon'))) ?>" alt="Flipkart">
        </a>

        <section class="check-card is-checking" data-panel="checking" aria-labelledby="t-checking"<?= $current === 'checking' ? '' : ' hidden' ?>>
            <svg class="spinner" viewBox="-110 -110 220 220" aria-hidden="true">
                <?php foreach (['#0039a0', '#0051c6', '#1566e9', '#2e7ae8', '#4994ef', '#5ca8f4', '#75bffc', '#8fcef9', '#a2dbf9', '#b6e6fc', '#caf1ff', '#dbf8fe'] as $i => $color): ?>
                <rect x="-10" y="-110" width="20" height="36" rx="10" fill="<?= $color ?>" transform="rotate(<?= -30 * $i ?>)"/>
                <?php endforeach; ?>
            </svg>
            <h2 class="check-status" id="t-checking" tabindex="-1"><?= e(setting('checking_text')) ?></h2>
        </section>

        <section class="check-card is-result" data-panel="success" aria-labelledby="t-success"<?= $current === 'success' ? '' : ' hidden' ?>>
            <img class="check-sign" src="<?= e(asset(setting('success_sign'))) ?>" alt="Yaaas, Diva!" width="556" height="346">
            <h2 class="check-lines is-pair" id="t-success" tabindex="-1">
                <span class="line-sm"><?= e(setting('success_line1')) ?></span>
                <span class="line-xl is-3d"><?= e(setting('success_big1')) ?></span>
            </h2>
            <p class="check-lines is-pair">
                <span class="line-sm"><?= e(setting('success_line2')) ?></span>
                <span class="line-xl is-3d"><?= e(setting('success_big2')) ?></span>
            </p>
            <p class="check-lines is-pair"><span class="line-sm"><?= e(setting('success_line3')) ?></span></p>
            <a class="pill-btn" href="<?= e($dealsLink) ?>"<?= link_attrs($dealsLink) ?>><?= e(setting('success_cta_text')) ?></a>
        </section>

        <section class="check-card is-result" data-panel="outside" aria-labelledby="t-outside"<?= $current === 'outside' ? '' : ' hidden' ?>>
            <img class="check-sign" src="<?= e(asset(setting('fail_sign'))) ?>" alt="Oh no, Diva!" width="556" height="329">
            <?= pin_icon() ?>
            <h2 class="check-lines" id="t-outside" tabindex="-1">
                <span class="line-sm"><?= e(setting('fail_line')) ?></span>
                <span class="line-xl"><?= e(setting('fail_big')) ?></span>
            </h2>
            <p class="check-msg is-big"><?= e_lines(setting('fail_message')) ?></p>
            <a class="pill-btn" href="<?= e($failLink) ?>"<?= link_attrs($failLink) ?>><?= e(setting('fail_cta_text')) ?></a>
            <button type="button" class="check-link" data-action="retry">I'm here — check again</button>
        </section>

        <?= problem_card('denied', $current, 'We need your', 'Location!',
            'Location is blocked for this site. Allow it, then tap Try Again.',
            true,
            '<ul class="check-steps">'
            . '<li><strong>Android / Chrome:</strong> tap the icon left of the address bar → Permissions → Location → Allow.</li>'
            . '<li><strong>iPhone / Safari:</strong> Settings → Privacy &amp; Security → Location Services → Safari Websites → While Using the App. Then tap <em>aA</em> in the address bar → Website Settings → Location → Allow.</li>'
            . '</ul>') ?>
        <?= problem_card('unavailable', $current, 'We can\'t find', 'Your Location!', 'Turn on Location (GPS) on your phone, then try again.') ?>
        <?= problem_card('timeout', $current, 'That took', 'Too Long!', 'Step into the open or near a window, check that Location is on, and try again.') ?>
        <?= problem_card('accuracy', $current, 'Your location is', 'Too Fuzzy!',
            'It\'s accurate to about <strong data-var="accuracy">250&nbsp;m</strong>; we need <strong data-var="limit">' . e(format_distance($cfg['accuracy_limit_meters'])) . '</strong> or better. Turn on <em>Precise location</em> and Wi-Fi, then try again.') ?>
        <?= problem_card('rate', $current, 'Too many tries,', 'Diva!', 'Please wait a few minutes, then try again.') ?>
        <?= problem_card('error', $current, 'Something went', 'Wrong!', 'We couldn\'t check your location just now. Check your connection and try again.') ?>
        <?= problem_card('insecure', $current, 'This page is', 'Not Secure!', 'Browsers share location only on secure pages. Please open the https:// address of this site.', false) ?>
        <?= problem_card('unsupported', $current, 'This browser can\'t', 'Find You!', 'Please open this page in Chrome or Safari on your phone.') ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($geo['admin']): ?>
<nav class="geo-preview" aria-label="Preview the screens (admin only)">
    <strong>Admin preview</strong> · lock <?= $geo['enabled'] ? 'ON' : 'OFF' ?> ·
    <?php foreach ($screens as $key => $label): ?>
    <a href="?screen=<?= $key ?>"<?= $key === $current ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
    <button type="button" class="geo-preview-close" aria-label="Hide" onclick="this.parentNode.remove()">×</button>
</nav>
<?php endif; ?>
<script src="<?= e(asset('assets/js/geofence.js')) ?>" defer></script>
</body>
</html>
