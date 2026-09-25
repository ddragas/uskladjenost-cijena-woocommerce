<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** The mapping from a WooCommerce product to the API's item and price, without WordPress. */
final class MapperTest extends TestCase
{
    public function test_a_simple_product_becomes_an_item_and_a_price(): void
    {
        $product = ['id' => 123, 'name' => 'Deterdžent 3 kg', 'sku' => 'DET-3', 'gtin' => '3859 0000 00001', 'category' => 'Kućanstvo', 'description' => '<p>Za  bijelo rublje</p>', 'in_stock' => true, 'regular_price' => '12.50', 'sale_price' => '', 'on_sale' => false, 'modified' => 1700000000];
        $item = UC_Mapper::item($product, 'm1', 'WEB');
        $this->assertSame(['merchant_id' => 'm1', 'kind' => 'product', 'name' => 'Deterdžent 3 kg', 'sku' => 'DET-3', 'barcode' => '3859000000001', 'category' => 'Kućanstvo', 'description' => 'Za bijelo rublje', 'active' => true, 'is_available' => true, 'channel_code' => 'WEB', 'metadata' => ['woocommerce_id' => 123]], $item);
        $price = UC_Mapper::price($product, 'eur', 'WEB');
        $this->assertSame(1250, $price['regular_price_minor']);
        $this->assertSame(1250, $price['effective_price_minor']);
        $this->assertSame('EUR', $price['currency']);
        $this->assertFalse($price['special_sale']['active']);
        $this->assertSame('wc:123:1250:1250:1700000000', $price['source_event_id']);
        $this->assertArrayNotHasKey('valid_to', $price);
    }

    public function test_a_sale_sets_the_effective_price_and_its_end(): void
    {
        $product = ['id' => 5, 'name' => 'X', 'regular_price' => '20', 'sale_price' => '15,90', 'on_sale' => true, 'sale_to' => '2026-10-31T23:59:59+01:00'];
        $price = UC_Mapper::price($product);
        $this->assertSame(2000, $price['regular_price_minor']);
        $this->assertSame(1590, $price['effective_price_minor']);
        $this->assertTrue($price['special_sale']['active']);
        $this->assertSame('2026-10-31T23:59:59+01:00', $price['valid_to']);

        // a "sale" price that is not below the regular one is no sale
        $price = UC_Mapper::price(['id' => 5, 'name' => 'X', 'regular_price' => '20', 'sale_price' => '20', 'on_sale' => true]);
        $this->assertSame(2000, $price['effective_price_minor']);
        $this->assertFalse($price['special_sale']['active']);
    }

    public function test_a_virtual_product_is_a_service_a_variation_carries_its_parents_name_and_no_price_means_no_event(): void
    {
        $this->assertSame('service', UC_Mapper::item(['id' => 1, 'name' => 'Pranje', 'virtual' => true], 'm1')['kind']);
        $item = UC_Mapper::item(['id' => 9, 'name' => 'Majica - L', 'parent_name' => 'Majica', 'attributes' => ['attribute_pa_size' => 'L', 'attribute_pa_color' => 'crna'], 'in_stock' => false], 'm1');
        $this->assertSame('Majica – L, crna', $item['name']);
        $this->assertFalse($item['is_available']);
        $this->assertArrayNotHasKey('channel_code', $item);
        $this->assertNull(UC_Mapper::price(['id' => 1, 'name' => 'X', 'regular_price' => '']));
        $this->assertNull(UC_Mapper::price(['id' => 1, 'name' => 'X', 'regular_price' => 'abc']));
        $this->assertSame(1000, UC_Mapper::minor(10));
        $this->assertSame(999, UC_Mapper::minor('9.99'));
        $this->assertSame(1, UC_Mapper::minor('0.005'));
    }

    public function test_the_label_comes_only_when_an_anchor_is_owed_and_known(): void
    {
        $this->assertSame('Cijena na dan 10. 9. 2026.: 12,50 €', UC_Mapper::label(['anchor_display' => ['required' => true, 'label' => 'Cijena na dan 10. 9. 2026.: 12,50 €']]));
        $this->assertSame('x', UC_Mapper::label(['data' => ['anchor_display' => ['required' => true, 'label' => 'x']]]));
        $this->assertNull(UC_Mapper::label(['anchor_display' => ['required' => false, 'label' => 'x']]));
        $this->assertNull(UC_Mapper::label(['anchor_display' => ['required' => true, 'label' => null, 'needs_review' => true]]));
        $this->assertNull(UC_Mapper::label([]));
    }
}
