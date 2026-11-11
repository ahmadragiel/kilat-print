@extends('layouts.app')

@section('title', 'Editor Desain '.data_get($product, 'name', 'Produk'))

@section('content')
@php
    $initialResponse = $estimate ?? $priceEstimate ?? null;
    $initialAmount = data_get($initialResponse, 'total', data_get($initialResponse, 'estimate', data_get($initialResponse, 'price')));
    $endpoint = route('products.price', $product);
    $specification = $initialSpecification ?? [];
    $variation = method_exists($product, 'variationDefinition') ? $product->variationDefinition() : null;
    $defaultVariant = method_exists($product, 'defaultVariant') ? $product->defaultVariant() : null;
    $needsDimensions = collect(data_get($product, 'priceRules', []))->contains(function ($rule) {
        $type = data_get($rule, 'pricing_type');
        $value = $type instanceof \BackedEnum ? $type->value : $type;

        return in_array($value, ['per_sqm', 'per_meter'], true);
    });
    // The `{id}` placeholder must survive verbatim so the editor can substitute a real
    // UUID at runtime. The route generator is therefore bypassed on purpose here.
    $customDesignBase = rtrim(url('/custom-designs'), '/');
    $editorConfiguration = [
        'productId' => $product->id,
        'saveEndpoint' => $customDesignBase,
        'updateEndpointTemplate' => $customDesignBase.'/{id}',
        'assetUploadEndpointTemplate' => $customDesignBase.'/{id}/assets',
        'csrfToken' => csrf_token(),
        'initialDraft' => $initialDraft,
        'initialDesign' => $initialDesign,
        'initialSpecification' => $specification,
        'mockups' => $mockups,
        'photoTemplate' => $photoTemplate,
        'stickers' => $stickers->values()->all(),
        'hasBack' => (bool) $hasBackMockup,
        'continueLabel' => $cartItem ? 'Perbarui ke cart' : 'Lanjut ke cart',
        'maxElements' => 80,
        'maxImageBytes' => (int) config('printing.custom_design_max_kilobytes', 5120) * 1024,
    ];
@endphp

<div class="page-shell py-6 sm:py-8">
    <nav class="mb-5 flex flex-wrap items-center gap-2 text-sm text-ink-500" aria-label="Breadcrumb">
        <a href="{{ route('products.index') }}" class="breadcrumb-link">Katalog</a>
        <x-icon name="chevron-right" class="h-3.5 w-3.5" />
        <a href="{{ route('products.show', $product) }}" class="breadcrumb-link">{{ $product->name }}</a>
        <x-icon name="chevron-right" class="h-3.5 w-3.5" />
        <span class="font-medium text-brand-700">Editor desain</span>
    </nav>

    <div x-data='designEditor(@json($editorConfiguration, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP))'>
    <x-page-header
        :title="$cartItem ? 'Edit konfigurasi & desain' : 'Rancang produk Anda'"
        :description="'Rancang ' . $product->name . ' dengan preview depan' . ($hasBackMockup ? ' dan belakang' : '') . '.' "
        eyebrow="Editor desain"
    >
        <x-slot:actions>
            <div x-cloak x-show="hasUnsavedChanges" class="inline-flex items-center gap-2 rounded-full bg-accent-100 px-3 py-1.5 text-xs font-bold text-accent-800 ring-1 ring-inset ring-accent-300">
                <span class="h-2 w-2 rounded-full bg-accent-500"></span> Belum disimpan
            </div>
            <x-button type="button" variant="outline" x-on:click="saveDraft()" x-bind:disabled="saving">
                <x-icon name="check" class="h-4 w-4" /> Simpan desain
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <form
        method="post"
        action="{{ route('cart.store') }}"
        enctype="multipart/form-data"
        x-on:submit="prepareCartSubmission($event)"
        x-ref="productForm"
        class="mt-6 space-y-5"
        aria-label="Form konfigurasi dan editor desain produk"
    >
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <input type="hidden" name="design_draft_id" x-ref="designDraftId" value="{{ $initialDraft['id'] ?? '' }}">
        @if($cartItem)
            <input type="hidden" name="editing_cart_item_id" value="{{ $cartItem->id }}">
        @endif
        <input name="design_file" x-ref="previewInput" type="file" accept="image/png" class="sr-only" tabindex="-1" aria-hidden="true">
        <input type="file" x-ref="imageInput" accept="image/jpeg,image/png" class="sr-only" tabindex="-1" aria-hidden="true">
        <input type="file" x-ref="photoInput" accept="image/jpeg,image/png" class="sr-only" tabindex="-1" aria-hidden="true">

        <section class="overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-panel" aria-label="Workspace desain">
            {{-- TOP TOOLBAR: white surface, brand red active states --}}
            <div class="design-toolbar flex items-center gap-2 overflow-x-auto border-b border-ink-200 bg-white px-3 py-3 sm:px-5">
                <div x-show="!photoTemplate" class="flex shrink-0 items-center rounded-lg bg-ink-100 p-1" role="group" aria-label="Sisi produk">
                    <button type="button" class="editor-toolbar-tab" x-on:click="switchSide('front')" x-bind:class="currentSide === 'front' && 'editor-toolbar-tab-active'" :aria-pressed="currentSide === 'front'">
                        <x-icon name="image" class="h-4 w-4" /> Depan
                    </button>
                    <button type="button" class="editor-toolbar-tab" x-on:click="switchSide('back')" x-bind:class="currentSide === 'back' && 'editor-toolbar-tab-active'" x-bind:disabled="!hasBack" :aria-pressed="currentSide === 'back'">
                        <x-icon name="refresh" class="h-4 w-4" /> Belakang
                    </button>
                </div>

                <div x-show="!photoTemplate" class="h-7 w-px shrink-0 bg-ink-200"></div>

                <div class="flex shrink-0 items-center gap-1" role="group" aria-label="Mode tampilan">
                    <button type="button" class="editor-icon-button" x-on:click="previewMode = false" x-bind:class="!previewMode && 'editor-icon-button-active'" aria-label="Mode edit">
                        <x-icon name="edit" class="h-4 w-4" />
                    </button>
                    <button type="button" class="editor-icon-button text-accent-600 hover:bg-accent-100 hover:text-accent-800" x-on:click="toggleMode()" x-bind:class="previewMode && 'editor-icon-button-active'" aria-label="Mode preview">
                        <x-icon name="eye" class="h-4 w-4" />
                    </button>
                </div>

                <div x-show="!photoTemplate" class="ml-auto flex shrink-0 items-center gap-1" role="group" aria-label="Zoom viewport">
                    <button type="button" class="editor-icon-button" x-on:click="zoomOut()" aria-label="Perkecil viewport"><span class="text-lg">−</span></button>
                    <span class="min-w-14 text-center text-xs font-bold text-ink-600" x-text="zoomPercent + '%'">100%</span>
                    <button type="button" class="editor-icon-button" x-on:click="zoomIn()" aria-label="Perbesar viewport"><span class="text-lg">+</span></button>
                    <button type="button" class="editor-toolbar-button" x-on:click="resetZoom()"><x-icon name="refresh" class="h-4 w-4" /> Atur ulang</button>
                </div>
                <p x-show="photoTemplate" class="ml-auto text-xs font-semibold text-ink-500">Geser foto di dalam frame untuk mengatur potongan.</p>
            </div>

            <div class="grid items-start gap-0 xl:grid-cols-[minmax(0,1fr)_380px]">
                {{-- CANVAS AREA: stays light gray so the white design reads clearly --}}
                <div class="min-w-0 border-b border-ink-200 bg-ink-100 p-3 sm:p-5 xl:border-b-0 xl:border-r">
                    <div
                        class="design-canvas-host relative mx-auto w-full max-w-5xl overflow-hidden rounded-xl border border-ink-300 bg-white shadow-panel"
                        x-bind:class="photoTemplate && 'design-canvas-host-square'"
                        x-bind:style="!photoTemplate && mockups[currentSide] ? `background-image: url('${mockups[currentSide]}')` : ''"
                    >
                        <div class="pointer-events-none absolute inset-0 grid place-items-center" x-show="!mockups[currentSide]">
                            <div class="max-w-xs text-center">
                                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-brand-50 text-brand-600"><x-icon name="image" class="h-7 w-7" /></span>
                                <p class="mt-3 text-sm font-bold text-ink-800">Mockup belum diatur</p>
                                <p class="mt-1 text-xs leading-5 text-ink-500">Admin dapat menambahkan mockup produk. Area tetap dapat didesain.</p>
                            </div>
                        </div>
                        <canvas x-ref="canvas" aria-label="Canvas desain produk"></canvas>
                        <div class="pointer-events-none absolute left-3 top-3 rounded-full bg-ink-950/75 px-3 py-1.5 text-[11px] font-bold text-white backdrop-blur" x-text="previewMode ? 'Preview ' + (currentSide === 'front' ? 'Depan' : 'Belakang') : 'Mode edit'"></div>
                        <div class="pointer-events-none absolute bottom-3 right-3 rounded-full bg-accent-400 px-2.5 py-1 text-[10px] font-black text-ink-950 shadow-sm" x-show="!previewMode" x-text="selected ? selectedLabel : 'Pilih elemen'"></div>
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-ink-600">
                        <p x-show="!photoTemplate">Tarik elemen untuk memindahkan. Gunakan handle untuk mengubah ukuran atau memutar.</p>
                        <p x-show="photoTemplate">Pilih salah satu frame di kanvas, lalu geser foto untuk mengatur potongan wajah.</p>
                        <p class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Pratinjau tersimpan di browser sampai Anda menyimpan desain</p>
                    </div>
                </div>

                {{-- EDITOR PANEL: white card, brand red controls --}}
                <aside class="max-h-none overflow-y-auto bg-white xl:max-h-[780px]" aria-label="Kontrol editor desain">
                    <div class="border-b border-ink-200 bg-linear-to-r from-brand-600 to-brand-700 px-5 py-4 text-white">
                        <p class="text-2xs font-extrabold tracking-[0.18em] text-accent-300 uppercase">Editor</p>
                        <h2 class="mt-1 text-lg font-extrabold">Tambah & atur elemen</h2>
                    </div>

                    <div class="space-y-6 p-5" x-show="!previewMode">
                        <section x-show="photoTemplate" x-cloak class="rounded-xl border border-accent-200 bg-accent-50/70 p-4">
                            <div class="flex items-start gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-accent-200 text-accent-900"><x-icon name="image" class="h-5 w-5" /></span>
                                <div class="min-w-0">
                                    <p class="editor-control-label">Foto otomatis</p>
                                    <p class="mt-1 text-xs leading-5 text-ink-600">Satu foto dipakai untuk semua frame. Deteksi wajah berjalan di browser Anda.</p>
                                </div>
                            </div>

                            <div x-show="!hasPhoto" class="mt-4">
                                <button type="button" class="editor-secondary-button w-full justify-center" x-on:click="openPhotoPicker()" x-bind:disabled="photoDetecting || uploading || previewMode">
                                    <x-icon name="upload" class="h-4 w-4" />
                                    <span x-text="photoDetecting ? 'Mendeteksi wajah…' : (uploading ? 'Mengunggah…' : 'Unggah foto wajah')"></span>
                                </button>
                                <p class="mt-2 text-[11px] leading-4 text-ink-500">JPG atau PNG. Foto hanya diproses di perangkat ini dan tidak dikirim ke layanan wajah pihak ketiga.</p>
                            </div>

                            <div x-show="hasPhoto" x-cloak class="mt-4 space-y-3">
                                <div class="flex items-center gap-3">
                                    <img :src="photoSrc" alt="Pratinjau foto sumber" class="h-14 w-14 shrink-0 rounded-lg border border-ink-200 bg-white object-cover">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold text-ink-900" x-text="photoStatusLabel"></p>
                                        <p class="mt-0.5 text-xs text-ink-500" x-text="photoFrameLabel"></p>
                                    </div>
                                </div>

                                <p x-show="photoWarning" x-cloak class="rounded-lg border border-accent-300 bg-accent-100 px-3 py-2 text-xs font-semibold leading-5 text-accent-900" role="status" x-text="photoWarning"></p>

                                <label class="block">
                                    <span class="flex items-center justify-between gap-3 text-xs font-semibold text-ink-600"><span>Zoom foto</span><output class="font-bold text-ink-900" x-text="photoZoomPercent + '%'"></output></span>
                                    <input type="range" min="100" max="300" step="5" class="mt-2 w-full accent-brand-600" :value="photoZoomPercent" x-on:input="setPhotoZoom(Number($event.target.value))" :disabled="!hasPhoto || previewMode" aria-label="Zoom foto pada frame terpilih">
                                </label>

                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" class="editor-secondary-button justify-center" x-on:click="selectPhotoFrame(-1)" :disabled="photoFrameCount < 2">Frame sebelumnya</button>
                                    <button type="button" class="editor-secondary-button justify-center" x-on:click="selectPhotoFrame(1)" :disabled="photoFrameCount < 2">Frame berikutnya</button>
                                    <button type="button" class="editor-secondary-button justify-center" x-on:click="resetPhotoFrame()">Atur ulang frame</button>
                                    <button type="button" class="editor-secondary-button justify-center" x-on:click="applyPhotoToAll()">Terapkan ke semua</button>
                                </div>

                                <button type="button" class="editor-secondary-button w-full justify-center" x-on:click="openPhotoPicker()" x-bind:disabled="photoDetecting || uploading || previewMode">
                                    <x-icon name="refresh" class="h-4 w-4" /> <span x-text="uploading ? 'Mengunggah…' : 'Ganti foto'"></span>
                                </button>
                                <p x-show="photoDetecting" x-cloak class="text-xs font-semibold text-ink-600" role="status">Mendeteksi wajah di perangkat…</p>
                            </div>
                        </section>

                        <section>
                            <p class="editor-control-label">Tambah elemen</p>
                            <div class="mt-3 grid grid-cols-3 gap-2">
                                <button type="button" class="editor-action-card" x-on:click="openImagePicker()">
                                    <x-icon name="upload" class="h-5 w-5" /><span>Unggah gambar</span>
                                </button>
                                <button type="button" class="editor-action-card" x-on:click="addText()">
                                    <span class="text-lg font-black">T</span><span>Tambah teks</span>
                                </button>
                                <button type="button" class="editor-action-card" x-on:click="toggleStickerPicker()">
                                    <x-icon name="sparkles" class="h-5 w-5" /><span>Stiker</span>
                                </button>
                            </div>
                        </section>

                        <section x-show="stickerPickerOpen" x-transition x-cloak>
                            <div class="flex items-center justify-between gap-3">
                                <p class="editor-control-label">Pilih stiker</p>
                                <button type="button" class="text-xs font-bold text-ink-500 transition hover:text-ink-900" x-on:click="stickerPickerOpen = false">Tutup</button>                            </div>
                            <div class="mt-3 grid grid-cols-4 gap-2">
                                <template x-for="sticker in stickers" :key="sticker.key">
                                    <button type="button" class="grid aspect-square place-items-center rounded-xl border border-ink-200 bg-white p-2 transition hover:border-brand-400 hover:bg-brand-50" x-on:click="addSticker(sticker)" :title="sticker.name">
                                        <img :src="sticker.url" :alt="sticker.name" class="h-full w-full object-contain">
                                    </button>
                                </template>
                            </div>
                        </section>

                        <section x-show="selected" x-transition x-cloak>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="editor-control-label">Elemen dipilih</p>
                                    <p class="mt-1 text-xs text-ink-500" x-text="selectedLabel"></p>
                                </div>
                                <button type="button" class="grid h-11 w-11 place-items-center rounded-lg bg-danger-50 text-danger-600 transition hover:bg-danger-100" x-on:click="deleteSelected()" aria-label="Hapus elemen"><x-icon name="trash" class="h-4 w-4" /></button>
                            </div>

                            <div x-show="selectedType === 'text'" class="mt-4 space-y-3">
                                <label class="block"><span class="editor-control-label">Isi teks</span><textarea rows="2" class="form-control mt-1.5" :value="selected?.text || ''" x-on:input="updateSelectedText($event.target.value)"></textarea></label>
                                <div class="grid grid-cols-[1fr_92px] gap-2">
                                    <label><span class="editor-control-label">Font</span><select class="form-control mt-1.5" :value="selected?.fontFamily || 'Arial'" x-on:change="updateSelectedFontFamily($event.target.value)"><option value="Arial">Arial</option><option value="Georgia">Georgia</option><option value="Courier New">Courier</option><option value="Verdana">Verdana</option><option value="Trebuchet MS">Trebuchet</option></select></label>
                                    <label><span class="editor-control-label">Ukuran</span><input type="number" min="8" max="180" class="form-control mt-1.5" :value="selected?.fontSize || 32" x-on:input="updateSelectedFontSize(Number($event.target.value))"></label>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button type="button" class="editor-mini-button" x-on:click="toggleBold()" x-bind:class="(selected?.fontWeight || 'normal') === 'bold' && 'editor-mini-button-active'"><strong>B</strong></button>
                                    <button type="button" class="editor-mini-button" x-on:click="toggleItalic()" x-bind:class="selected?.fontStyle === 'italic' && 'editor-mini-button-active'"><em>I</em></button>
                                    <div class="ml-1 flex rounded-lg border border-ink-200 p-0.5">
                                        <template x-for="alignment in ['left','center','right']" :key="alignment"><button type="button" class="editor-mini-button border-0" x-on:click="updateSelectedAlign(alignment)" x-bind:class="(selected?.textAlign || 'left') === alignment && 'editor-mini-button-active'" :aria-label="alignment === 'left' ? 'Rata kiri' : (alignment === 'center' ? 'Rata tengah' : 'Rata kanan')"><span x-text="alignment === 'left' ? '≡' : (alignment === 'center' ? '☷' : '≡')"></span></button></template>
                                    </div>
                                    <label class="ml-auto flex items-center gap-2 text-xs font-semibold text-ink-600">Warna<input type="color" class="h-9 w-10 cursor-pointer rounded border border-ink-200 bg-white p-1" :value="selected?.fill || '#10151c'" x-on:input="updateSelectedColor($event.target.value)"></label>
                                </div>
                            </div>

                            <div x-show="selectedType === 'image'" class="mt-4">
                                <p class="editor-control-label">Gambar</p>
                                <div class="mt-2 grid grid-cols-2 gap-2">
                                    <button type="button" class="editor-secondary-button" x-on:click="replaceSelectedImage()"><x-icon name="refresh" class="h-4 w-4" /> Ganti</button>
                                    <button type="button" class="editor-secondary-button" x-on:click="selectedZoomOut()">Perkecil</button>
                                    <button type="button" class="editor-secondary-button" x-on:click="selectedZoomIn()">Perbesar</button>
                                    <button type="button" class="editor-secondary-button" x-on:click="rotateSelected(90)">Putar 90°</button>
                                    <button type="button" class="editor-secondary-button" x-on:click="flipSelectedHorizontal()">Cermin horizontal</button>
                                    <button type="button" class="editor-secondary-button" x-on:click="flipSelectedVertical()">Cermin vertikal</button>
                                </div>
                                <p class="mt-2 text-[11px] leading-4 text-ink-500">Pemotongan gambar belum tersedia. Sesuaikan ukuran lewat handle di kanvas.</p>
                            </div>

                            <div class="mt-4 space-y-3 border-t border-ink-100 pt-4">
                                <label class="grid grid-cols-[74px_1fr_48px] items-center gap-2 text-xs font-semibold text-ink-600"><span>Rotasi</span><input type="range" min="-180" max="180" step="1" class="accent-brand-600" :value="selected?.angle || selected?.rotation || 0" x-on:input="updateSelectedRotation(Number($event.target.value))"><output x-text="Math.round(selected?.angle || selected?.rotation || 0) + '°'">0°</output></label>
                                <label class="grid grid-cols-[74px_1fr_48px] items-center gap-2 text-xs font-semibold text-ink-600"><span>Opasitas</span><input type="range" min="0.1" max="1" step="0.05" class="accent-brand-600" :value="selected?.opacity ?? 1" x-on:input="updateSelectedOpacity(Number($event.target.value))"><output x-text="Math.round((selected?.opacity ?? 1) * 100) + '%'">100%</output></label>
                                <div class="grid grid-cols-4 gap-1.5">
                                    <button type="button" class="editor-layer-button" x-on:click="bringToFront()" title="Paling depan" aria-label="Pindahkan ke lapisan paling depan"><x-icon name="arrow-right" class="h-4 w-4 rotate-[-45deg]" /></button>
                                    <button type="button" class="editor-layer-button" x-on:click="bringForward()" title="Maju satu lapisan" aria-label="Majukan satu lapisan"><x-icon name="chevron-right" class="h-4 w-4" /></button>
                                    <button type="button" class="editor-layer-button" x-on:click="sendBackward()" title="Mundur satu lapisan" aria-label="Mundurkan satu lapisan"><x-icon name="chevron-right" class="h-4 w-4 rotate-180" /></button>
                                    <button type="button" class="editor-layer-button" x-on:click="sendToBack()" title="Paling belakang" aria-label="Pindahkan ke lapisan paling belakang"><x-icon name="arrow-right" class="h-4 w-4 rotate-[135deg]" /></button>
                                </div>
                            </div>
                        </section>

                        <section>
                            <div class="flex items-center justify-between gap-3">
                                <p class="editor-control-label">Susunan / Layer</p>
                                <span class="text-xs text-ink-500" x-text="layers.length + ' elemen'">0 elemen</span>
                            </div>
                            <div class="mt-3 max-h-56 space-y-1.5 overflow-y-auto pr-1" role="list" aria-label="Daftar layer desain">
                                <template x-for="layer in layers" :key="layer.id">
                                    <div class="flex items-center gap-2 rounded-lg border border-ink-200 bg-white px-2.5 py-2" role="listitem">
                                        <button type="button" class="min-w-0 flex-1 truncate text-left text-xs font-semibold text-ink-700 transition hover:text-brand-700" x-on:click="selectLayer(layer.id)" x-text="layer.label"></button>
                                        <button type="button" class="grid h-11 w-11 shrink-0 place-items-center rounded-lg text-ink-500 transition hover:bg-ink-100 hover:text-ink-900" x-on:click="toggleLayerVisibility(layer.id)" :aria-label="layer.visible ? 'Sembunyikan layer' : 'Tampilkan layer'"><span x-text="layer.visible ? '◉' : '○'"></span></button>
                                    </div>
                                </template>
                                <p x-show="layers.length === 0" class="rounded-lg border border-dashed border-ink-300 px-3 py-5 text-center text-xs text-ink-500">Belum ada elemen desain.</p>
                            </div>
                        </section>
                    </div>

                    <div x-show="previewMode" x-cloak class="p-8 text-center">
                        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-accent-100 text-accent-700"><x-icon name="eye" class="h-7 w-7" /></span>
                        <h3 class="mt-4 font-extrabold text-ink-950">Preview bersih</h3>
                        <p class="mt-2 text-sm leading-6 text-ink-500">Bounding box dan kontrol disembunyikan. Pilih mode edit untuk melanjutkan.</p>
                        <button type="button" class="mt-5 text-sm font-bold text-brand-700 transition hover:text-brand-800" x-on:click="toggleMode()">Kembali ke edit</button>
                    </div>

                    <div class="border-t border-ink-200 p-5" x-data='priceEstimator({ endpoint: @json($endpoint, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), estimate: @json($initialAmount, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), response: @json($initialResponse, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) })' x-init="fetchEstimate()">
                        <p class="section-kicker">Estimasi harga</p>
                        <div class="mt-2 flex items-end justify-between gap-4">
                            <div>
                                <p class="text-xs text-ink-500">Total estimasi</p>
                                <p class="mt-1 text-2xl font-black text-brand-700" x-text="formatAmount(estimate)">Belum dihitung</p>
                            </div>
                            <x-icon name="calculator" class="h-7 w-7 text-accent-500" />
                        </div>
                        <dl class="mt-3 space-y-1.5 border-t border-ink-100 pt-3 text-sm" x-show="response" x-cloak>
                            <div class="flex items-center justify-between gap-3 text-ink-600">
                                <dt>Jumlah</dt>
                                <dd class="font-semibold text-ink-900" x-text="(response?.quantity ?? 0) + ' item'"></dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-ink-600">
                                <dt>Harga per item</dt>
                                <dd class="font-semibold text-ink-900" x-text="formatAmount(response?.unit_price)"></dd>
                            </div>
                            <div class="flex items-center justify-between gap-3 text-ink-600">
                                <dt>Subtotal</dt>
                                <dd class="font-semibold text-ink-900" x-text="formatAmount(response?.total)"></dd>
                            </div>
                        </dl>
                        <p class="mt-3 text-xs leading-5 text-ink-500">Estimasi belum termasuk ongkos kirim. Harga akhir dihitung ulang oleh server saat masuk keranjang dan checkout.</p>
                        <div x-show="error" x-cloak x-transition class="mt-3"><x-alert type="error" x-text="error"></x-alert></div>
                        <button type="button" class="editor-secondary-button mt-4 w-full justify-center" x-on:click="fetchEstimate()" x-bind:disabled="loading"><span x-text="loading ? 'Menghitung…' : 'Hitung ulang harga'"></span></button>
                    </div>
                </aside>
            </div>
        </section>

        <section class="panel p-5 sm:p-7" aria-labelledby="product-specification-title">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="section-kicker">Spesifikasi</p><h2 id="product-specification-title" class="mt-1 text-xl font-extrabold text-ink-950">Detail pesanan</h2></div>
                <p class="text-xs text-ink-500">Harga tetap dihitung ulang oleh server saat cart/checkout.</p>
            </div>
            <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <x-input name="quantity" label="Jumlah" type="number" :value="data_get($specification, 'quantity', $product->minimum_order)" :min="max(1, (int) $product->minimum_order)" required :help="'Minimal '.max(1, (int) $product->minimum_order).' item.'" />
                @if ($variation)
                    <x-select
                        name="variant"
                        :label="$variation['label']"
                        :value="data_get($specification, 'variant', $variation['required'] ? $defaultVariant : null)"
                        :placeholder="$variation['required'] ? null : 'Tidak memilih'"
                        :required="$variation['required']"
                        :help="'Pilih '.strtolower($variation['label']).($variation['required'] ? ' yang diinginkan.' : ' bila diperlukan.')"
                    >
                        @foreach ($variation['options'] as $option)
                            <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                        @endforeach
                    </x-select>
                @else
                    <x-input name="size" label="Ukuran / orientasi" :value="data_get($specification, 'size')" placeholder="Contoh: A4" />
                @endif
                @if ($needsDimensions)
                    <x-input name="length_cm" label="Panjang (cm)" type="number" step="0.01" min="0" :value="data_get($specification, 'length_cm')" placeholder="Masukkan panjang" />
                    <x-input name="width_cm" label="Lebar (cm)" type="number" step="0.01" min="0" :value="data_get($specification, 'width_cm')" placeholder="Masukkan lebar" />
                @endif
                <x-input name="color" label="Warna" :value="data_get($specification, 'color')" placeholder="Contoh: Merah" />
                <div class="sm:col-span-2 lg:col-span-3"><x-textarea name="notes" label="Catatan produksi" :value="data_get($specification, 'notes')" :rows="3" placeholder="Instruksi tambahan untuk tim produksi" /></div>
            </div>
        </section>

        <div x-cloak x-show="error" x-transition class="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm font-semibold text-danger-700" role="alert" x-text="error"></div>
        <div x-show="statusMessage" x-transition class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-700" role="status" x-text="statusMessage"></div>

        {{-- Sticky action bar — "Lanjutkan" is the yellow high-visibility CTA --}}
        <div class="sticky bottom-3 z-30 flex flex-col gap-3 rounded-2xl border border-ink-200 bg-white/95 p-3 shadow-panel-lg backdrop-blur sm:flex-row sm:items-center sm:justify-between sm:p-4">
            <div class="min-w-0">
                <p class="text-sm font-extrabold text-ink-950">{{ $cartItem ? 'Perbarui konfigurasi' : 'Lanjutkan dengan desain ini' }}</p>
                <p class="mt-0.5 truncate text-xs text-ink-500">Desain, spesifikasi, dan catatan akan disimpan ke pesanan Anda.</p>
            </div>
            <x-button type="submit" variant="accent" data-design-editor-action="continue" class="w-full sm:w-auto" x-bind:disabled="continuing">
                <span x-text="continuing ? 'Menyimpan…' : continueLabel"></span>
                <x-icon name="arrow-right" class="h-4 w-4" />
            </x-button>
        </div>
    </form>

    <div
        x-show="faceChooserOpen"
        x-cloak
        x-on:keydown.escape.window="cancelFaceChooser()"
        class="fixed inset-0 z-[70] grid place-items-center overflow-y-auto bg-ink-950/60 p-4 backdrop-blur-sm"
        role="dialog"
        aria-modal="true"
        aria-labelledby="face-chooser-title"
    >
        <div class="w-full max-w-lg rounded-2xl border border-ink-200 bg-white p-5 shadow-panel-lg sm:p-6" x-on:click.outside="cancelFaceChooser()">
            <p class="section-kicker">Deteksi wajah</p>
            <h2 id="face-chooser-title" class="mt-1 text-lg font-extrabold text-ink-950">Pilih wajah yang dipakai</h2>
            <p class="mt-2 text-sm leading-6 text-ink-600">Foto ini berisi lebih dari satu wajah. Pilih satu wajah untuk dipasang ke seluruh frame, atau gunakan potongan tengah.</p>

            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                <template x-for="(face, index) in faceCandidates" :key="face.id">
                    <button type="button" class="group overflow-hidden rounded-xl border border-ink-200 bg-white p-1.5 text-left transition hover:border-brand-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600" x-on:click="chooseFace(face.id)">
                        <img :src="face.thumbnail" alt="" class="aspect-square w-full rounded-lg bg-ink-100 object-cover">
                        <span class="mt-2 block px-1 pb-1 text-xs font-bold text-ink-700 group-hover:text-brand-700">Wajah <span x-text="index + 1"></span></span>
                    </button>
                </template>
            </div>

            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <button type="button" class="editor-secondary-button justify-center" x-on:click="cancelFaceChooser()">Gunakan potongan tengah</button>
            </div>
        </div>
    </div>
    </div>
</div>

@endsection
