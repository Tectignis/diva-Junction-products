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
                'landing_hello'    => ['label' => 'Greeting (pink, sparkly)', 'type' => 'text', 'default' => 'Hello, Divas!'],
                'landing_welcome'  => ['label' => 'Welcome line (blue)', 'type' => 'text', 'default' => 'Welcome to'],
                'landing_cta_text' => ['label' => 'Button text', 'type' => 'text', 'default' => 'Get Started'],
                'landing_cta_link' => ['label' => 'Deals page link', 'type' => 'url', 'default' => 'shop.php', 'help' => 'Where the button leads once the visitor may enter (lock OFF or location confirmed). Use shop.php, or any full URL.'],
                'landing_bg'       => ['label' => 'Background artwork (1080 × 1920)', 'type' => 'image', 'default' => 'assets/img/landing-bg.webp', 'help' => 'Station scene with an empty signboard. Greeting, logo and button are placed on top.'],
            ],
        ],
        'hero' => [
            'title'  => 'Deals page — header',
            'fields' => [
                'hero_banner' => ['label' => 'Header banner (1440 × 480)', 'type' => 'image', 'default' => 'assets/img/hero-banner.webp', 'help' => 'Shown full width, as is. No text is placed on top.'],
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
            'title'  => 'Location check — question',
            'fields' => [
                'ask_line'      => ['label' => 'Small line (blue)', 'type' => 'text', 'default' => 'Just a minute, Diva....'],
                'ask_question'  => ['label' => 'Question (pink)', 'type' => 'textarea', 'default' => "Are you really on\nDiva Junction?", 'help' => 'Shown while the browser asks for the location. Each line break is kept.'],
                'ask_bg'        => ['label' => 'Background artwork (1080 × 1920)', 'type' => 'image', 'default' => 'assets/img/ask-bg.webp', 'help' => 'Station scene with an empty wide signboard.'],
                'geo_privacy'   => ['label' => 'Privacy note (bottom of this screen)', 'type' => 'textarea', 'default' => 'We use your location only to check that you are at Diva Junction.', 'help' => 'Leave empty to hide it.'],
                'checking_text' => ['label' => 'Checking text', 'type' => 'text', 'default' => 'Checking...'],
            ],
        ],
        'result' => [
            'title'  => 'Location check — results',
            'fields' => [
                'success_sign'     => ['label' => 'Success sign artwork', 'type' => 'image', 'default' => 'assets/img/sign-success.webp', 'help' => 'Transparent image, 556 px wide; the poles touch the top edge.'],
                'success_line1'    => ['label' => 'Success — line 1 (blue)', 'type' => 'text', 'default' => 'Location'],
                'success_big1'     => ['label' => 'Success — line 1 (pink)', 'type' => 'text', 'default' => 'PERFECT.'],
                'success_line2'    => ['label' => 'Success — line 2 (blue)', 'type' => 'text', 'default' => 'Diva status'],
                'success_big2'     => ['label' => 'Success — line 2 (pink)', 'type' => 'text', 'default' => 'CONFIRMED.'],
                'success_line3'    => ['label' => 'Success — line above the button', 'type' => 'text', 'default' => 'Deals waiting'],
                'success_cta_text' => ['label' => 'Success — button text', 'type' => 'text', 'default' => 'Explore Now', 'help' => 'Opens the deals page link (Landing page section).'],
                'fail_sign'        => ['label' => 'Not-there sign artwork', 'type' => 'image', 'default' => 'assets/img/sign-fail.webp', 'help' => 'Also used on the other problem screens (location off, weak GPS, …).'],
                'fail_line'        => ['label' => 'Not there — small line (blue)', 'type' => 'text', 'default' => 'You are not on'],
                'fail_big'         => ['label' => 'Not there — big line (pink)', 'type' => 'text', 'default' => 'Diva Junction!'],
                'fail_message'     => ['label' => 'Not there — message', 'type' => 'textarea', 'default' => "Deals are there.\nAnd you are here?!"],
                'fail_cta_text'    => ['label' => 'Not there — button text', 'type' => 'text', 'default' => 'Get to Diva Junction'],
                'fail_cta_link'    => ['label' => 'Not there — button link', 'type' => 'url', 'default' => '', 'help' => 'Leave empty to open Google Maps directions to the Location lock pin.'],
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
