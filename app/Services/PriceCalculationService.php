<?php

namespace App\Services;

use App\Enums\PricingType;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

class PriceCalculationService
{
    /**
     * Price a configuration from the product tariff only.
     *
     * The total is derived from the product rules combined with the chosen size and
     * quantity. Material and finishing options are no longer part of the customer
     * configuration, so they are deliberately ignored here.
     *
     * @param  array<string, mixed>  $configuration
     * @return array{product:Product,quantity:int,unit_price:int,total:int,breakdown:array<string,int>,dimensions:array<string,float>,variant:?string,variant_label:?string}
     */
    public function calculate(Product $product, array $configuration): array
    {
        abort_unless($product->status === 'active', 422, 'Produk tidak tersedia.');

        $quantity = max(1, (int) ($configuration['quantity'] ?? 1));
        $length = (float) ($configuration['length_cm'] ?? 0);
        $width = (float) ($configuration['width_cm'] ?? 0);

        if ($quantity < (int) $product->minimum_order) {
            throw ValidationException::withMessages([
                'quantity' => "Minimum order produk ini adalah {$product->minimum_order}.",
            ]);
        }

        [$variant, $variantLabel] = $this->resolveVariant($product, $configuration);

        $area = $length > 0 && $width > 0 ? ($length / 100) * ($width / 100) : 0;
        $meters = $length > 0 ? $length / 100 : 0;

        $rules = $product->priceRules()
            ->where('active', true)
            ->where('min_quantity', '<=', $quantity)
            ->when(
                $variant !== null,
                fn ($query) => $query->where(fn ($nested) => $nested->whereNull('variant_key')->orWhere('variant_key', $variant)),
                fn ($query) => $query->whereNull('variant_key'),
            )
            // A variant-specific tariff beats the generic tariff at the same
            // quantity tier. A higher quantity tier still wins, which is how the
            // disabled wholesale tier will behave once its threshold is agreed.
            ->orderByDesc('min_quantity')
            ->orderByRaw('CASE WHEN variant_key IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id')
            ->get();

        if ($rules->isEmpty()) {
            throw ValidationException::withMessages(['product_id' => 'Produk belum memiliki aturan harga aktif untuk pilihan ini.']);
        }

        $breakdown = ['product' => 0];
        $charges = 0;
        $seenBase = [];

        foreach ($rules as $rule) {
            $type = $rule->pricing_type instanceof PricingType ? $rule->pricing_type : PricingType::from($rule->pricing_type);
            $multiplier = $this->multiplier($type, $quantity, $area, $meters);

            if ($multiplier === null) {
                throw ValidationException::withMessages([
                    'length_cm' => "Aturan harga {$type->label()} membutuhkan ukuran panjang dan lebar.",
                ]);
            }

            if (in_array($type, [PricingType::PerItem, PricingType::PerSqm, PricingType::PerMeter, PricingType::Fixed], true)) {
                if (isset($seenBase[$type->value])) {
                    continue;
                }
                $seenBase[$type->value] = true;
            }

            $charge = (int) round($rule->discounted_price * $multiplier);
            $charges += $charge;
            $breakdown['product'] += $charge;
        }

        $total = array_sum($breakdown);
        $dimensions = $this->dimensionsFor($length, $width, $quantity, $area, $meters);

        return [
            'product' => $product,
            'quantity' => $quantity,
            'unit_price' => (int) round($total / $quantity),
            'total' => $total,
            'breakdown' => $breakdown,
            'dimensions' => $dimensions,
            'variant' => $variant,
            'variant_label' => $variantLabel,
        ];
    }

    /**
     * Validate the selected variation against the product definition. The label
     * is always resolved from the server-side definition so a browser cannot
     * inject an arbitrary snapshot.
     *
     * @param  array<string, mixed>  $configuration
     * @return array{0:?string,1:?string}
     */
    private function resolveVariant(Product $product, array $configuration): array
    {
        $definition = $product->variationDefinition();

        if ($definition === null) {
            return [null, null];
        }

        $requested = trim((string) ($configuration['variant'] ?? ''));

        if ($requested === '') {
            if ($definition['required']) {
                throw ValidationException::withMessages([
                    'variant' => "Pilih {$definition['label']} terlebih dahulu.",
                ]);
            }

            return [null, null];
        }

        $option = $product->variationOption($requested);

        if ($option === null) {
            throw ValidationException::withMessages([
                'variant' => "Pilihan {$definition['label']} tidak tersedia.",
            ]);
        }

        return [$option['value'], $option['label']];
    }

    private function multiplier(PricingType $type, int $quantity, float $area, float $meters): ?float
    {
        return match ($type) {
            PricingType::PerItem => (float) $quantity,
            PricingType::PerSqm => $area > 0 ? $area * $quantity : null,
            PricingType::PerMeter => $meters > 0 ? $meters * $quantity : null,
            PricingType::Fixed, PricingType::AdditionalFee => 1.0,
        };
    }

    /** @return array<string, float> */
    private function dimensionsFor(float $length, float $width, int $quantity, float $area, float $meters): array
    {
        return [
            'length_cm' => $length,
            'width_cm' => $width,
            'area_sqm' => round($area * $quantity, 4),
            'length_meter' => round($meters * $quantity, 4),
            'quantity' => $quantity,
        ];
    }
}
