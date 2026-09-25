<?php

declare(strict_types=1);

/** WooCommerce → Postavke → Usklađenost cijena: the token, the merchant, the webshop channel code, what to show. */
final class UC_Settings
{
    public const OPTIONS = ['uc_api_token', 'uc_base_url', 'uc_merchant_id', 'uc_channel_code', 'uc_show_label', 'uc_auto_sync'];

    public static function init(): void
    {
        add_filter('woocommerce_settings_tabs_array', static function (array $tabs): array {
            $tabs['uskladjenost'] = 'Usklađenost cijena';

            return $tabs;
        }, 50);
        add_action('woocommerce_settings_tabs_uskladjenost', [self::class, 'render']);
        add_action('woocommerce_update_options_uskladjenost', [self::class, 'save']);
        add_action('admin_post_uc_sync_all', [self::class, 'syncAll']);
        add_action('admin_notices', [self::class, 'notices']);
    }

    /** @return list<array<string, mixed>> */
    public static function fields(): array
    {
        return [
            ['title' => 'Povezivanje', 'type' => 'title', 'id' => 'uc_section', 'desc' => 'Token izdajete u aplikaciji Usklađenost cijena pod <em>API pristup</em>, s opsezima catalog:write, prices:write i compliance:read. Šifra kanala je kod vašeg webshopa (Trgovac → Kanali), npr. WEB.'],
            ['title' => 'API token', 'type' => 'password', 'id' => 'uc_api_token', 'desc_tip' => 'pc_live_… ili pc_test_…', 'css' => 'min-width:420px'],
            ['title' => 'ID trgovca', 'type' => 'text', 'id' => 'uc_merchant_id', 'desc_tip' => 'Iz aplikacije: Trgovci → vaš trgovac → ID.', 'css' => 'min-width:420px'],
            ['title' => 'Šifra kanala (webshop)', 'type' => 'text', 'id' => 'uc_channel_code', 'desc_tip' => 'Kod prodajnog kanala tipa webshop, npr. WEB. Cijene iz WooCommercea idu na taj kanal, i javni cjenik se gradi za njega.', 'default' => 'WEB'],
            ['title' => 'Adresa servisa', 'type' => 'text', 'id' => 'uc_base_url', 'default' => 'https://uskladjenost-cijena.com', 'desc_tip' => 'Ostavite kako jest; mijenja se samo za testni poslužitelj.', 'css' => 'min-width:420px'],
            ['type' => 'sectionend', 'id' => 'uc_section'],
            ['title' => 'Ponašanje', 'type' => 'title', 'id' => 'uc_behaviour'],
            ['title' => 'Automatska sinkronizacija', 'type' => 'checkbox', 'id' => 'uc_auto_sync', 'desc' => 'Svaka spremljena promjena proizvoda (naziv, šifra, cijena, akcija, zaliha) odmah ide u Usklađenost cijena.', 'default' => 'yes'],
            ['title' => 'Sidrena cijena uz cijenu', 'type' => 'checkbox', 'id' => 'uc_show_label', 'desc' => 'Na stranici proizvoda ispod cijene ispiši propisani tekst (npr. „Cijena na dan 10. 9. 2026.: 12,50 €”).', 'default' => 'yes'],
            ['type' => 'sectionend', 'id' => 'uc_behaviour'],
        ];
    }

    public static function render(): void
    {
        woocommerce_admin_fields(self::fields());
        $client = UC_Client::fromSettings();
        echo '<h2>Stanje</h2><table class="form-table"><tr><th>Veza</th><td>';
        if ($client === null) {
            echo 'Upišite token.';
        } else {
            $ping = $client->ping();
            echo isset($ping['error']) ? '<span style="color:#932F30">Greška: '.esc_html($ping['error']['message']).'</span>' : '<span style="color:#27614A">Povezano: '.esc_html((string) ($ping['data']['tenant'] ?? '')).' (opsezi: '.esc_html(implode(', ', (array) ($ping['data']['scopes'] ?? []))).')</span>';
        }
        echo '</td></tr><tr><th>Zadnja sinkronizacija</th><td>'.esc_html((string) get_option('uc_last_sync', '—')).'</td></tr>';
        echo '<tr><th>Sve proizvode sada</th><td><a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=uc_sync_all'), 'uc_sync_all')).'">Pošalji sve proizvode i cijene</a> <span class="description">Šalje cijeli katalog u serijama od 200; na velikim trgovinama traje minutu-dvije.</span></td></tr></table>';
    }

    public static function save(): void
    {
        woocommerce_update_options(self::fields());
        delete_option('uc_last_error');
    }

    public static function syncAll(): void
    {
        if (! current_user_can('manage_woocommerce') || ! wp_verify_nonce((string) ($_GET['_wpnonce'] ?? ''), 'uc_sync_all')) {
            wp_die('Nedopušteno.');
        }
        $summary = UC_Sync::syncAll();
        set_transient('uc_notice', $summary, 60);
        wp_safe_redirect(admin_url('admin.php?page=wc-settings&tab=uskladjenost'));
        exit;
    }

    public static function notices(): void
    {
        $notice = get_transient('uc_notice');
        if (is_string($notice) && $notice !== '') {
            delete_transient('uc_notice');
            echo '<div class="notice notice-success is-dismissible"><p>Usklađenost cijena: '.esc_html($notice).'</p></div>';
        }
        $error = get_option('uc_last_error');
        if (is_string($error) && $error !== '' && current_user_can('manage_woocommerce')) {
            echo '<div class="notice notice-warning"><p>Usklađenost cijena: zadnja sinkronizacija nije prošla: '.esc_html($error).'</p></div>';
        }
    }
}
