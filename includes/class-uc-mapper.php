<?php

declare(strict_types=1);

/**
 * WooCommerce product data → API payloads. Pure PHP (no WordPress), so it
 * is unit-tested on its own. The external id of a product is its
 * WooCommerce id as a string ("123"); a variation's is the variation id.
 */
final class UC_Mapper
{
    /**
     * The item to PUT /items/{id}.
     *
     * @param  array{id: int, name: string, sku?: string, virtual?: bool, description?: string, category?: string, gtin?: string, in_stock?: bool, parent_name?: string, attributes?: array<string, string>}  $product
     * @return array<string, mixed>
     */
    public static function item(array $product, string $merchantId, ?string $channelCode = null): array
    {
        $name = $product['name'];
        if (! empty($product['parent_name']) && ! empty($product['attributes'])) {
            $name = $product['parent_name'].' – '.implode(', ', array_values($product['attributes']));
        }
        $item = [
            'merchant_id' => $merchantId,
            'kind' => ! empty($product['virtual']) ? 'service' : 'product',
            'name' => mb_substr($name, 0, 255),
            'sku' => ! empty($product['sku']) ? (string) $product['sku'] : null,
            'barcode' => ! empty($product['gtin']) ? preg_replace('/\s+/', '', (string) $product['gtin']) : null,
            'category' => ! empty($product['category']) ? mb_substr((string) $product['category'], 0, 120) : null,
            'description' => ! empty($product['description']) ? mb_substr(wp_strip_all_tags_fallback((string) $product['description']), 0, 2000) : null,
            'active' => true,
            'is_available' => $product['in_stock'] ?? true,
            'channel_code' => $channelCode,
            'metadata' => ['woocommerce_id' => $product['id']],
        ];

        return array_filter($item, static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * The price event to POST /prices/by-external/{id}, or null when the
     * product has no price. Amounts arrive as WooCommerce decimal strings.
     *
     * @param  array{regular_price?: string|float|int|null, sale_price?: string|float|int|null, on_sale?: bool, sale_from?: string|null, sale_to?: string|null}  $product
     * @return array<string, mixed>|null
     */
    public static function price(array $product, string $currency = 'EUR', ?string $channelCode = null): ?array
    {
        $regular = self::minor($product['regular_price'] ?? null);
        if ($regular === null) {
            return null;
        }
        $sale = self::minor($product['sale_price'] ?? null);
        $onSale = ! empty($product['on_sale']) && $sale !== null && $sale < $regular;
        $event = [
            'regular_price_minor' => $regular,
            'effective_price_minor' => $onSale ? $sale : $regular,
            'currency' => strtoupper($currency),
            'tax_inclusive' => true,
            'special_sale' => ['active' => $onSale],
            'source_event_id' => 'wc:'.($product['id'] ?? '').':'.$regular.':'.($onSale ? $sale : $regular).':'.(string) ($product['modified'] ?? ''),
            'channel_code' => $channelCode,
        ];
        if ($onSale && ! empty($product['sale_to'])) {
            $event['valid_to'] = $product['sale_to'];
        }

        return array_filter($event, static fn ($v) => $v !== null);
    }

    /** "12.50" → 1250; "" / null / not a number → null. */
    public static function minor(mixed $amount): ?int
    {
        if ($amount === null || $amount === '' || $amount === false) {
            return null;
        }
        $text = str_replace(',', '.', trim((string) $amount));
        if (! is_numeric($text)) {
            return null;
        }

        return (int) round(((float) $text) * 100);
    }

    /** The one line printed next to the price, from the API's decision; null when nothing is owed. @param array<string, mixed> $decision */
    public static function label(array $decision): ?string
    {
        $display = $decision['anchor_display'] ?? ($decision['data']['anchor_display'] ?? null);
        if (! is_array($display) || empty($display['required'])) {
            return null;
        }
        $label = $display['label'] ?? null;

        return is_string($label) && $label !== '' ? $label : null;
    }
}

if (! function_exists('wp_strip_all_tags_fallback')) {
    /** wp_strip_all_tags when WordPress is loaded; a plain strip otherwise (tests). */
    function wp_strip_all_tags_fallback(string $text): string
    {
        return function_exists('wp_strip_all_tags') ? wp_strip_all_tags($text) : trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');
    }
}
