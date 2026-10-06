<?php
/**
 * Every editable site setting, grouped for the admin "Site content" page.
 * type: text | textarea | url | image | datetime | number
 */

function settings_schema(): array
{
    return [
        'landing' => [
            'title'  => 'Landing page',
            'fields' => [
                'landing_welcome'  => ['label' => 'Welcome line', 'type' => 'text', 'default' => 'Welcome to'],
                'landing_tagline'  => ['label' => 'Tagline', 'type' => 'textarea', 'default' => "Yaha rukengi trainein,\nkhulengi Fashion & Beauty\nki dealein!", 'help' => 'Each line break is kept.'],
                'landing_cta_text' => ['label' => 'Button text', 'type' => 'text', 'default' => 'Get Started'],
                'landing_cta_link' => ['label' => 'Button link', 'type' => 'url', 'default' => 'shop.php', 'help' => 'Use shop.php for the product page, or any full URL.'],
                'landing_bg'       => ['label' => 'Background artwork (1080 × 1920)', 'type' => 'image', 'default' => 'assets/img/landing-bg.webp', 'help' => 'Station scene with an empty signboard. Text, logo and button are placed on top.'],
            ],
        ],
        'hero' => [
            'title'  => 'Product page — hero',
            'fields' => [
                'hero_heading'   => ['label' => 'Heading (blue)', 'type' => 'textarea', 'default' => "There's\nsomething"],
                'hero_highlight' => ['label' => 'Heading (pink, italic)', 'type' => 'textarea', 'default' => "for every\ndiva!"],
                'hero_cta_text'  => ['label' => 'Button text', 'type' => 'text', 'default' => 'Hop on!'],
                'hero_cta_link'  => ['label' => 'Button link', 'type' => 'url', 'default' => '#featured'],
                'hero_image'     => ['label' => 'Hero card artwork (853 × 450)', 'type' => 'image', 'default' => 'assets/img/hero-card.webp', 'help' => 'Keep the left side empty for the heading.'],
            ],
        ],
        'sections' => [
            'title'  => 'Product page — sections',
            'fields' => [
                'featured_title'  => ['label' => 'Featured band title', 'type' => 'text', 'default' => 'FEATURED'],
                'brands_title'    => ['label' => 'Brands section title', 'type' => 'text', 'default' => 'Shop by Brands'],
                'countdown_label' => ['label' => 'Countdown label', 'type' => 'text', 'default' => 'Next deals in...'],
                'countdown_end'   => ['label' => 'Next deals start at', 'type' => 'datetime', 'default' => ''],
                'countdown_repeat_hours' => ['label' => 'Repeat every (hours)', 'type' => 'number', 'default' => '3', 'help' => 'After the countdown ends it restarts for this many hours. 0 = stop at 00:00.'],
            ],
        ],
        'brand' => [
            'title'  => 'Branding',
            'fields' => [
                'site_title'    => ['label' => 'Browser title', 'type' => 'text', 'default' => 'Diva Junction | The Big Billion Days'],
                'logo'          => ['label' => 'Diva Junction logo', 'type' => 'image', 'default' => 'assets/img/logo.png', 'help' => 'Transparent PNG, square.'],
                'flipkart_icon' => ['label' => 'Corner icon', 'type' => 'image', 'default' => 'assets/img/flipkart.png'],
                'flipkart_link' => ['label' => 'Corner icon link', 'type' => 'url', 'default' => 'https://www.flipkart.com/'],
            ],
        ],
    ];
}

function settings_field(string $key): ?array
{
    foreach (settings_schema() as $group) {
        if (isset($group['fields'][$key])) {
            return $group['fields'][$key];
        }
    }
    return null;
}
