<?php
/** @var array $c */
$site = $c['site'] ?? [];
$hero = $c['hero'] ?? [];
$adv = $c['advantages'] ?? [];
$services = $c['services'] ?? [];
$process = $c['process'] ?? [];
$stats = $c['stats'] ?? [];
$works = $c['works'] ?? [];
$reviews = $c['reviews'] ?? [];
$faq = $c['faq'] ?? [];
$contacts = $c['contacts'] ?? [];
$forms = $c['forms'] ?? [];
$footer = $c['footer'] ?? [];

$phone = $site['phone'] ?? '+375293355068';
$phoneD = $site['phone_display'] ?? '8 (029) 335-50-68';
$phone2 = $site['phone2'] ?? '';
$phone2D = $site['phone2_display'] ?? '';
$email = $site['email'] ?? '';
$company = $site['company'] ?? 'РостНержСталь';
$domain = rtrim($site['domain'] ?? 'https://steel-serves.by', '/');
$ogImage = $domain . '/' . ltrim($site['og_image'] ?? 'assets/img/og.jpg', '/');
$year = date('Y');

$cssVer = @filemtime(ROOT . '/assets/css/site.css') ?: 1;
$jsVer = @filemtime(ROOT . '/assets/js/site.js') ?: 1;
$visualVer = @filemtime(ROOT . '/admin/assets/visual.js') ?: 1;

$heroSlides = [
    [
        'src' => $hero['image'] ?? '',
        'alt' => $hero['image_alt'] ?? '',
        'caption' => $hero['image_caption'] ?? '????? 304',
        'path' => 'hero.image',
        'alt_path' => 'hero.image_alt',
        'caption_path' => 'hero.image_caption',
    ],
];
foreach (($hero['gallery'] ?? []) as $galleryIndex => $galleryItem) {
    if (!is_array($galleryItem)) {
        continue;
    }
    $src = trim((string)($galleryItem['src'] ?? ''));
    if ($src === '') {
        continue;
    }
    $heroSlides[] = [
        'src' => $src,
        'alt' => trim((string)($galleryItem['alt'] ?? '')),
        'caption' => trim((string)($galleryItem['caption'] ?? 'Монтаж')),
        'path' => 'hero.gallery.' . $galleryIndex . '.src',
        'alt_path' => 'hero.gallery.' . $galleryIndex . '.alt',
        'caption_path' => 'hero.gallery.' . $galleryIndex . '.caption',
    ];
}

$faqLd = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn($it) => [
        '@type' => 'Question',
        'name' => $it['q'] ?? '',
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $it['a'] ?? ''],
    ], $faq['items'] ?? []),
];
$bizLd = [
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => $company,
    'description' => $site['description'] ?? '',
    'url' => $domain . '/',
    'telephone' => $phone,
    'email' => $email,
    'image' => $ogImage,
    'priceRange' => 'по договору',
    'address' => [
        '@type' => 'PostalAddress',
        'addressCountry' => 'BY',
        'addressRegion' => 'Минская область',
        'addressLocality' => 'Минский район',
    ],
    'areaServed' => [
        ['@type' => 'Country', 'name' => 'Беларусь'],
    ],
    'hasOfferCatalog' => [
        '@type' => 'OfferCatalog',
        'name' => 'Изделия из нержавеющей стали',
        'itemListElement' => array_map(fn($s) => [
            '@type' => 'Offer',
            'itemOffered' => ['@type' => 'Service', 'name' => $s['name'] ?? ''],
        ], $services['items'] ?? []),
    ],
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($site['title'] ?? $company) ?></title>
    <meta name="description" content="<?= e($site['description'] ?? '') ?>">
    <link rel="canonical" href="<?= e($domain) ?>/">
    <meta name="theme-color" content="#0a0c0d">
    <meta name="format-detection" content="telephone=no">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:site_name" content="<?= e($company) ?>">
    <meta property="og:title" content="<?= e($site['title'] ?? $company) ?>">
    <meta property="og:description" content="<?= e($site['description'] ?? '') ?>">
    <meta property="og:url" content="<?= e($domain) ?>/">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($site['title'] ?? $company) ?>">
    <meta name="twitter:description" content="<?= e($site['description'] ?? '') ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">

    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="icon" href="favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <link rel="manifest" href="site.webmanifest">

    <?= preload_picture($hero['image'] ?? '', '(max-width: 900px) 94vw, 44vw') ?>
    <link rel="preload" href="assets/fonts/onest-300-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="assets/fonts/oswald-500-cyrillic.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="assets/css/fonts.css">
    <link rel="stylesheet" href="assets/css/site.css?v=<?= $cssVer ?>">

    <script type="application/ld+json"><?= json_encode($bizLd, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <script type="application/ld+json"><?= json_encode($faqLd, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</head>
<body>
<a class="skip-link" href="#main">Перейти к содержанию</a>

<header class="header" data-header>
    <div class="header__bar container">
        <a class="logo" href="#top">
            <img class="logo__img" src="assets/img/logo-wordmark.webp"
                 alt="<?= e($company) ?> — перила и ограждения из нержавеющей стали">
        </a>

        <nav class="nav" aria-label="Основная навигация">
            <ul class="nav__list">
                <?php foreach (($c['nav'] ?? []) as $ni => $item): ?>
                    <li><a class="nav__link" href="<?= e($item['href'] ?? '#') ?>"><span<?= bm('nav.' . $ni . '.label') ?>><?= e($item['label'] ?? '') ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="header__right">
            <a class="header__phone" href="tel:<?= e($phone) ?>">
                <span class="header__phone-label">Звоните</span>
                <span class="header__phone-value"><?= phone_typography($phoneD) ?></span>
            </a>
            <button class="btn btn--accent btn--sm header__cta" type="button" data-open-lead data-source="header">
                <span<?= bm('forms.callback_title') ?>><?= e($forms['callback_title'] ?? 'Заказать звонок') ?></span>
            </button>
            <button class="burger" type="button" aria-label="Открыть меню" aria-expanded="false" aria-controls="mobile-menu" data-burger>
                <span></span><span></span>
            </button>
        </div>
    </div>
    <div class="header__progress" aria-hidden="true"><span data-progress></span></div>
</header>

<dialog class="mobile-menu" id="mobile-menu" aria-label="Меню">
    <div class="mobile-menu__top container">
        <a class="logo logo--menu" href="#top" data-menu-link>
            <img class="logo__img" src="assets/img/logo-wordmark.webp" alt="<?= e($company) ?>">
        </a>
        <button class="burger burger--close" type="button" aria-label="Закрыть меню" data-menu-close>
            <span></span><span></span>
        </button>
    </div>
    <nav class="mobile-menu__nav container" aria-label="Мобильная навигация">
        <?php foreach (($c['nav'] ?? []) as $i => $item): ?>
            <a class="mobile-menu__link" href="<?= e($item['href'] ?? '#') ?>" data-menu-link>
                <span class="mobile-menu__num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <span<?= bm('nav.' . $i . '.label') ?>><?= e($item['label'] ?? '') ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="mobile-menu__bottom container">
        <a class="mobile-menu__phone" href="tel:<?= e($phone) ?>"><?= phone_typography($phoneD) ?></a>
        <button class="btn btn--accent" type="button" data-open-lead data-source="menu"><?= e($forms['callback_title'] ?? 'Заказать звонок') ?></button>
        <p class="mobile-menu__note"><span<?= bm('site.geo') ?>><?= e($site['geo'] ?? '') ?></span> · <span<?= bm('site.email') ?>><?= e($site['email'] ?? '') ?></span></p>
    </div>
</dialog>

<main id="main">

    <section class="hero" id="top">
        <span class="hero__watermark" aria-hidden="true">304</span>
        <div class="container hero__grid">
            <div class="hero__content">
                <p class="kicker hero__kicker" data-reveal<?= bm('hero.kicker') ?>><?= e($hero['kicker'] ?? '') ?></p>
                <h1 class="hero__title">
                    <?php
                    $lines = [
                        [$hero['title_top'] ?? '', '', 'hero.title_top'],
                        [$hero['title_mid'] ?? '', '', 'hero.title_mid'],
                        [$hero['title_bottom'] ?? '', 'is-outline', 'hero.title_bottom'],
                    ];
                    foreach ($lines as $i => $line):
                        if ($line[0] === '') continue;
                        ?>
                        <span class="hero__line <?= $line[1] ?>" style="--line-delay: <?= 0.12 + $i * 0.12 ?>s">
                            <span<?= bm($line[2]) ?>><?= e($line[0]) ?></span>
                        </span>
                    <?php endforeach; ?>
                </h1>
                <p class="hero__lead" data-reveal style="--d: .35s"<?= bm('hero.lead') ?>><?= e($hero['lead'] ?? '') ?></p>
                <div class="hero__actions" data-reveal style="--d: .45s">
                    <button class="btn btn--accent" type="button" data-open-lead data-source="hero">
                        <span<?= bm('hero.cta') ?>><?= e($hero['cta'] ?? 'Рассчитать стоимость') ?></span>
                        <?= icon('arrow', 'btn__ico') ?>
                    </button>
                    <a class="btn btn--ghost" href="tel:<?= e($phone) ?>">
                        <?= icon('phone', 'btn__ico') ?>
                        <?= phone_typography($phoneD) ?>
                    </a>
                </div>
                <p class="hero__note" data-reveal style="--d: .5s"<?= bm('hero.cta_note') ?>><?= e($hero['cta_note'] ?? '') ?></p>
                <ul class="hero__badges" data-reveal style="--d: .55s">
                    <?php foreach (($hero['badges'] ?? []) as $bi => $b): ?>
                        <li><?= icon('check', 'ico') ?><span<?= bm('hero.badges.' . $bi) ?>><?= e($b) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <figure class="hero__media" data-reveal style="--d: .2s">
                <div class="hero__frame hero__carousel" data-carousel role="region" aria-roledescription="carousel" aria-label="Фото монтажей ограждений">
                    <div class="hero__slides" aria-live="polite" aria-atomic="true">
                        <?php foreach ($heroSlides as $slideIndex => $slide): ?>
                            <div class="hero__slide<?= $slideIndex === 0 ? ' is-active' : '' ?>" data-slide role="group" aria-roledescription="Слайд" aria-label="Слайд <?= $slideIndex + 1 ?> из <?= count($heroSlides) ?>" aria-hidden="<?= $slideIndex === 0 ? 'false' : 'true' ?>">
                                <?= picture($slide['src'], $slide['alt'], '(max-width: 900px) 94vw, 44vw', [
                                    'class' => 'hero__img',
                                    'loading' => $slideIndex === 0 ? 'eager' : 'lazy',
                                    'fetchpriority' => $slideIndex === 0 ? 'high' : 'auto',
                                    'edit' => $slide['path'],
                                    'edit_alt' => $slide['alt_path'],
                                ]) ?>
                                <span class="hero__caption"<?= bm($slide['caption_path']) ?>><?= e($slide['caption']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <span class="hero__frame-line" aria-hidden="true"></span>
                    <?php if (count($heroSlides) > 1): ?>
                        <div class="hero__carousel-ui">
                            <button class="hero__carousel-btn hero__carousel-btn--prev" type="button" data-carousel-prev aria-label="Предыдущее фото">
                                <?= icon('arrow', 'ico') ?>
                            </button>
                            <div class="hero__dots" aria-label="Выбор фото">
                                <?php foreach ($heroSlides as $dotIndex => $dot): ?>
                                    <button class="hero__dot<?= $dotIndex === 0 ? ' is-active' : '' ?>" type="button" data-carousel-go="<?= $dotIndex ?>" aria-label="Показать фото <?= $dotIndex + 1 ?> из <?= count($heroSlides) ?>" aria-current="<?= $dotIndex === 0 ? 'true' : 'false' ?>"></button>
                                <?php endforeach; ?>
                            </div>
                            <button class="hero__carousel-btn hero__carousel-btn--next" type="button" data-carousel-next aria-label="Следующее фото">
                                <?= icon('arrow', 'ico') ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="hero__specs">
                    <?php foreach (($hero['specs'] ?? []) as $si => $spec): ?>
                        <div class="hero__spec">
                            <span<?= bm('hero.specs.' . $si . '.0') ?>><?= e($spec[0] ?? '') ?></span>
                            <b<?= bm('hero.specs.' . $si . '.1') ?>><?= e($spec[1] ?? '') ?></b>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a class="scroll-cue" href="#advantages"><span aria-hidden="true"></span>Листайте</a>
            </figure>
        </div>

        <div class="ticker" aria-hidden="true">
            <div class="ticker__track">
                <?php for ($r = 0; $r < 2; $r++): ?>
                    <span class="ticker__group">
                        <?php for ($k = 0; $k < 3; $k++): ?>
                            <span<?= bm('hero.ticker') ?>><?= e($hero['ticker'] ?? '????? 304') ?></span>
                        <?php endfor; ?>
                    </span>
                <?php endfor; ?>
            </div>
        </div>
    </section>

    <section class="section advantages" id="advantages">
        <div class="container">
            <div class="section-head">
                <span class="section-index" data-reveal>01 / Преимущества</span>
                <h2 class="section-title" data-reveal<?= bm('advantages.title') ?>><?= e($adv['title'] ?? '') ?></h2>
                <p class="section-sub" data-reveal style="--d:.1s"<?= bm('advantages.subtitle') ?>><?= e($adv['subtitle'] ?? '') ?></p>
            </div>
            <div class="adv-grid">
                <?php foreach (($adv['items'] ?? []) as $i => $item): ?>
                    <article class="adv-card<?= ($i === 0 || $i === 5) ? ' adv-card--wide' : '' ?>" data-reveal style="--d: <?= 0.05 * $i ?>s">
                        <span class="adv-card__num" aria-hidden="true"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <span class="adv-card__icon"><?= icon($item['icon'] ?? 'check') ?></span>
                        <h3 class="adv-card__title"<?= bm('advantages.items.' . $i . '.title') ?>><?= e($item['title'] ?? '') ?></h3>
                        <p class="adv-card__text"<?= bm('advantages.items.' . $i . '.text') ?>><?= e($item['text'] ?? '') ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section services" id="services">
        <div class="container">
            <div class="section-head section-head--split">
                <div>
                    <span class="section-index" data-reveal>02 / Услуги</span>
                    <h2 class="section-title" data-reveal<?= bm('services.title') ?>><?= e($services['title'] ?? '') ?></h2>
                </div>
                <p class="section-sub" data-reveal style="--d:.1s"<?= bm('services.subtitle') ?>><?= e($services['subtitle'] ?? '') ?></p>
            </div>

            <div class="services__layout" data-services>
                <div class="services__list" role="tablist" aria-orientation="vertical" aria-label="Типы конструкций">
                    <?php foreach (($services['items'] ?? []) as $i => $s): ?>
                        <button class="svc-tab<?= $i === 0 ? ' is-active' : '' ?>" type="button" role="tab"
                                id="tab-<?= e($s['id'] ?? $i) ?>" aria-controls="panel-<?= e($s['id'] ?? $i) ?>"
                                aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>"
                                data-tab="<?= $i ?>" data-scroll>
                            <span class="svc-tab__num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="svc-tab__name"><?= e($s['name'] ?? '') ?></span>
                            <span class="svc-tab__count"><?= count($s['photos'] ?? []) ?> фото</span>
                            <?= icon('arrow', 'svc-tab__arrow') ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="services__panels">
                    <?php foreach (($services['items'] ?? []) as $i => $s):
                        $cover = $s['cover_img'] ?? '';
                        [$cw, $ch] = img_meta($cover);
                        $base = preg_replace('/\.(webp|jpg|jpeg|png)$/i', '', $cover);
                        $set = [];
                        foreach ([400, 640, 960] as $w) {
                            if (is_file(img_path($base . '-' . $w . '.webp'))) {
                                $set[] = $base . '-' . $w . '.webp ' . $w . 'w';
                            }
                        }
                        if (is_file(img_path($cover))) {
                            $set[] = $cover . ' ' . $cw . 'w';
                        }
                        $mainSet = implode(', ', $set);
                        ?>
                        <div class="service-panel<?= $i === 0 ? ' is-active' : '' ?>" id="panel-<?= e($s['id'] ?? $i) ?>"
                             role="tabpanel" aria-labelledby="tab-<?= e($s['id'] ?? $i) ?>" data-panel="<?= $i ?>"<?= $i === 0 ? '' : ' hidden' ?>>
                            <div class="service-panel__media">
                                <div class="service-panel__main">
                                    <img class="service-panel__img" data-main-img
                                         src="<?= e($cover) ?>" srcset="<?= e($mainSet) ?>"
                                         sizes="(max-width: 900px) 94vw, 620px"
                                         width="<?= $cw ?>" height="<?= $ch ?>"
                                         alt="<?= e($s['alt'] ?? '') ?>" loading="lazy" decoding="async"
                                         <?= bm_img('services.items.' . $i . '.photos.0.src', 'services.items.' . $i . '.photos.0.alt') ?>>
                                    <span class="service-panel__badge"<?= bm('services.items.' . $i . '.badge') ?>><?= e($s['badge'] ?? '') ?></span>
                                    <button class="zoom-btn" type="button" aria-label="Открыть фото"
                                            data-lightbox-group="service-<?= e($s['id'] ?? $i) ?>"
                                            data-lightbox-src="<?= e($cover) ?>" data-lightbox-alt="<?= e($s['alt'] ?? '') ?>">
                                        <?= icon('plus', 'ico') ?>
                                    </button>
                                </div>
                                <div class="service-panel__thumbs">
                                    <?php foreach (($s['photos'] ?? []) as $pi => $photo):
                                        $p = $photo['src'] ?? '';
                                        $pbase = preg_replace('/\.(webp|jpg|jpeg|png)$/i', '', $p);
                                        $thumb = $pbase . '-400.webp';
                                        if (!is_file(img_path($thumb))) {
                                            $thumb = $p;
                                        }
                                        [$tw, $th] = img_meta($thumb);
                                        $pset = [];
                                        foreach ([400, 640] as $w) {
                                            if (is_file(img_path($pbase . '-' . $w . '.webp'))) {
                                                $pset[] = $pbase . '-' . $w . '.webp ' . $w . 'w';
                                            }
                                        }
                                        [$fw, $fh] = img_meta($p);
                                        $pset[] = $p . ' ' . $fw . 'w';
                                        ?>
                                        <button class="thumb<?= $p === $cover ? ' is-active' : '' ?>" type="button"
                                                data-thumb data-src="<?= e($p) ?>" data-srcset="<?= e(implode(', ', $pset)) ?>"
                                                data-alt="<?= e($photo['alt'] ?? '') ?>"
                                                aria-label="Показать фото <?= $pi + 1 ?>">
                                            <img src="<?= e($thumb) ?>" srcset="<?= e(implode(', ', $pset)) ?>"
                                                 sizes="96px" width="<?= $tw ?>" height="<?= $th ?>"
                                                 alt="" loading="lazy" decoding="async">
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="service-panel__info">
                                <h3 class="service-panel__title"<?= bm('services.items.' . $i . '.name') ?>><?= e($s['name'] ?? '') ?></h3>
                                <p class="service-panel__desc"<?= bm('services.items.' . $i . '.desc') ?>><?= e($s['desc'] ?? '') ?></p>
                                <dl class="specs">
                                    <?php foreach (($s['specs'] ?? []) as $si => $spec): ?>
                                        <div class="specs__row">
                                            <dt<?= bm('services.items.' . $i . '.specs.' . $si . '.0') ?>><?= e($spec[0] ?? '') ?></dt>
                                            <dd<?= bm('services.items.' . $i . '.specs.' . $si . '.1') ?>><?= e($spec[1] ?? '') ?></dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                                <div class="service-panel__actions">
                                    <button class="btn btn--accent" type="button" data-open-lead data-source="service-<?= e($s['id'] ?? $i) ?>">
                                        <span<?= bm('services.items.' . $i . '.cta') ?>><?= e($s['cta'] ?? 'Рассчитать') ?></span><?= icon('arrow', 'btn__ico') ?>
                                    </button>
                                    <span class="service-panel__note">Замер и расчёт — бесплатно</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="section section--light process" id="process">
        <div class="container process__grid">
            <div class="process__head">
                <span class="section-index" data-reveal>03 / Как работаем</span>
                <h2 class="section-title" data-reveal<?= bm('process.title') ?>><?= e($process['title'] ?? '') ?></h2>
                <p class="section-sub" data-reveal<?= bm('process.subtitle') ?>><?= e($process['subtitle'] ?? '') ?></p>
                <button class="btn btn--dark" type="button" data-open-lead data-source="process" data-reveal>
                    Оставить заявку<?= icon('arrow', 'btn__ico') ?>
                </button>
            </div>
            <ol class="process__steps" data-steps>
                <?php foreach (($process['steps'] ?? []) as $i => $step): ?>
                    <li class="step" data-step>
                        <span class="step__num" aria-hidden="true"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <div class="step__body">
                            <h3 class="step__title"<?= bm('process.steps.' . $i . '.title') ?>><?= e($step['title'] ?? '') ?></h3>
                            <p class="step__text"<?= bm('process.steps.' . $i . '.text') ?>><?= e($step['text'] ?? '') ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <section class="section section--light stats" id="stats">
        <div class="container">
            <div class="stats__grid">
                <?php foreach ($stats as $i => $st): ?>
                    <div class="stat" data-reveal style="--d: <?= 0.08 * $i ?>s">
                        <div class="stat__value">
                            <span data-count="<?= (int)($st['value'] ?? 0) ?>"<?= bm('stats.' . $i . '.value', 'number') ?>><?= (int)($st['value'] ?? 0) ?></span><em<?= bm('stats.' . $i . '.suffix') ?>><?= e($st['suffix'] ?? '') ?></em>
                        </div>
                        <div class="stat__label"<?= bm('stats.' . $i . '.label') ?>><?= e($st['label'] ?? '') ?></div>
                        <p class="stat__text"<?= bm('stats.' . $i . '.text') ?>><?= e($st['text'] ?? '') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section works" id="works">
        <div class="container">
            <div class="section-head section-head--split">
                <div>
                    <span class="section-index" data-reveal>04 / Портфолио</span>
                    <h2 class="section-title" data-reveal<?= bm('works.title') ?>><?= e($works['title'] ?? '') ?></h2>
                </div>
                <?php if (($works['subtitle'] ?? '') !== ''): ?>
                    <p class="section-sub" data-reveal style="--d:.1s"<?= bm('works.subtitle') ?>><?= e($works['subtitle'] ?? '') ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="works__scroller" data-scroller>
            <div class="works__track">
                <?php foreach (($works['items'] ?? []) as $i => $w): ?>
                    <figure class="work" data-reveal style="--d: <?= min($i, 6) * 0.04 ?>s">
                        <button class="work__media" type="button"
                                data-lightbox-group="works" data-lightbox-src="<?= e($w['src'] ?? '') ?>"
                                data-lightbox-alt="<?= e($w['alt'] ?? '') ?>"
                                aria-label="Открыть фото: <?= e($w['caption'] ?? '') ?>">
                            <?= picture($w['src'] ?? '', $w['alt'] ?? '', '(max-width: 700px) 78vw, 420px', [
                                'edit' => 'works.items.' . $i . '.src',
                                'edit_alt' => 'works.items.' . $i . '.alt',
                            ]) ?>
                            <span class="work__plus" aria-hidden="true"><?= icon('plus', 'ico') ?></span>
                        </button>
                        <figcaption class="work__caption">
                            <span class="work__num"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <span<?= bm('works.items.' . $i . '.caption') ?>><?= e($w['caption'] ?? '') ?></span>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="container works__footer">
            <div class="works__controls">
                <button class="round-btn" type="button" aria-label="Предыдущие работы" data-scroll-prev><?= icon('arrow', 'ico ico--left') ?></button>
                <button class="round-btn" type="button" aria-label="Следующие работы" data-scroll-next><?= icon('arrow', 'ico') ?></button>
            </div>
            <button class="btn btn--ghost" type="button" data-open-lead data-source="works">Хочу так же<?= icon('arrow', 'btn__ico') ?></button>
        </div>
    </section>

    <section class="section section--light reviews" id="reviews">
        <div class="container">
            <div class="section-head">
                <span class="section-index" data-reveal>05 / Отзывы</span>
                <h2 class="section-title" data-reveal<?= bm('reviews.title') ?>><?= e($reviews['title'] ?? '') ?></h2>
                <p class="section-sub" data-reveal<?= bm('reviews.subtitle') ?>><?= e($reviews['subtitle'] ?? '') ?></p>
            </div>
            <div class="reviews__grid">
                <?php foreach (($reviews['items'] ?? []) as $i => $r): ?>
                    <article class="review" data-reveal style="--d: <?= 0.1 * $i ?>s">
                        <span class="review__quote" aria-hidden="true">“</span>
                        <blockquote class="review__text"<?= bm('reviews.items.' . $i . '.text') ?>><?= e($r['text'] ?? '') ?></blockquote>
                        <div class="review__foot">
                            <div class="review__person">
                                <span class="review__avatar" aria-hidden="true"><?= e(mb_substr($r['name'] ?? '', 0, 1)) ?></span>
                                <div>
                                    <div class="review__name"<?= bm('reviews.items.' . $i . '.name') ?>><?= e($r['name'] ?? '') ?></div>
                                    <div class="review__role"<?= bm('reviews.items.' . $i . '.role') ?>><?= e($r['role'] ?? '') ?></div>
                                </div>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section section--light faq" id="faq">
        <div class="container faq__grid">
            <div class="faq__head">
                <span class="section-index" data-reveal>06 / Вопросы</span>
                <h2 class="section-title" data-reveal<?= bm('faq.title') ?>><?= e($faq['title'] ?? '') ?></h2>
                <p class="section-sub" data-reveal>Не нашли ответ? Позвоните — разберёмся вместе.</p>
                <a class="link-arrow" href="tel:<?= e($phone) ?>"><?= phone_typography($phoneD) ?><?= icon('arrow', 'ico') ?></a>
            </div>
            <div class="faq__list" data-accordion>
                <?php foreach (($faq['items'] ?? []) as $i => $item): ?>
                    <div class="acc" data-acc>
                        <h3 class="acc__heading">
                            <button class="acc__btn" type="button" id="faq-btn-<?= $i ?>" aria-expanded="false" aria-controls="faq-panel-<?= $i ?>">
                                <span<?= bm('faq.items.' . $i . '.q') ?>><?= e($item['q'] ?? '') ?></span>
                                <span class="acc__icon" aria-hidden="true"><?= icon('plus', 'ico') ?></span>
                            </button>
                        </h3>
                        <div class="acc__panel" id="faq-panel-<?= $i ?>" role="region" aria-labelledby="faq-btn-<?= $i ?>" hidden>
                            <div class="acc__inner"><p<?= bm('faq.items.' . $i . '.a') ?>><?= e($item['a'] ?? '') ?></p></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="section contacts" id="contacts">
        <div class="container contacts__grid">
            <div class="contacts__info">
                <span class="section-index" data-reveal>07 / Контакты</span>
                <h2 class="section-title" data-reveal<?= bm('contacts.title') ?>><?= e($contacts['title'] ?? '') ?></h2>
                <p class="section-sub" data-reveal<?= bm('contacts.text') ?>><?= e($contacts['text'] ?? '') ?></p>

                <dl class="contacts__list" data-reveal>
                    <div class="contacts__row">
                        <dt>Телефон</dt>
                        <dd>
                            <a class="contacts__phone" href="tel:<?= e($phone) ?>"><?= phone_typography($phoneD) ?></a>
                            <?php if ($phone2 !== ''): ?>
                                <a class="contacts__phone contacts__phone--second" href="tel:<?= e($phone2) ?>"><?= phone_typography($phone2D) ?></a>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div class="contacts__row">
                        <dt>Почта</dt>
                        <dd><a class="link-line" href="mailto:<?= e($email) ?>"><span<?= bm('site.email') ?>><?= e($email) ?></span></a></dd>
                    </div>
                    <div class="contacts__row">
                        <dt>География</dt>
                        <dd<?= bm('site.geo') ?>><?= e($site['geo'] ?? '') ?></dd>
                    </div>
                    <div class="contacts__row">
                        <dt>Доставка</dt>
                        <dd<?= bm('site.delivery') ?>><?= e($site['delivery'] ?? '') ?></dd>
                    </div>
                </dl>
            </div>

            <div class="contacts__form" data-reveal>
                <?= lead_form('contacts', 'contacts', $contacts['form_title'] ?? 'Рассчитать стоимость', $contacts['form_text'] ?? '', '', 'contacts.form') ?>
            </div>
        </div>
    </section>
</main>

<footer class="footer">
    <div class="container footer__grid">
        <div class="footer__brand">
            <a class="logo logo--footer" href="#top">
                <img class="logo__img logo__img--footer" src="assets/img/logo-full.webp"
                     alt="<?= e($company) ?> — перила и ограждения из нержавеющей стали">
            </a>
            <p class="footer__tagline"<?= bm('footer.tagline') ?>><?= e($footer['tagline'] ?? '') ?></p>
        </div>

        <nav class="footer__col" aria-label="Разделы сайта">
            <h3 class="footer__title"<?= bm('footer.nav_title') ?>><?= e($footer['nav_title'] ?? 'Разделы') ?></h3>
            <ul class="footer__list">
                <?php foreach (($c['nav'] ?? []) as $ni => $item): ?>
                    <li><a href="<?= e($item['href'] ?? '#') ?>"><span<?= bm('nav.' . $ni . '.label') ?>><?= e($item['label'] ?? '') ?></span></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="footer__col">
            <h3 class="footer__title"<?= bm('footer.contacts_title') ?>><?= e($footer['contacts_title'] ?? 'Контакты') ?></h3>
            <ul class="footer__list">
                <li><a href="tel:<?= e($phone) ?>"><?= phone_typography($phoneD) ?></a></li>
                <?php if ($phone2 !== ''): ?><li><a href="tel:<?= e($phone2) ?>"><?= phone_typography($phone2D) ?></a></li><?php endif; ?>
                <li><a href="mailto:<?= e($email) ?>"><span<?= bm('site.email') ?>><?= e($email) ?></span></a></li>
                <li<?= bm('site.geo') ?>><?= e($site['geo'] ?? '') ?></li>
            </ul>
        </div>

        <div class="footer__col">
            <h3 class="footer__title"<?= bm('footer.legal_title') ?>><?= e($footer['legal_title'] ?? 'Документы') ?></h3>
            <ul class="footer__list">
                <li><button class="link-btn" type="button" data-open-privacy>Политика конфиденциальности</button></li>
                <li><a href="assets/docs/registration-certificate.pdf" target="_blank" rel="noopener">Свидетельство о регистрации</a></li>
                <li><button class="link-btn" type="button" data-open-lead data-source="footer">Заказать звонок</button></li>
            </ul>
        </div>
    </div>

    <div class="container footer__bottom">
        <span<?= bm('site.copyright') ?>><?= e($site['copyright'] ?? '© ' . $year . ' ' . $company) ?></span>
    </div>
</footer>

<div class="mobile-bar" role="group" aria-label="Быстрые действия">
    <a class="mobile-bar__btn" href="tel:<?= e($phone) ?>"><?= icon('phone', 'ico') ?><span>Позвонить</span></a>
    <button class="mobile-bar__btn mobile-bar__btn--accent" type="button" data-open-lead data-source="mobile-bar">
        <?= icon('arrow', 'ico') ?><span>Оставить заявку</span>
    </button>
</div>

<dialog class="modal" id="lead-modal" aria-labelledby="lead-modal-title">
    <div class="modal__inner">
        <button class="modal__close" type="button" aria-label="Закрыть" data-close><?= icon('close', 'ico') ?></button>
        <div id="lead-modal-title" class="sr-only"><?= e($forms['callback_title'] ?? 'Заказать звонок') ?></div>
        <?= lead_form('modal', 'modal', $forms['callback_title'] ?? 'Заказать звонок', $forms['callback_text'] ?? '', '', 'forms.callback') ?>
    </div>
</dialog>

<dialog class="modal modal--privacy" id="privacy-modal" aria-labelledby="privacy-title">
    <div class="modal__inner">
        <button class="modal__close" type="button" aria-label="Закрыть" data-close><?= icon('close', 'ico') ?></button>
        <h2 class="modal__title" id="privacy-title"><?= e($forms['privacy_title'] ?? 'Политика конфиденциальности') ?></h2>
        <div class="prose"><?= $c['privacy_html'] ?? '' ?></div>
    </div>
</dialog>

<dialog class="lightbox" id="lightbox" aria-label="Просмотр фотографии">
    <button class="lightbox__close" type="button" aria-label="Закрыть" data-lightbox-close><?= icon('close', 'ico') ?></button>
    <button class="lightbox__nav lightbox__nav--prev" type="button" aria-label="Предыдущее фото" data-lightbox-prev><?= icon('arrow', 'ico ico--left') ?></button>
    <figure class="lightbox__figure">
        <img class="lightbox__img" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="" data-lightbox-img>
        <figcaption class="lightbox__caption" data-lightbox-caption></figcaption>
    </figure>
    <button class="lightbox__nav lightbox__nav--next" type="button" aria-label="Следующее фото" data-lightbox-next><?= icon('arrow', 'ico') ?></button>
    <span class="lightbox__counter" data-lightbox-counter></span>
</dialog>

<script src="assets/js/site.js?v=<?= $jsVer ?>" defer></script>
<?php if (defined('BM_EDIT')): ?>
    <script src="admin/assets/visual.js?v=<?= $visualVer ?>" defer></script>
<?php endif; ?>
</body>
</html>
