<?php
/**
 * Every editable site setting, grouped for the admin "Site content" page.
 * type: text | textarea | url | image | datetime | number | bool
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
                'landing_cta_link' => ['label' => 'Button link', 'type' => 'url', 'default' => 'shop.php', 'help' => 'Use shop.php for the deals page, or any full URL.'],
                'landing_bg'       => ['label' => 'Background artwork (1080 × 1920)', 'type' => 'image', 'default' => 'assets/img/landing-bg.webp', 'help' => 'Station scene with an empty signboard. Text, logo and button are placed on top.'],
            ],
        ],
        'hero' => [
            'title'  => 'Deals page — header',
            'fields' => [
                'hero_heading'   => ['label' => 'Heading (blue)', 'type' => 'textarea', 'default' => "There's\nsomething"],
                'hero_highlight' => ['label' => 'Heading (pink, italic)', 'type' => 'textarea', 'default' => "for every\ndiva!"],
                'hero_cta_text'  => ['label' => 'Button text', 'type' => 'text', 'default' => 'Hop on!'],
                'hero_cta_link'  => ['label' => 'Button link', 'type' => 'url', 'default' => '#deals'],
                'hero_image'     => ['label' => 'Header card artwork (853 × 450)', 'type' => 'image', 'default' => 'assets/img/hero-card.webp', 'help' => 'Keep the left side empty for the heading.'],
            ],
        ],
        'banner' => [
            'title'  => 'Deals page — bottom banner',
            'fields' => [
                'banner_kicker'   => ['label' => 'Small line (yellow)', 'type' => 'text', 'default' => 'explore more upcoming deals'],
                'banner_title'    => ['label' => 'Big line', 'type' => 'text', 'default' => 'this Big Billion Days'],
                'banner_cta_text' => ['label' => 'Button text', 'type' => 'text', 'default' => 'Lesssgo'],
                'banner_link'     => ['label' => 'Banner link', 'type' => 'url', 'default' => 'https://www.flipkart.com/big-billion-days-store'],
                'banner_image'    => ['label' => 'Banner artwork (optional, 1440 × 320)', 'type' => 'image', 'default' => '', 'help' => 'Upload a finished banner to use it instead of the texts above.'],
                'disclaimer'      => ['label' => 'Disclaimer (page footer)', 'type' => 'textarea', 'default' => 'Products, scenes, characters and their names are fictional and/or representational in nature. All copyrights in the images, logos and products belong to respective copyright holders. All the prices of the products/Offer(s)/Promotion(s) are provided by the participating sellers/brand partners/banking partners on the Flipkart platform. All Offers are for a limited period, limited products and subject to product availability. The offers may be discontinued or rescheduled as per discretion of Flipkart/participating seller/brand.'],
            ],
        ],
        'countdown' => [
            'title'  => 'Deals page — countdown',
            'fields' => [
                'countdown_enabled'      => ['label' => 'Show the countdown above the bottom banner', 'type' => 'bool', 'default' => '0'],
                'countdown_label'        => ['label' => 'Countdown label', 'type' => 'text', 'default' => 'Next deals in...'],
                'countdown_end'          => ['label' => 'Next deals start at', 'type' => 'datetime', 'default' => ''],
                'countdown_repeat_hours' => ['label' => 'Repeat every (hours)', 'type' => 'number', 'default' => '3', 'help' => 'After the countdown ends it restarts for this many hours. 0 = stop at 00:00.'],
            ],
        ],
        'location' => [
            'title'  => 'Location check screen',
            'fields' => [
                'geo_title'    => ['label' => 'Title', 'type' => 'text', 'default' => 'Location Access Required'],
                'geo_message'  => ['label' => 'Message', 'type' => 'textarea', 'default' => 'This website is currently available only within the designated Diva Junction location.'],
                'geo_privacy'  => ['label' => 'Privacy note', 'type' => 'textarea', 'default' => 'We use your location only to check that you are at Diva Junction. It is never shared, and we keep only an approximate copy for a limited time.'],
                'support_text' => ['label' => 'Support button text', 'type' => 'text', 'default' => 'Contact Support'],
                'support_link' => ['label' => 'Support link', 'type' => 'url', 'default' => 'https://www.flipkart.com/helpcentre', 'help' => 'A web address, mailto:someone@example.com or tel:+91…. Leave empty to hide the button.'],
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
