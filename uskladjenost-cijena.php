<?php
/**
 * Plugin Name: Usklađenost cijena za WooCommerce
 * Plugin URI:  https://uskladjenost-cijena.com
 * Description: Sidrene cijene i javni cjenik po NN 101/2026: artikli i cijene iz WooCommercea idu u Usklađenost cijena, a uz cijenu na stranici proizvoda ispisuje se propisana sidrena cijena.
 * Version:     1.0.0
 * Author:      Info Media d.o.o.
 * Author URI:  https://uskladjenost-cijena.com
 * License:     MIT
 * Text Domain: uskladjenost-cijena
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * WC requires at least: 8.0
 * WC tested up to: 9.5
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('UC_WC_VERSION', '1.0.0');
define('UC_WC_FILE', __FILE__);
define('UC_WC_DIR', plugin_dir_path(__FILE__));

require_once UC_WC_DIR.'includes/class-uc-mapper.php';
require_once UC_WC_DIR.'includes/class-uc-client.php';
require_once UC_WC_DIR.'includes/class-uc-settings.php';
require_once UC_WC_DIR.'includes/class-uc-sync.php';
require_once UC_WC_DIR.'includes/class-uc-display.php';

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    if (! class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>Usklađenost cijena treba aktivan WooCommerce.</p></div>';
        });

        return;
    }
    UC_Settings::init();
    UC_Sync::init();
    UC_Display::init();
    if (defined('WP_CLI') && WP_CLI) {
        require_once UC_WC_DIR.'includes/class-uc-cli.php';
    }
});
