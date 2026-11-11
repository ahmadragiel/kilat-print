<?php

namespace App\Services;

use App\Enums\DesignStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\DesignFile;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RepeatOrderService
{
    public function __construct(private readonly PriceCalculationService $prices) {}

    public function repeat(Customer $customer, Order $source): RepeatOrderResult
    {
        abort_unless($source->customer_id === $customer->id, 403);
        $source->loadMissing(['items.product']);
        abort_if($source->items->isEmpty(), 422, 'Konfigurasi order ini tidak dapat diulang.');

        $discardedReferences = [];
        $missingDesigns = [];
        $reusedDesigns = 0;

        $cart = DB::transaction(function () use ($customer, $source, &$discardedReferences, &$missingDesigns, &$reusedDesigns): Cart {
            $cart = Cart::firstOrCreate(['customer_id' => $customer->id]);
            $discardedReferences = $cart->items()
                ->whereNotNull('design_reference')
                ->pluck('design_reference')
                ->filter(fn ($path) => is_string($path) && $path !== '')
                ->unique()
                ->values()
                ->all();

            $cart->items()->delete();

            foreach ($source->items as $item) {
                $product = $item->product;
                $productName = $item->product_name ?: $product?->name ?: 'Produk';

                if (! $product || $product->status !== 'active') {
                    throw ValidationException::withMessages([
                        'product_id' => "{$productName} sudah tidak tersedia dan tidak dapat dipesan ulang.",
                    ]);
                }

                try {
                    $price = $this->prices->calculate($product, [
                        'quantity' => $item->quantity,
                        'variant' => $item->variant,
                        'length_cm' => $item->length_cm,
                        'width_cm' => $item->width_cm,
                    ]);
                } catch (ValidationException $exception) {
                    throw $this->withProductName($exception, $productName);
                }

                $designReference = $this->finalDesignReference($item);

                if ($designReference === null) {
                    $missingDesigns[] = $productName;
                } else {
                    $reusedDesigns++;
                }

                $cart->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'size' => $item->size,
                    'variant' => $price['variant'],
                    'variant_label' => $price['variant_label'],
                    'length_cm' => $item->length_cm,
                    'width_cm' => $item->width_cm,
                    'color' => $item->color,
                    'production_method' => $item->production_method,
                    'custom_parameters' => $item->custom_parameters,
                    'custom_design_draft_id' => $item->custom_design_draft_id,
                    'price_at_addition' => $price['total'],
                    'design_reference' => $designReference,
                    'notes' => $item->notes,
                ]);
            }

            return $cart->load('items');
        });

        $this->removeReplacedCartFiles($discardedReferences);

        return new RepeatOrderResult(
            cart: $cart,
            reusedDesigns: $reusedDesigns,
            missingDesigns: array_values(array_unique($missingDesigns)),
        );
    }

    /**
     * Prefer the historical item snapshot, then fall back to an approved design
     * file that belongs to the same item. A path only counts when the private
     * file still exists; the source order and its files are never modified.
     */
    private function finalDesignReference(OrderItem $item): ?string
    {
        $fallbackPaths = DesignFile::query()
            ->where('order_id', $item->order_id)
            ->where('order_item_id', $item->id)
            ->where('status', DesignStatus::Approved->value)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->pluck('path');

        $candidates = collect([$item->design_reference])->merge($fallbackPaths);

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && Storage::disk('local')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function withProductName(ValidationException $exception, string $productName): ValidationException
    {
        $errors = $exception->errors();
        $key = array_key_first($errors) ?: 'variant';
        $message = $errors[$key][0] ?? 'Konfigurasi tidak dapat dipesan ulang.';

        return ValidationException::withMessages([
            $key => "{$productName}: {$message}",
        ]);
    }

    /** @param list<string> $references */
    private function removeReplacedCartFiles(array $references): void
    {
        foreach ($references as $reference) {
            if (! str_starts_with($reference, 'design-references/') || $this->referenceIsInUse($reference)) {
                continue;
            }

            Storage::disk('local')->delete($reference);
        }
    }

    private function referenceIsInUse(string $path): bool
    {
        return DesignFile::query()->where('path', $path)->exists()
            || CartItem::query()->where('design_reference', $path)->exists()
            || OrderItem::query()->where('design_reference', $path)->exists();
    }
}
