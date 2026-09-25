<?php

declare(strict_types=1);

/**
 * The anchor price next to the price on the product page, from the API's
 * decision for the product on the webshop channel, cached six hours and
 * forgotten on every sync of that product.
 */
final class UC_Display
{
    private const TTL = 6 * HOUR_IN_SECONDS;

    public static function init(): void
    {
        if (get_option('uc_show_label', 'yes') !== 'yes') {
            return;
        }
        add_filter('woocommerce_get_price_html', [self::class, 'appendToPriceHtml'], 20, 2);
        add_filter('woocommerce_available_variation', [self::class, 'variation'], 20, 3);
    }

    /** @param WC_Product $product */
    public static function appendToPriceHtml(string $html, $product): string
    {
        if (! $product instanceof WC_Product || $product->is_type('variable') || is_admin()) {
            return $html;
        }
        $label = self::label((string) $product->get_id());

        return $label === null ? $html : $html.'<span class="uc-anchor-price" style="display:block;font-size:.85em;opacity:.8">'.esc_html($label).'</span>';
    }

    /** @param array<string, mixed> $data @param WC_Product_Variable $variable @param WC_Product_Variation $variation @return array<string, mixed> */
    public static function variation(array $data, $variable, $variation): array
    {
        $label = self::label((string) $variation->get_id());
        if ($label !== null && isset($data['price_html']) && is_string($data['price_html'])) {
            $data['price_html'] .= '<span class="uc-anchor-price" style="display:block;font-size:.85em;opacity:.8">'.esc_html($label).'</span>';
        }
        $data['uc_anchor_label'] = $label;

        return $data;
    }

    public static function label(string $externalId): ?string
    {
        $key = self::key($externalId);
        $cached = get_transient($key);
        if ($cached !== false) {
            return $cached === '' ? null : (string) $cached;
        }
        $client = UC_Client::fromSettings();
        if ($client === null) {
            return null;
        }
        $decision = $client->complianceByExternal($externalId, ['channel_code' => (string) get_option('uc_channel_code', 'WEB'), 'locale' => 'hr']);
        $label = isset($decision['error']) ? null : UC_Mapper::label($decision['data'] ?? []);
        set_transient($key, $label ?? '', isset($decision['error']) ? 5 * MINUTE_IN_SECONDS : self::TTL);

        return $label;
    }

    public static function forget(string $externalId): void
    {
        delete_transient(self::key($externalId));
    }

    private static function key(string $externalId): string
    {
        return 'uc_label_'.md5($externalId.'|'.(string) get_option('uc_channel_code', 'WEB'));
    }
}
