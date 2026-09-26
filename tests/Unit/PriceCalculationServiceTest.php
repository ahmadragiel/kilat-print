<?php

namespace Tests\Unit;

use App\Enums\PricingType;
use App\Services\PriceCalculationService;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PriceCalculationServiceTest extends TestCase
{
    public function test_per_item_price_is_multiplied_by_quantity(): void
    {
        $product = $this->makeProduct(PricingType::PER_ITEM, 12500);

        $result = app(PriceCalculationService::class)->calculate($product, [
            'quantity' => 3,
        ]);

        $this->assertSame(3, $result['quantity']);
        $this->assertSame(12500, $result['unit_price']);
        $this->assertSame(37500, $result['total']);
        $this->assertSame(37500, $result['breakdown']['product']);
    }

    public function test_per_square_meter_price_uses_length_and_width_converted_to_square_meters(): void
    {
        $product = $this->makeProduct(PricingType::PER_SQM, 20000);

        $result = app(PriceCalculationService::class)->calculate($product, [
            'quantity' => 2,
            'length_cm' => 100,
            'width_cm' => 50,
        ]);

        $this->assertSame(20000, $result['total']);
        $this->assertSame(1.0, $result['dimensions']['area_sqm']);
        $this->assertSame(100.0, $result['dimensions']['length_cm']);
        $this->assertSame(50.0, $result['dimensions']['width_cm']);
    }

    public function test_per_meter_price_uses_centimeters_converted_to_meters(): void
    {
        $product = $this->makeProduct(PricingType::PER_METER, 10000);

        $result = app(PriceCalculationService::class)->calculate($product, [
            'quantity' => 2,
            'length_cm' => 150,
        ]);

        $this->assertSame(30000, $result['total']);
        $this->assertSame(3.0, $result['dimensions']['length_meter']);
    }

    public function test_fixed_price_is_not_multiplied_by_quantity(): void
    {
        $product = $this->makeProduct(PricingType::FIXED, 50000);

        $result = app(PriceCalculationService::class)->calculate($product, [
            'quantity' => 8,
        ]);

        $this->assertSame(50000, $result['total']);
        $this->assertSame(6250, $result['unit_price']);
    }

    public function test_material_and_finishing_overrides_are_used_instead_of_catalog_prices(): void
    {
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $material = $this->attachMaterial($product, PricingType::PER_ITEM, 1000, 1500);
        $finishing = $this->attachFinishing($product, PricingType::PER_ITEM, 800, 1200);

        $result = app(PriceCalculationService::class)->calculate($product, [
            'quantity' => 2,
            'material_id' => $material->id,
            'finishing_id' => $finishing->id,
        ]);

        $this->assertSame(20000, $result['breakdown']['product']);
        $this->assertSame(3000, $result['breakdown']['material']);
        $this->assertSame(2400, $result['breakdown']['finishing']);
        $this->assertSame(25400, $result['total']);
    }

    public function test_additional_fee_is_added_once_as_a_fixed_charge(): void
    {
        $product = $this->makeProduct(PricingType::ADDITIONAL_FEE, 2500);

        $result = app(PriceCalculationService::class)->calculate($product, [
            'quantity' => 10,
        ]);

        $this->assertSame(2500, $result['total']);
        $this->assertSame(250, $result['unit_price']);
        $this->assertSame(2500, $result['breakdown']['product']);
    }

    public function test_minimum_order_is_enforced_before_pricing(): void
    {
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000, [
            'minimum_order' => 5,
        ]);

        try {
            app(PriceCalculationService::class)->calculate($product, ['quantity' => 4]);
            $this->fail('Expected a minimum-order validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('quantity', $exception->errors());
            $this->assertStringContainsString('5', $exception->errors()['quantity'][0]);
        }
    }

    public function test_area_and_length_tariffs_reject_missing_dimensions(): void
    {
        $product = $this->makeProduct(PricingType::PER_SQM, 10000);

        try {
            app(PriceCalculationService::class)->calculate($product, ['quantity' => 1]);
            $this->fail('Expected a dimension validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('length_cm', $exception->errors());
        }

        $meterProduct = $this->makeProduct(PricingType::PER_METER, 10000);
        $this->expectException(ValidationException::class);
        app(PriceCalculationService::class)->calculate($meterProduct, ['quantity' => 1]);
    }

    public function test_calculator_returns_selected_options_and_rejects_inactive_products(): void
    {
        $product = $this->makeProduct(PricingType::PER_ITEM, 10000);
        $material = $this->attachMaterial($product, PricingType::FIXED, 700);

        $result = app(PriceCalculationService::class)->calculate($product, [
            'quantity' => 2,
            'material_id' => $material->id,
        ]);

        $this->assertTrue($result['material']->is($material));
        $this->assertSame(700, $result['breakdown']['material']);

        $product->update(['status' => 'inactive']);
        $this->expectException(HttpException::class);
        app(PriceCalculationService::class)->calculate($product->fresh(), ['quantity' => 2]);
    }
}
