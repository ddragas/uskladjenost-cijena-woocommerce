<?php

declare(strict_types=1);

/**
 * Products and prices out of WooCommerce: on every save (one product) and
 * on demand (the whole catalogue, in bulk). Variations are items of their
 * own; a variable parent is not sent.
 */
final class UC_Sync
{
    public static function init(): void
    {
        if (get_option('uc_auto_sync', 'yes') !== 'yes') {
            return;
        }
        foreach (['woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation'] as $hook) {
            add_action($hook, [self::class, 'onSave'], 20, 1);
        }
        add_action('woocommerce_product_set_stock_status', [self::class, 'onSave'], 20, 1);
        add_action('woocommerce_variation_set_stock_status', [self::class, 'onSave'], 20, 1);
    }

    /** @param int|WC_Product $product */
    public static function onSave($product): void
    {
        $product = $product instanceof WC_Product ? $product : wc_get_product($product);
        if (! $product instanceof WC_Product) {
            return;
        }
        $client = UC_Client::fromSettings();
        $merchant = (string) get_option('uc_merchant_id', '');
        if ($client === null || $merchant === '') {
            return;
        }
        foreach (self::leaves($product) as $leaf) {
            $error = self::syncOne($client, $merchant, $leaf);
            if ($error !== null) {
                update_option('uc_last_error', $error, false);

                return;
            }
        }
        delete_option('uc_last_error');
        update_option('uc_last_sync', current_time('mysql'), false);
    }

    /** The product itself, or every variation of a variable product. @return list<WC_Product> */
    public static function leaves(WC_Product $product): array
    {
        if ($product->is_type('variable')) {
            $leaves = [];
            foreach ($product->get_children() as $childId) {
                $child = wc_get_product($childId);
                if ($child instanceof WC_Product) {
                    $leaves[] = $child;
                }
            }

            return $leaves;
        }

        return [$product];
    }

    /** @return array<string, mixed> the array UC_Mapper takes */
    public static function extract(WC_Product $product): array
    {
        $parent = $product->is_type('variation') ? wc_get_product($product->get_parent_id()) : null;
        $terms = get_the_terms($parent ? $parent->get_id() : $product->get_id(), 'product_cat');
        $gtin = method_exists($product, 'get_global_unique_id') ? (string) $product->get_global_unique_id() : (string) $product->get_meta('_gtin', true);

        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'parent_name' => $parent ? $parent->get_name() : null,
            'attributes' => $product->is_type('variation') ? array_map('strval', $product->get_attributes()) : [],
            'sku' => $product->get_sku(),
            'virtual' => $product->is_virtual(),
            'description' => $product->get_short_description() ?: ($parent ? $parent->get_short_description() : ''),
            'category' => is_array($terms) && $terms !== [] ? $terms[0]->name : null,
            'gtin' => $gtin,
            'in_stock' => $product->is_in_stock(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'on_sale' => $product->is_on_sale(),
            'sale_to' => $product->get_date_on_sale_to() ? $product->get_date_on_sale_to()->date('c') : null,
            'modified' => $product->get_date_modified() ? $product->get_date_modified()->getTimestamp() : '',
        ];
    }

    /** One product: the item, then its price. Returns the error text or null. */
    public static function syncOne(UC_Client $client, string $merchant, WC_Product $product): ?string
    {
        $data = self::extract($product);
        $channel = (string) get_option('uc_channel_code', 'WEB') ?: null;
        $external = (string) $data['id'];
        $item = $client->upsertItem($external, UC_Mapper::item($data, $merchant, $channel));
        if (isset($item['error'])) {
            return 'artikl '.$external.': '.$item['error']['message'];
        }
        $price = UC_Mapper::price($data, get_woocommerce_currency(), $channel);
        if ($price !== null) {
            $result = $client->recordPriceByExternal($external, $price);
            if (isset($result['error'])) {
                return 'cijena '.$external.': '.$result['error']['message'];
            }
        }
        UC_Display::forget($external);

        return null;
    }

    /** The whole catalogue in batches of 200 items and their prices. Returns a summary line. */
    public static function syncAll(): string
    {
        $client = UC_Client::fromSettings();
        $merchant = (string) get_option('uc_merchant_id', '');
        if ($client === null || $merchant === '') {
            return 'Upišite token i ID trgovca.';
        }
        $channel = (string) get_option('uc_channel_code', 'WEB') ?: null;
        $currency = get_woocommerce_currency();
        $sent = 0;
        $errors = 0;
        $page = 1;
        do {
            $products = wc_get_products(['status' => 'publish', 'limit' => 100, 'page' => $page, 'type' => ['simple', 'variable']]);
            $items = [];
            $prices = [];
            foreach ($products as $product) {
                foreach (self::leaves($product) as $leaf) {
                    $data = self::extract($leaf);
                    $items[] = UC_Mapper::item($data, $merchant, $channel) + ['external_id' => (string) $data['id']];
                    $price = UC_Mapper::price($data, $currency, $channel);
                    if ($price !== null) {
                        unset($price['channel_code']);
                        $prices[(string) $data['id']] = $price;
                    }
                    UC_Display::forget((string) $data['id']);
                }
            }
            foreach (array_chunk($items, 200) as $chunk) {
                $result = $client->bulkItems($chunk);
                if (isset($result['error'])) {
                    return 'Greška: '.$result['error']['message'];
                }
                $events = [];
                foreach ($result['data'] ?? [] as $row) {
                    if (($row['status'] ?? '') === 'error') {
                        $errors++;

                        continue;
                    }
                    $sent++;
                    $external = (string) ($row['external_id'] ?? '');
                    if (isset($prices[$external], $row['data']['offer_id'])) {
                        $events[] = $prices[$external] + ['offer_id' => $row['data']['offer_id']];
                    }
                }
                foreach (array_chunk($events, 1000) as $batch) {
                    $priced = $client->bulkPrices($batch);
                    $errors += (int) ($priced['summary']['errors'] ?? 0);
                }
            }
            $page++;
        } while (count($products) === 100);
        update_option('uc_last_sync', current_time('mysql'), false);
        delete_option('uc_last_error');

        return sprintf('poslano %d artikala, %d s greškom.', $sent, $errors);
    }
}
