<?php
/**
 * Plugin Name: Mad Baits App Installer
 * Description: Adds the manifest, service worker, icons, and install prompt wiring needed to install Mad Baits as a mobile web app.
 * Version: 1.0.0
 * Author: Mad Baits
 * License: GPL-2.0-or-later
 * Text Domain: mad-baits-pwa
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Mad_Baits_PWA {
    private const VERSION = '1.0.0';
    private const MANIFEST_QUERY = 'mad_baits_pwa_manifest';
    private const SERVICE_WORKER_QUERY = 'mad_baits_pwa_sw';
    private const ICON_QUERY = 'mad_baits_pwa_icon';
    private const OFFLINE_QUERY = 'mad_baits_pwa_offline';

    public static function init(): void {
        add_action('init', [__CLASS__, 'register_rewrites']);
        add_filter('query_vars', [__CLASS__, 'register_query_vars']);
        add_action('template_redirect', [__CLASS__, 'serve_pwa_assets']);
        add_action('wp_head', [__CLASS__, 'print_head_tags'], 1);
        add_action('wp_footer', [__CLASS__, 'print_install_script'], 99);
    }

    public static function activate(): void {
        self::register_rewrites();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    public static function register_rewrites(): void {
        add_rewrite_rule('^mad-baits\.webmanifest$', 'index.php?' . self::MANIFEST_QUERY . '=1', 'top');
        add_rewrite_rule('^mad-baits-sw\.js$', 'index.php?' . self::SERVICE_WORKER_QUERY . '=1', 'top');
        add_rewrite_rule('^mad-baits-pwa-icon-([0-9]+)\.svg$', 'index.php?' . self::ICON_QUERY . '=$matches[1]', 'top');
    }

    public static function register_query_vars(array $vars): array {
        $vars[] = self::MANIFEST_QUERY;
        $vars[] = self::SERVICE_WORKER_QUERY;
        $vars[] = self::ICON_QUERY;
        $vars[] = self::OFFLINE_QUERY;

        return $vars;
    }

    public static function serve_pwa_assets(): void {
        if (get_query_var(self::MANIFEST_QUERY)) {
            self::serve_manifest();
        }

        if (get_query_var(self::SERVICE_WORKER_QUERY)) {
            self::serve_service_worker();
        }

        $icon_size = absint(get_query_var(self::ICON_QUERY));
        if ($icon_size > 0) {
            self::serve_icon($icon_size);
        }

        if (isset($_GET[self::OFFLINE_QUERY])) {
            self::serve_offline_page();
        }
    }

    public static function print_head_tags(): void {
        if (is_admin()) {
            return;
        }

        $manifest_url = home_url('/mad-baits.webmanifest');
        $theme_color = '#111111';
        $icon_url = self::icon_url(180);

        echo "\n" . '<link rel="manifest" href="' . esc_url($manifest_url) . '">' . "\n";
        echo '<meta name="theme-color" content="' . esc_attr($theme_color) . '">' . "\n";
        echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-status-bar-style" content="black">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="Mad Baits">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . esc_url($icon_url) . '">' . "\n";
    }

    public static function print_install_script(): void {
        if (is_admin()) {
            return;
        }

        $sw_url = home_url('/mad-baits-sw.js');
        ?>
<script>
(function () {
    var deferredPrompt = null;
    var installTextPattern = /get\s+the\s+mad\s+baits\s+app/i;
    var directSelectors = '.madbaits-install-app, .mad-baits-install-app, [data-madbaits-install]';

    function isIos() {
        return /iphone|ipad|ipod/i.test(window.navigator.userAgent || '');
    }

    function isStandalone() {
        return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    }

    function explainInstallFallback() {
        if (isIos()) {
            window.alert('To install Mad Baits: tap Share, then Add to Home Screen.');
            return;
        }

        window.alert('To install Mad Baits: open your browser menu, then choose Install app or Add to Home screen.');
    }

    function bindInstallButtons() {
        var elements = Array.prototype.slice.call(document.querySelectorAll(directSelectors));

        Array.prototype.slice.call(document.querySelectorAll('a, button')).forEach(function (element) {
            if (installTextPattern.test((element.textContent || '').trim())) {
                elements.push(element);
            }
        });

        elements.forEach(function (element) {
            if (element.dataset.madBaitsInstallBound) {
                return;
            }

            element.dataset.madBaitsInstallBound = '1';
            element.addEventListener('click', function (event) {
                if (isStandalone()) {
                    return;
                }

                event.preventDefault();

                if (!deferredPrompt) {
                    explainInstallFallback();
                    return;
                }

                deferredPrompt.prompt();
                deferredPrompt.userChoice.finally(function () {
                    deferredPrompt = null;
                });
            });
        });
    }

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('<?php echo esc_js($sw_url); ?>', { scope: '/' }).catch(function () {});
        });
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        document.documentElement.classList.add('mad-baits-pwa-ready');
        bindInstallButtons();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindInstallButtons);
    } else {
        bindInstallButtons();
    }
}());
</script>
        <?php
    }

    private static function serve_manifest(): void {
        $manifest = [
            'name' => 'Mad Baits',
            'short_name' => 'Mad Baits',
            'description' => 'Premium carp bait, bundle deals, session tools, and latest Mad Baits drops.',
            'id' => home_url('/'),
            'start_url' => home_url('/?utm_source=mad_baits_app'),
            'scope' => home_url('/'),
            'display' => 'standalone',
            'display_override' => ['window-controls-overlay', 'standalone', 'browser'],
            'orientation' => 'portrait-primary',
            'background_color' => '#111111',
            'theme_color' => '#111111',
            'categories' => ['shopping', 'sports', 'lifestyle'],
            'icons' => [
                [
                    'src' => self::icon_url(192),
                    'sizes' => '192x192',
                    'purpose' => 'any maskable',
                ],
                [
                    'src' => self::icon_url(512),
                    'sizes' => '512x512',
                    'purpose' => 'any maskable',
                ],
            ],
            'shortcuts' => [
                [
                    'name' => 'Shop Bundles',
                    'short_name' => 'Bundles',
                    'url' => home_url('/product-category/bundles-deals/?utm_source=mad_baits_app'),
                    'icons' => [
                        [
                            'src' => self::icon_url(192),
                            'sizes' => '192x192',
                        ],
                    ],
                ],
                [
                    'name' => 'Basket',
                    'short_name' => 'Basket',
                    'url' => home_url('/cart/?utm_source=mad_baits_app'),
                    'icons' => [
                        [
                            'src' => self::icon_url(192),
                            'sizes' => '192x192',
                        ],
                    ],
                ],
            ],
            'prefer_related_applications' => false,
        ];

        status_header(200);
        header('Content-Type: application/manifest+json; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    private static function serve_service_worker(): void {
        $cache_name = 'mad-baits-pwa-' . self::VERSION;
        $precache_urls = [
            home_url('/'),
            home_url('/mad-baits.webmanifest'),
            self::icon_url(192),
            self::icon_url(512),
        ];

        status_header(200);
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        ?>
const CACHE_NAME = <?php echo wp_json_encode($cache_name); ?>;
const PRECACHE_URLS = <?php echo wp_json_encode($precache_urls, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>;
const STATIC_FILE_PATTERN = /\.(?:css|js|mjs|png|jpg|jpeg|gif|webp|svg|ico|woff|woff2)$/i;
const BYPASS_PATHS = ['/cart/', '/checkout/', '/my-account/', '/account/', '/wp-admin/', '/wp-login.php'];
const BYPASS_PARAMS = ['add-to-cart', 'remove_item', 'wc-ajax', '_wpnonce'];

function shouldBypass(url) {
    if (url.origin !== self.location.origin) {
        return true;
    }

    if (BYPASS_PATHS.some(function (path) { return url.pathname.indexOf(path) === 0; })) {
        return true;
    }

    return BYPASS_PARAMS.some(function (param) { return url.searchParams.has(param); });
}

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function (cache) {
                return Promise.all(PRECACHE_URLS.map(function (url) {
                    return cache.add(url).catch(function () {
                        return null;
                    });
                }));
            })
            .then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys()
            .then(function (keys) {
                return Promise.all(keys.map(function (key) {
                    if (key.indexOf('mad-baits-pwa-') === 0 && key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                    return Promise.resolve();
                }));
            })
            .then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') {
        return;
    }

    var url = new URL(event.request.url);

    if (shouldBypass(url)) {
        return;
    }

    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).catch(function () {
                return caches.match(<?php echo wp_json_encode(home_url('/')); ?>);
            })
        );
        return;
    }

    if (!STATIC_FILE_PATTERN.test(url.pathname)) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then(function (cachedResponse) {
            var fetchPromise = fetch(event.request).then(function (networkResponse) {
                if (networkResponse && networkResponse.ok && networkResponse.type === 'basic') {
                    var responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(event.request, responseClone);
                    });
                }

                return networkResponse;
            });

            return cachedResponse || fetchPromise;
        })
    );
});
        <?php
        exit;
    }

    private static function serve_icon(int $size): void {
        $size = max(48, min(1024, $size));
        $font_size = (int) round($size * 0.25);
        $sub_size = (int) round($size * 0.07);

        status_header(200);
        header('Content-Type: image/svg+xml; charset=utf-8');
        header('Cache-Control: public, max-age=604800');

        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
<svg xmlns="http://www.w3.org/2000/svg" width="<?php echo esc_attr((string) $size); ?>" height="<?php echo esc_attr((string) $size); ?>" viewBox="0 0 <?php echo esc_attr((string) $size); ?> <?php echo esc_attr((string) $size); ?>" role="img" aria-label="Mad Baits">
    <rect width="<?php echo esc_attr((string) $size); ?>" height="<?php echo esc_attr((string) $size); ?>" rx="<?php echo esc_attr((string) round($size * 0.2)); ?>" fill="#111111"/>
    <circle cx="<?php echo esc_attr((string) round($size * 0.74)); ?>" cy="<?php echo esc_attr((string) round($size * 0.24)); ?>" r="<?php echo esc_attr((string) round($size * 0.13)); ?>" fill="#e32228"/>
    <path d="M<?php echo esc_attr((string) round($size * 0.22)); ?> <?php echo esc_attr((string) round($size * 0.66)); ?>C<?php echo esc_attr((string) round($size * 0.36)); ?> <?php echo esc_attr((string) round($size * 0.46)); ?> <?php echo esc_attr((string) round($size * 0.58)); ?> <?php echo esc_attr((string) round($size * 0.46)); ?> <?php echo esc_attr((string) round($size * 0.78)); ?> <?php echo esc_attr((string) round($size * 0.64)); ?>" fill="none" stroke="#f3f3f3" stroke-width="<?php echo esc_attr((string) round($size * 0.045)); ?>" stroke-linecap="round"/>
    <text x="50%" y="54%" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="<?php echo esc_attr((string) $font_size); ?>" font-weight="800" fill="#f3f3f3" letter-spacing="0">MAD</text>
    <text x="50%" y="69%" text-anchor="middle" font-family="Arial, Helvetica, sans-serif" font-size="<?php echo esc_attr((string) $sub_size); ?>" font-weight="700" fill="#e32228" letter-spacing="0">BAITS</text>
</svg>
        <?php
        exit;
    }

    private static function serve_offline_page(): void {
        status_header(200);
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mad Baits</title>
    <style>
        body {
            align-items: center;
            background: #111111;
            color: #f3f3f3;
            display: flex;
            font-family: Arial, Helvetica, sans-serif;
            justify-content: center;
            margin: 0;
            min-height: 100vh;
            padding: 24px;
            text-align: center;
        }
        main {
            max-width: 420px;
        }
        h1 {
            font-size: 32px;
            margin: 0 0 12px;
        }
        p {
            color: #d7d7d7;
            font-size: 16px;
            line-height: 1.5;
            margin: 0;
        }
    </style>
</head>
<body>
    <main>
        <h1>Mad Baits</h1>
        <p>You are offline at the moment. Reconnect to keep shopping and checking the latest drops.</p>
    </main>
</body>
</html>
        <?php
        exit;
    }

    private static function icon_url(int $size): string {
        $site_icon = get_site_icon_url($size);

        if ($site_icon) {
            return esc_url_raw($site_icon);
        }

        return home_url('/mad-baits-pwa-icon-' . absint($size) . '.svg');
    }

}

Mad_Baits_PWA::init();

register_activation_hook(__FILE__, ['Mad_Baits_PWA', 'activate']);
register_deactivation_hook(__FILE__, ['Mad_Baits_PWA', 'deactivate']);
