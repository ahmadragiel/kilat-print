<?php

namespace Tests\Feature;

use App\Models\CustomDesignAsset;
use App\Models\CustomDesignDraft;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomDesignPhotoTest extends TestCase
{
    protected function fakePng(string $name = 'photo.png'): UploadedFile
    {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );

        $path = tempnam(sys_get_temp_dir(), 'kilat-photo-').'.png';
        file_put_contents($path, $png);

        return new UploadedFile($path, $name, 'image/png', null, true);
    }

    protected function photoProduct(): Product
    {
        return $this->makeProduct(attributes: [
            'catalog_key' => 'kertas-kado-custom',
            'name' => 'Kertas Kado Custom',
        ]);
    }

    protected function createDraft($customer, Product $product): CustomDesignDraft
    {
        $response = $this->actingAs($customer->user)->postJson(route('custom-designs.store'), [
            'product_id' => $product->id,
            'specification' => ['quantity' => 1],
            'design' => [
                'front' => ['elements' => []],
                'back' => ['elements' => []],
            ],
        ])->assertOk();

        return CustomDesignDraft::findOrFail($response->json('id'));
    }

    protected function uploadPhotoAsset($customer, CustomDesignDraft $draft): CustomDesignAsset
    {
        $id = $this->actingAs($customer->user)->post(
            route('custom-designs.assets.store', $draft),
            ['image' => $this->fakePng()],
            ['Accept' => 'application/json'],
        )->assertOk()->json('id');

        return CustomDesignAsset::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $slotOverrides
     * @return array<string, mixed>
     */
    protected function photoPayload(string $assetId, array $slotOverrides = []): array
    {
        return [
            'asset_id' => $assetId,
            'source_width' => 900,
            'source_height' => 600,
            'face_detected' => true,
            'face_count' => 1,
            'face_padding' => 0.28,
            'base_crop' => ['x' => 150, 'y' => 0, 'width' => 600, 'height' => 600],
            'slots' => [
                array_replace([
                    'id' => 'dino-01',
                    'crop_x' => 150,
                    'crop_y' => 0,
                    'crop_width' => 600,
                    'crop_height' => 600,
                    'zoom' => 1.25,
                    'offset_x' => 4.5,
                    'offset_y' => -3.25,
                    'rotation' => 0,
                    'clip' => 'circle',
                    'layer' => 0,
                ], $slotOverrides),
                array_replace([
                    'id' => 'dino-03',
                    'crop_x' => 150,
                    'crop_y' => 0,
                    'crop_width' => 600,
                    'crop_height' => 600,
                    'zoom' => 1,
                    'offset_x' => 0,
                    'offset_y' => 0,
                    'rotation' => 0,
                    'clip' => 'circle',
                    'layer' => 2,
                ], $slotOverrides),
            ],
        ];
    }

    protected function savePhoto($customer, CustomDesignDraft $draft, array $photo)
    {
        return $this->actingAs($customer->user)->putJson(route('custom-designs.update', $draft), [
            'product_id' => $draft->product_id,
            'specification' => ['quantity' => 1],
            'design' => [
                'front' => ['elements' => []],
                'back' => ['elements' => []],
                'photo' => $photo,
            ],
        ])->assertOk();
    }

    public function test_photo_slots_are_stamped_with_template_geometry_and_clip_edges(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->photoProduct();
        $draft = $this->createDraft($customer, $product);
        $asset = $this->uploadPhotoAsset($customer, $draft);

        $response = $this->savePhoto($customer, $draft, $this->photoPayload($asset->id, [
            // A tampered client must not be able to move the frame or change its clip.
            'frame_x' => 1,
            'frame_y' => 2,
            'frame_width' => 3,
            'frame_height' => 4,
            'clip' => 'rect',
            'rotation' => 90,
            'layer' => 99,
            'clip_edge' => 5,
        ]));

        $response->assertJsonPath('design.photo.asset_id', $asset->id)
            ->assertJsonPath('design.photo.template', 'kertas-kado-custom')
            ->assertJsonPath('design.photo.template_version', 1)
            ->assertJsonPath('design.photo.src', route('custom-designs.show', $asset, absolute: false))
            ->assertJsonPath('design.photo.slots.0.id', 'dino-01')
            ->assertJsonPath('design.photo.slots.0.frame_x', 239.5)
            ->assertJsonPath('design.photo.slots.0.frame_y', 205.5)
            ->assertJsonPath('design.photo.slots.0.frame_width', 68)
            ->assertJsonPath('design.photo.slots.0.frame_height', 68)
            ->assertJsonPath('design.photo.slots.0.clip', 'circle')
            ->assertJsonPath('design.photo.slots.0.rotation', 0)
            ->assertJsonPath('design.photo.slots.0.layer', 0)
            ->assertJsonPath('design.photo.slots.0.clip_edge', 211)
            ->assertJsonPath('design.photo.slots.1.id', 'dino-03')
            ->assertJsonPath('design.photo.slots.1.clip_edge', null)
            ->assertJsonPath('design.photo.slots.1.offset_x', 0)
            ->assertJsonPath('design.photo.slots.1.offset_y', 0);

        $stored = $draft->fresh()->design['photo'];
        $this->assertSame($asset->id, $stored['asset_id']);
        $this->assertSame(900, $stored['source_width']);
        $this->assertSame(600, $stored['source_height']);
        $this->assertTrue($stored['face_detected']);
        $this->assertSame(1, $stored['face_count']);
        $this->assertSame(0.28, $stored['face_padding']);
        $this->assertSame(4.5, $stored['slots'][0]['offset_x']);
        $this->assertSame(-3.25, $stored['slots'][0]['offset_y']);
        $this->assertSame(1.25, $stored['slots'][0]['zoom']);
        $this->assertSame(211.0, (float) $stored['slots'][0]['clip_edge']);
        $this->assertNull($stored['slots'][1]['clip_edge']);
        $this->assertSame([$asset->id], $draft->fresh()->referencedAssetIds());
    }

    public function test_unknown_frame_ids_are_rejected(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->photoProduct();
        $draft = $this->createDraft($customer, $product);
        $asset = $this->uploadPhotoAsset($customer, $draft);

        $photo = $this->photoPayload($asset->id);
        $photo['slots'][0]['id'] = 'dino-99';

        $this->actingAs($customer->user)->putJson(route('custom-designs.update', $draft), [
            'product_id' => $draft->product_id,
            'specification' => ['quantity' => 1],
            'design' => ['photo' => $photo],
        ])->assertStatus(422)->assertJsonValidationErrors('design.photo.slots.0.id');
    }

    public function test_photo_for_a_product_without_a_template_is_rejected(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();
        $draft = $this->createDraft($customer, $product);
        $asset = $this->uploadPhotoAsset($customer, $draft);

        $this->actingAs($customer->user)->putJson(route('custom-designs.update', $draft), [
            'product_id' => $draft->product_id,
            'specification' => ['quantity' => 1],
            'design' => ['photo' => $this->photoPayload($asset->id)],
        ])->assertStatus(422)->assertJsonValidationErrors('design.photo');
    }

    public function test_photo_cannot_reference_another_customers_asset(): void
    {
        Storage::fake('local');
        $owner = $this->makeCustomer();
        $intruder = $this->makeCustomer();
        $product = $this->photoProduct();
        $ownerDraft = $this->createDraft($owner, $product);
        $intruderDraft = $this->createDraft($intruder, $product);
        $intruderAsset = $this->uploadPhotoAsset($intruder, $intruderDraft);

        $this->actingAs($owner->user)->putJson(route('custom-designs.update', $ownerDraft), [
            'product_id' => $ownerDraft->product_id,
            'specification' => ['quantity' => 1],
            'design' => ['photo' => $this->photoPayload($intruderAsset->id)],
        ])->assertStatus(422)->assertJsonValidationErrors('design.photo.asset_id');
    }

    public function test_photo_asset_is_retained_while_referenced_and_removed_when_replaced(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->photoProduct();
        $draft = $this->createDraft($customer, $product);
        $first = $this->uploadPhotoAsset($customer, $draft);
        $firstPath = $first->path;

        $this->savePhoto($customer, $draft, $this->photoPayload($first->id));
        Storage::disk('local')->assertExists($firstPath);
        $this->assertNotNull(CustomDesignAsset::find($first->id));

        // Upload after the first save, matching the editor flow: the previous asset
        // becomes unreferenced only once the replacement is persisted.
        $second = $this->uploadPhotoAsset($customer, $draft);
        $this->savePhoto($customer, $draft, $this->photoPayload($second->id));

        $this->assertNull(CustomDesignAsset::find($first->id));
        Storage::disk('local')->assertMissing($firstPath);
        $this->assertNotNull(CustomDesignAsset::find($second->id));
        $this->assertSame([$second->id], $draft->fresh()->referencedAssetIds());
    }

    public function test_photo_can_be_removed_and_its_asset_is_cleaned_up(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->photoProduct();
        $draft = $this->createDraft($customer, $product);
        $asset = $this->uploadPhotoAsset($customer, $draft);
        $path = $asset->path;

        $this->savePhoto($customer, $draft, $this->photoPayload($asset->id));
        $this->assertNotNull(CustomDesignAsset::find($asset->id));

        $this->actingAs($customer->user)->putJson(route('custom-designs.update', $draft), [
            'product_id' => $draft->product_id,
            'specification' => ['quantity' => 1],
            'design' => [
                'front' => ['elements' => []],
                'back' => ['elements' => []],
            ],
        ])->assertOk()->assertJsonMissingPath('design.photo');

        $this->assertNull(CustomDesignAsset::find($asset->id));
        Storage::disk('local')->assertMissing($path);
        $this->assertSame([], $draft->fresh()->referencedAssetIds());
    }

    public function test_reopening_the_editor_exposes_the_authorised_photo_url(): void
    {
        Storage::fake('local');
        $customer = $this->makeCustomer();
        $product = $this->photoProduct();
        $draft = $this->createDraft($customer, $product);
        $asset = $this->uploadPhotoAsset($customer, $draft);
        $this->savePhoto($customer, $draft, $this->photoPayload($asset->id));

        $assetUrl = route('custom-designs.show', $asset, absolute: false);

        $this->actingAs($customer->user)
            ->get(route('products.customize', ['product' => $product->slug, 'draft' => $draft->id]))
            ->assertOk()
            ->assertSee($draft->id, false)
            // The editor bootstrap is JSON inside an HTML attribute, so slashes are escaped.
            ->assertSee(json_encode($assetUrl), false)
            ->assertSee('dino-01', false)
            ->assertSee('clip_edge', false)
            ->assertDontSee($asset->path, false);
    }
}
