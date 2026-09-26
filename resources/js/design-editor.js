/**
 * Fabric is loaded on demand.
 *
 * The canvas library is only needed on the product customiser page, so it is pulled in
 * as a separate lazy chunk the first time the editor mounts. Every other page ships a
 * bundle without Fabric at all.
 */
/**
 * Unwrap an Alpine reactive proxy.
 *
 * Alpine deeply proxies objects returned from its own accessors, so `this.selected`
 * would hand Fabric a *proxy* of the canvas object. Fabric's collection helpers rely on
 * reference identity (`_objects.indexOf(object)`), so a proxied object silently fails to
 * match and the layer operations become no-ops. Every read of a Fabric object from the
 * reactive surface must therefore be unwrapped first.
 */
function unwrapReactive(value) {
    const raw = globalThis.Alpine?.raw;

    return typeof raw === 'function' && value != null ? raw(value) : value;
}

let fabricLibrary = null;
let fabricLoading = null;

function loadFabric() {
    if (fabricLibrary !== null) {
        return Promise.resolve(fabricLibrary);
    }

    if (fabricLoading === null) {
        fabricLoading = import('fabric')
            .then((library) => {
                fabricLibrary = library;

                return library;
            })
            .catch(() => {
                fabricLoading = null;

                throw new EditorRequestError(
                    'Editor desain gagal dimuat. Periksa koneksi internet lalu muat ulang halaman.',
                );
            });
    }

    return fabricLoading;
}

const DEFAULT_TEXT = 'Teks Anda';
const DEFAULT_FILL = '#111827';
const DEFAULT_FONT_FAMILY = 'Arial, sans-serif';
const DEFAULT_FONT_SIZE = 32;
const MIN_ZOOM = 0.5;
const MAX_ZOOM = 2;
const ZOOM_STEP = 0.1;
const SESSION_EXPIRED_MESSAGE = 'Sesi Anda telah berakhir. Muat ulang halaman lalu coba kembali.';
const SESSION_STATUSES = new Set([401, 403, 419]);
const FONT_WEIGHTS = new Set(['normal', 'bold']);
const FONT_STYLES = new Set(['normal', 'italic']);
const TEXT_ALIGNMENTS = new Set(['left', 'center', 'right', 'justify']);
const ELEMENT_TYPES = new Set(['image', 'text', 'sticker']);
const CART_ACTION_PATTERN = /^(?:cart|add[-_]?to[-_]?cart|continue(?:[-_]?to[-_]?cart)?|checkout|design[-_]?editor[-_]?continue)$/i;
const CART_URL_PATTERN = /(?:^|\/)(?:cart|checkout)(?:\/|$)|keranjang/i;
const PRICE_KEY_PATTERN = /(?:^|[_.-])(?:price|prices|amount|total|subtotal|estimate|estimated_price|cost|charge)(?:$|[_.-])/i;
const UNSAFE_KEYS = new Set(['__proto__', 'prototype', 'constructor']);
const MAX_TEXT_LENGTH = 5000;
const MAX_FONT_SIZE = 400;
const MIN_FONT_SIZE = 6;
const MIN_SCALE = 0.01;
const MAX_SCALE = 100;
const UPLOAD_TIMEOUT = 60000;
const MAX_SPECIFICATION_DEPTH = 6;

const elementMetadata = new WeakMap();

let idSequence = 0;

class EditorRequestError extends Error {
    constructor(message, status = 0) {
        super(message);
        this.name = 'EditorRequestError';
        this.status = status;
    }
}

function isPlainObject(value) {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) {
        return false;
    }

    const prototype = Object.getPrototypeOf(value);

    return prototype === Object.prototype || prototype === null;
}

function isBrowserInstance(constructorName, value) {
    const constructor = globalThis[constructorName];

    return typeof constructor === 'function' && value instanceof constructor;
}

function clamp(value, minimum, maximum) {
    return Math.min(Math.max(value, minimum), maximum);
}

function roundNumber(value, precision = 4) {
    const factor = 10 ** precision;

    return Math.round(value * factor) / factor;
}

function toFiniteNumber(value) {
    const numericValue = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(numericValue) ? numericValue : null;
}

function toSafeString(value, maximumLength = 255) {
    if (typeof value === 'number' && Number.isFinite(value)) {
        return String(value);
    }

    if (typeof value !== 'string') {
        return null;
    }

    const cleaned = value.replace(/[\u0000-\u001F\u007F]/g, ' ').trim();

    if (cleaned.length === 0 || cleaned.length > maximumLength) {
        return null;
    }

    return cleaned;
}

function toSafeIdentifier(value) {
    const identifier = toSafeString(value, 128);

    if (identifier === null || !/^[A-Za-z0-9_-]+$/.test(identifier)) {
        return null;
    }

    return identifier;
}

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

function isUuidLike(value) {
    return typeof value === 'string' && UUID_PATTERN.test(value);
}

function createElementId() {
    if (typeof globalThis.crypto?.randomUUID === 'function') {
        return `el_${globalThis.crypto.randomUUID()}`;
    }

    idSequence += 1;

    return `el_${Date.now().toString(36)}_${idSequence.toString(36)}`;
}

function isSafeFontFamily(value) {
    const family = toSafeString(value, 160);

    if (family === null || /[<>;{}()@]/.test(family)) {
        return null;
    }

    return family;
}

function isSafeColor(value) {
    if (typeof value !== 'string') {
        return null;
    }

    const color = value.replace(/[\u0000-\u001F\u007F]/g, '').trim();

    if (color.length === 0 || color.length > 64 || /url\s*\(/i.test(color)) {
        return null;
    }

    if (typeof globalThis.CSS?.supports === 'function') {
        try {
            return globalThis.CSS.supports('color', color) ? color : null;
        } catch {
            return null;
        }
    }

    const basicPattern = /^(?:#[0-9a-f]{3,8}|(?:rgb|rgba|hsl|hsla)\([\d\s.,%+/-]+\)|[a-z]+)$/i;

    return basicPattern.test(color) ? color : null;
}

function toSameOriginUrl(value) {
    if (typeof value !== 'string' || value.trim() === '') {
        return null;
    }

    const base = globalThis.location?.href;

    if (typeof base !== 'string') {
        return null;
    }

    if (/^(?:data|blob|javascript):/i.test(value.trim())) {
        return null;
    }

    try {
        const url = new URL(value, base);

        if (!['http:', 'https:'].includes(url.protocol) || url.username || url.password) {
            return null;
        }

        if (url.origin !== globalThis.location.origin) {
            return null;
        }

        return url.href;
    } catch {
        return null;
    }
}

function plainMessage(value, fallback, maximumLength = 300) {
    if (typeof value !== 'string') {
        return fallback;
    }

    const cleaned = value
        .replace(/<[^>]*>/g, ' ')
        .replace(/[\u0000-\u001F\u007F]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .slice(0, maximumLength);

    return cleaned || fallback;
}

function isSessionExpired(status, responseUrl = '', payload = null) {
    if (SESSION_STATUSES.has(Number(status))) {
        return true;
    }

    if (typeof responseUrl === 'string' && typeof globalThis.location?.origin === 'string' && responseUrl.startsWith(globalThis.location.origin)) {
        try {
            const url = new URL(responseUrl);
            const path = `${url.pathname}${url.search}`;

            if (/\/login(?:\/|$|\?)/i.test(path) || /\/expired(?:\/|$|\?)/i.test(path)) {
                return true;
            }
        } catch {
            return false;
        }
    }

    const message = typeof payload?.message === 'string' ? payload.message.toLowerCase() : '';

    return message.includes('csrf') || message.includes('unauthenticated') || message.includes('session expired');
}

function responseMessage(payload, fallback) {
    if (!isPlainObject(payload)) {
        return fallback;
    }

    if (isPlainObject(payload.errors)) {
        for (const value of Object.values(payload.errors)) {
            const message = Array.isArray(value) ? value[0] : value;

            if (typeof message === 'string') {
                return plainMessage(message, fallback);
            }
        }
    }

    if (typeof payload.message === 'string') {
        return plainMessage(payload.message, fallback);
    }

    if (typeof payload.error === 'string') {
        return plainMessage(payload.error, fallback);
    }

    return fallback;
}

function friendlyError(error, fallback) {
    if (error instanceof EditorRequestError) {
        return error.message;
    }

    if (error?.name === 'AbortError') {
        return 'Permintaan dibatalkan.';
    }

    if (error instanceof Error && error.message) {
        return plainMessage(error.message, fallback);
    }

    return fallback;
}

function parseJsonText(text) {
    if (typeof text !== 'string' || text.trim() === '') {
        return null;
    }

    try {
        return JSON.parse(text);
    } catch {
        return null;
    }
}

function normalizeStickerConfiguration(stickers) {
    const stickerMap = new Map();
    const safeStickers = [];

    if (!Array.isArray(stickers)) {
        return { stickerMap, safeStickers };
    }

    for (const sticker of stickers) {
        if (!isPlainObject(sticker)) {
            continue;
        }

        const key = toSafeIdentifier(sticker.key);
        const name = toSafeString(sticker.name, 120) ?? key;
        const localUrl = typeof sticker.url === 'string' ? sticker.url.trim() : '';
        const authorizedUrl = toSameOriginUrl(localUrl);

        if (key === null || name === null || authorizedUrl === null || stickerMap.has(key)) {
            continue;
        }

        const safeSticker = { key, name, url: localUrl };
        stickerMap.set(key, safeSticker);
        safeStickers.push(safeSticker);
    }

    return { stickerMap, safeStickers };
}

function normalizeMaxElements(value) {
    const numericValue = toFiniteNumber(value);

    if (numericValue === null) {
        return 50;
    }

    return clamp(Math.floor(numericValue), 1, 500);
}

function normalizeMaxImageBytes(value) {
    const numericValue = toFiniteNumber(value);

    if (numericValue === null || numericValue <= 0) {
        return 5 * 1024 * 1024;
    }

    return Math.floor(numericValue);
}

function extractImageSource(record) {
    const candidates = [
        record.src,
        record.url,
        record.image_url,
        isPlainObject(record.asset) ? record.asset.src : null,
        isPlainObject(record.asset) ? record.asset.url : null,
        isPlainObject(record.image) ? record.image.src : null,
        isPlainObject(record.image) ? record.image.url : null,
    ];

    for (const candidate of candidates) {
        const authorized = toSameOriginUrl(candidate);

        if (authorized !== null) {
            return authorized;
        }
    }

    return null;
}

function extractAssetId(record) {
    if (typeof record.asset_id === 'string' || typeof record.asset_id === 'number') {
        return record.asset_id;
    }

    if (isPlainObject(record.asset) && (typeof record.asset.id === 'string' || typeof record.asset.id === 'number')) {
        return record.asset.id;
    }

    return null;
}

function uniqueElementId(candidate, usedIds) {
    let identifier = toSafeIdentifier(candidate);

    if (identifier === null || usedIds.has(identifier)) {
        do {
            identifier = createElementId();
        } while (usedIds.has(identifier));
    }

    usedIds.add(identifier);

    return identifier;
}

function requiredFiniteNumber(value, field) {
    const numericValue = toFiniteNumber(value);

    if (numericValue === null || !Number.isFinite(numericValue)) {
        throw new Error(`Nilai "${field}" pada desain awal tidak valid.`);
    }

    return numericValue;
}

function requiredPositiveNumber(value, field) {
    const numericValue = requiredFiniteNumber(value, field);

    if (numericValue <= 0) {
        throw new Error(`Nilai "${field}" pada desain awal tidak valid.`);
    }

    return numericValue;
}

function normalizeElement(record, fallbackSide, sourceIndex, context) {
    if (!isPlainObject(record)) {
        throw new Error('Data desain awal memuat elemen yang tidak valid.');
    }

    const type = typeof record.type === 'string' ? record.type.trim().toLowerCase() : '';

    if (!ELEMENT_TYPES.has(type)) {
        throw new Error('Data desain awal memuat jenis elemen yang tidak dikenal.');
    }

    const rawSide = record.side === undefined || record.side === null ? fallbackSide : record.side;
    const side = typeof rawSide === 'string' ? rawSide.trim().toLowerCase() : '';

    if (!['front', 'back'].includes(side)) {
        throw new Error('Data desain awal memuat sisi yang tidak valid.');
    }

    if (side !== fallbackSide) {
        throw new Error('Data desain awal tidak konsisten antara sisi dan lokasi elemen.');
    }

    const layerValue = toFiniteNumber(record.layer);
    const rotationValue = toFiniteNumber(record.rotation) ?? 0;
    const scaleXValue = toFiniteNumber(record.scaleX) ?? 1;
    const scaleYValue = toFiniteNumber(record.scaleY) ?? 1;
    const opacityValue = toFiniteNumber(record.opacity) ?? 1;
    const positionX = requiredFiniteNumber(record.x, 'x');
    const positionY = requiredFiniteNumber(record.y, 'y');

    const element = {
        id: uniqueElementId(record.id, context.usedIds),
        type,
        x: roundNumber(positionX),
        y: roundNumber(positionY),
        width: roundNumber(requiredPositiveNumber(record.width, 'width')),
        height: roundNumber(requiredPositiveNumber(record.height, 'height')),
        rotation: roundNumber(((rotationValue % 360) + 360) % 360),
        scaleX: roundNumber(clamp(Math.abs(scaleXValue) || 1, MIN_SCALE, MAX_SCALE)),
        scaleY: roundNumber(clamp(Math.abs(scaleYValue) || 1, MIN_SCALE, MAX_SCALE)),
        flipX: record.flipX === true,
        flipY: record.flipY === true,
        opacity: roundNumber(clamp(opacityValue, 0, 1)),
        visible: record.visible !== false,
        side,
        layer: layerValue === null ? sourceIndex : Math.max(0, Math.floor(layerValue)),
    };

    if (type === 'image') {
        const assetId = extractAssetId(record);
        const source = extractImageSource(record);

        if (assetId === null || toSafeString(assetId, 128) === null) {
            throw new Error('Gambar pada desain awal tidak memiliki identitas aset yang valid.');
        }

        if (source === null) {
            throw new Error('Gambar pada desain awal tidak memiliki sumber lokal yang diizinkan.');
        }

        element.asset_id = assetId;
        context.imageSources.set(element.id, source);

        return element;
    }

    if (type === 'sticker') {
        let stickerKey = toSafeIdentifier(record.sticker_key);
        const configuredSticker = stickerKey === null ? undefined : context.stickerMap.get(stickerKey);

        if (configuredSticker === undefined) {
            const canonicalSource = extractImageSource(record);
            stickerKey = [...context.stickerMap.values()]
                .filter((sticker) => toSameOriginUrl(sticker.url) === canonicalSource)
                .map((sticker) => sticker.key)[0] ?? null;
        }

        const sticker = stickerKey === null ? undefined : context.stickerMap.get(stickerKey);

        if (sticker === undefined) {
            throw new Error('Stiker pada desain awal tidak termasuk daftar stiker yang diizinkan.');
        }

        element.sticker_key = sticker.key;
        element.src = sticker.url;

        return element;
    }

    const text = typeof record.text === 'string' ? record.text.slice(0, MAX_TEXT_LENGTH) : '';
    const fontSize = toFiniteNumber(record.font_size) ?? DEFAULT_FONT_SIZE;
    const fontFamily = isSafeFontFamily(record.font_family) ?? DEFAULT_FONT_FAMILY;
    const fontWeight = toSafeString(record.font_weight, 32)?.toLowerCase();
    const fontStyle = toSafeString(record.font_style, 32)?.toLowerCase();
    const textAlign = toSafeString(record.text_align, 32)?.toLowerCase();

    element.text = text;
    element.font_size = roundNumber(clamp(fontSize, MIN_FONT_SIZE, MAX_FONT_SIZE));
    element.font_family = fontFamily;
    element.font_weight = FONT_WEIGHTS.has(fontWeight) ? fontWeight : 'normal';
    element.font_style = FONT_STYLES.has(fontStyle) ? fontStyle : 'normal';
    element.text_align = TEXT_ALIGNMENTS.has(textAlign) ? textAlign : 'left';
    element.fill = isSafeColor(record.fill) ?? DEFAULT_FILL;

    return element;
}

function designRecords(design) {
    const parsed = typeof design === 'string' ? parseJsonText(design) : design;

    if (parsed === null || parsed === undefined) {
        return { front: [], back: [] };
    }

    if (Array.isArray(parsed)) {
        return { front: parsed, back: [] };
    }

    if (!isPlainObject(parsed)) {
        throw new Error('Format desain awal tidak dikenali.');
    }

    if (Array.isArray(parsed.elements)) {
        return { front: parsed.elements, back: [] };
    }

    const result = { front: [], back: [] };
    let foundSide = false;

    for (const side of ['front', 'back']) {
        if (!(side in parsed)) {
            continue;
        }

        foundSide = true;
        const sideDesign = parsed[side];

        if (Array.isArray(sideDesign)) {
            result[side] = sideDesign;
        } else if (isPlainObject(sideDesign) && Array.isArray(sideDesign.elements)) {
            result[side] = sideDesign.elements;
        } else {
            throw new Error('Struktur desain awal tidak valid.');
        }
    }

    if (!foundSide) {
        throw new Error('Format desain awal tidak dikenali.');
    }

    return result;
}

function normalizeInitialDesign(design, context) {
    const records = designRecords(design);
    const states = { front: [], back: [] };

    for (const side of ['front', 'back']) {
        const normalized = records[side].map((record, index) => normalizeElement(record, side, index, context));

        normalized.sort((first, second) => first.layer - second.layer);
        states[side] = normalized.slice(0, context.maxElements).map((element, index) => ({
            ...element,
            layer: index,
        }));
    }

    return states;
}

function sanitizeSpecificationValue(value, depth = 0) {
    if (depth > MAX_SPECIFICATION_DEPTH) {
        return null;
    }

    if (value === null || typeof value === 'string' || typeof value === 'boolean') {
        return typeof value === 'string' ? value.slice(0, 5000) : value;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }

    if (Array.isArray(value)) {
        return value.slice(0, 100).map((item) => sanitizeSpecificationValue(item, depth + 1));
    }

    if (!isPlainObject(value)) {
        return null;
    }

    const sanitized = {};

    for (const [key, item] of Object.entries(value).slice(0, 100)) {
        if (UNSAFE_KEYS.has(key) || PRICE_KEY_PATTERN.test(key)) {
            continue;
        }

        const cleanItem = sanitizeSpecificationValue(item, depth + 1);

        if (cleanItem !== null && cleanItem !== undefined) {
            sanitized[key] = cleanItem;
        }
    }

    return sanitized;
}

function isExcludedSpecificationKey(key) {
    const normalizedKey = key.toLowerCase();

    return (
        !/^[a-z][a-z0-9_.-]*$/i.test(key) ||
        UNSAFE_KEYS.has(key) ||
        PRICE_KEY_PATTERN.test(normalizedKey) ||
        /^(?:_token|_method|product_id|specification)$/.test(normalizedKey) ||
        /(?:draft_id|design_id|preview|file$|image$)/.test(normalizedKey)
    );
}

function readFormSpecification(form, previousSpecification) {
    if (!form || !form.elements) {
        return sanitizeSpecificationValue(previousSpecification ?? {}) ?? {};
    }

    const specification = sanitizeSpecificationValue(previousSpecification ?? {}) ?? {};

    for (const element of Array.from(form.elements)) {
        if (!element || !element.name || element.disabled) {
            continue;
        }

        const key = element.name;

        if (isExcludedSpecificationKey(key)) {
            continue;
        }

        if (element.tagName === 'BUTTON' || element.type === 'file' || element.type === 'submit' || element.type === 'reset') {
            continue;
        }

        if (element.type === 'checkbox' && element.checked !== true) {
            delete specification[key];
            continue;
        }

        if (element.type === 'radio' && element.checked !== true) {
            delete specification[key];
            continue;
        }

        const value = element.type === 'checkbox' && !element.value ? 'on' : element.value;

        if (typeof value !== 'string') {
            continue;
        }

        if (element.multiple === true) {
            const selectedValues = Array.from(element.selectedOptions ?? [])
                .map((option) => option.value)
                .filter((option) => typeof option === 'string');

            specification[key] = selectedValues;
        } else {
            specification[key] = value.slice(0, 5000);
        }
    }

    return specification;
}

function validateForm(form) {
    if (!form) {
        throw new Error('Formulir produk tidak tersedia.');
    }

    if (typeof form.checkValidity === 'function' && form.checkValidity() !== true) {
        if (typeof form.reportValidity === 'function') {
            form.reportValidity();
        }

        throw new EditorRequestError('Lengkapi data produk yang masih kosong atau tidak valid.');
    }
}

function isCartActionElement(element) {
    if (!element) {
        return false;
    }

    const dataset = element.dataset ?? {};
    const marker = dataset.designEditorAction ?? dataset.editorAction ?? dataset.action;

    if (typeof marker === 'string' && marker.trim() !== '') {
        return CART_ACTION_PATTERN.test(marker.trim());
    }

    if (typeof element.hasAttribute === 'function' && element.hasAttribute('formaction')) {
        return isCartLikeUrl(element.formAction);
    }

    const name = typeof element.name === 'string' ? element.name : '';
    const value = typeof element.value === 'string' ? element.value : '';

    if (/cart|keranjang|continue|checkout|lanjut/i.test(`${name} ${value}`.trim())) {
        return true;
    }

    return isCartLikeUrl(element.formAction);
}

function isCartLikeUrl(value) {
    if (typeof value !== 'string' || value === '' || typeof globalThis.location?.href !== 'string') {
        return false;
    }

    try {
        return CART_URL_PATTERN.test(new URL(value, globalThis.location.href).pathname);
    } catch {
        return false;
    }
}

function resolveCartSubmitter(event) {
    if (!event) {
        return null;
    }

    const element = event.type === 'click' ? event.currentTarget : event.submitter;

    return isCartActionElement(element) ? element : null;
}

function styleEditorObject(object) {
    object.set({
        borderColor: '#e11d2e',
        cornerColor: '#ffffff',
        cornerStrokeColor: '#e11d2e',
        cornerStyle: 'circle',
        transparentCorners: false,
        cornerSize: 10,
        touchCornerSize: 24,
        borderScaleFactor: 1.5,
        hasBorders: true,
        hasControls: true,
        objectCaching: true,
        padding: 0,
        minScaleLimit: MIN_SCALE,
        maxScaleLimit: MAX_SCALE,
        lockScalingFlip: true,
        centeredRotation: true,
    });

    const rotationControl = object.controls?.mtr;

    if (rotationControl) {
        rotationControl.offsetY = Math.max(rotationControl.offsetY ?? 0, 24);
    }

    object.setCoords();
}

function attachMetadata(object, metadata) {
    elementMetadata.set(object, {
        id: metadata.id,
        type: metadata.type,
        assetId: metadata.assetId ?? null,
        stickerKey: metadata.stickerKey ?? null,
        source: metadata.source ?? null,
    });
}

function metadataFor(object) {
    return elementMetadata.get(object) ?? null;
}

function clampObjectToCanvas(object, canvas) {
    if (!object || !canvas) {
        return;
    }

    const canvasWidth = canvas.getWidth();
    const canvasHeight = canvas.getHeight();

    if (!(canvasWidth > 0) || !(canvasHeight > 0)) {
        return;
    }

    const width = Math.max(1, object.getScaledWidth());
    const height = Math.max(1, object.getScaledHeight());
    const radians = ((toFiniteNumber(object.angle) ?? 0) * Math.PI) / 180;
    const cosine = Math.abs(Math.cos(radians));
    const sine = Math.abs(Math.sin(radians));
    const projectedWidth = cosine * width + sine * height;
    const projectedHeight = sine * width + cosine * height;

    if (projectedWidth > canvasWidth || projectedHeight > canvasHeight) {
        const fitScale = Math.min(canvasWidth / projectedWidth, canvasHeight / projectedHeight);

        object.set({
            scaleX: clamp((toFiniteNumber(object.scaleX) ?? 1) * fitScale, MIN_SCALE, MAX_SCALE),
            scaleY: clamp((toFiniteNumber(object.scaleY) ?? 1) * fitScale, MIN_SCALE, MAX_SCALE),
        });
    }

    const finalWidth = Math.max(1, object.getScaledWidth());
    const finalHeight = Math.max(1, object.getScaledHeight());
    const finalRadians = ((toFiniteNumber(object.angle) ?? 0) * Math.PI) / 180;
    const halfWidth = (Math.abs(Math.cos(finalRadians)) * finalWidth + Math.abs(Math.sin(finalRadians)) * finalHeight) / 2;
    const halfHeight = (Math.abs(Math.sin(finalRadians)) * finalWidth + Math.abs(Math.cos(finalRadians)) * finalHeight) / 2;
    const left = toFiniteNumber(object.left) ?? canvasWidth / 2;
    const top = toFiniteNumber(object.top) ?? canvasHeight / 2;

    object.set({
        left: clamp(left, halfWidth, Math.max(halfWidth, canvasWidth - halfWidth)),
        top: clamp(top, halfHeight, Math.max(halfHeight, canvasHeight - halfHeight)),
    });
    object.setCoords();
}

function serializeObject(object, side, layer) {
    const metadata = metadataFor(object);

    if (metadata === null || !ELEMENT_TYPES.has(metadata.type)) {
        return null;
    }

    const element = {
        id: metadata.id,
        type: metadata.type,
        x: roundNumber(toFiniteNumber(object.left) ?? 0),
        y: roundNumber(toFiniteNumber(object.top) ?? 0),
        width: roundNumber(toFiniteNumber(object.width) ?? object.getScaledWidth()),
        height: roundNumber(toFiniteNumber(object.height) ?? object.getScaledHeight()),
        rotation: roundNumber((toFiniteNumber(object.angle) ?? 0) % 360),
        scaleX: roundNumber(clamp(toFiniteNumber(object.scaleX) ?? 1, MIN_SCALE, MAX_SCALE)),
        scaleY: roundNumber(clamp(toFiniteNumber(object.scaleY) ?? 1, MIN_SCALE, MAX_SCALE)),
        flipX: object.flipX === true,
        flipY: object.flipY === true,
        opacity: roundNumber(clamp(toFiniteNumber(object.opacity) ?? 1, 0, 1)),
        visible: object.visible !== false,
        side,
        layer,
    };

    if (metadata.type === 'image') {
        element.asset_id = metadata.assetId;
    } else if (metadata.type === 'sticker') {
        element.sticker_key = metadata.stickerKey;
        element.src = metadata.source;
    } else {
        const fill = typeof object.fill === 'string' ? object.fill : object.fill?.toString?.();

        element.text = typeof object.text === 'string' ? object.text.slice(0, MAX_TEXT_LENGTH) : '';
        element.font_size = roundNumber(clamp(toFiniteNumber(object.fontSize) ?? DEFAULT_FONT_SIZE, MIN_FONT_SIZE, MAX_FONT_SIZE));
        element.font_family = isSafeFontFamily(object.fontFamily) ?? DEFAULT_FONT_FAMILY;
        element.font_weight = FONT_WEIGHTS.has(String(object.fontWeight).toLowerCase()) ? String(object.fontWeight).toLowerCase() : 'normal';
        element.font_style = FONT_STYLES.has(String(object.fontStyle).toLowerCase()) ? String(object.fontStyle).toLowerCase() : 'normal';
        element.text_align = TEXT_ALIGNMENTS.has(String(object.textAlign)) ? String(object.textAlign) : 'left';
        element.fill = isSafeColor(fill) ?? DEFAULT_FILL;
    }

    return element;
}

function clearCanvas(canvas) {
    if (!canvas) {
        return;
    }

    const activeObject = canvas.getActiveObject();

    if (typeof activeObject?.exitEditing === 'function') {
        activeObject.exitEditing();
    }

    canvas.discardActiveObject();
    const objects = canvas.getObjects();
    canvas.remove(...objects);
    objects.forEach((object) => {
        object.dispose?.();
    });
    canvas.requestRenderAll();
}

function validateImageFile(file, maximumBytes) {
    if (!isBrowserInstance('File', file) || file.size <= 0) {
        throw new EditorRequestError('Berkas gambar tidak valid atau kosong.');
    }

    const extension = /\.jpe?g$/i.test(file.name) || /\.png$/i.test(file.name);
    const supportedType = ['image/jpeg', 'image/jpg', 'image/png', 'image/pjpeg'].includes(file.type.toLowerCase());

    if (!extension && !supportedType) {
        throw new EditorRequestError('Format gambar harus JPG, JPEG, atau PNG.');
    }

    if (file.size > maximumBytes) {
        const maximumMegabytes = Math.floor(maximumBytes / (1024 * 1024));

        throw new EditorRequestError(`Ukuran gambar melebihi batas ${maximumMegabytes} MB.`);
    }
}

function createAbortError() {
    if (typeof DOMException === 'function') {
        return new DOMException('Permintaan dibatalkan.', 'AbortError');
    }

    const error = new Error('Permintaan dibatalkan.');
    error.name = 'AbortError';

    return error;
}

/**
 * Asset identifier of an *upload response*.
 *
 * This is deliberately separate from `extractAssetId()`: when reading a persisted
 * design element a top-level `id` is the ELEMENT id, never an asset id. Only here, in
 * the upload response, may a bare `id` be treated as the asset identifier, and only
 * when it is UUID-shaped.
 */
function extractUploadedAssetId(record) {
    if (!isPlainObject(record)) {
        return null;
    }

    const direct = extractAssetId(record);

    if (direct !== null) {
        return direct;
    }

    const candidate = toSafeString(record.id, 128);

    return candidate !== null && isUuidLike(candidate) ? candidate : null;
}

function extractUploadedAsset(payload) {
    const candidates = [
        isPlainObject(payload?.asset) ? payload.asset : null,
        isPlainObject(payload?.data?.asset) ? payload.data.asset : null,
        isPlainObject(payload?.data) ? payload.data : null,
        payload,
    ];

    for (const candidate of candidates) {
        if (!isPlainObject(candidate)) {
            continue;
        }

        const assetId = extractUploadedAssetId(candidate);
        const source = extractImageSource(candidate);

        if (assetId !== null && toSafeString(assetId, 128) !== null && source !== null) {
            return { assetId, source };
        }
    }

    throw new EditorRequestError('Server tidak mengembalikan identitas dan sumber gambar yang valid.');
}

function uploadImageFile({ file, url, csrfToken, requests, signalState }) {
    return new Promise((resolve, reject) => {
        if (typeof XMLHttpRequest !== 'function') {
            reject(new EditorRequestError('Browser tidak mendukung unggah gambar.'));
            return;
        }

        const request = new XMLHttpRequest();
        const succeed = (value) => {
            requests.delete(request);
            resolve(value);
        };
        const fail = (error) => {
            requests.delete(request);
            reject(error);
        };

        requests.add(request);

        request.open('POST', url, true);
        request.timeout = UPLOAD_TIMEOUT;
        request.withCredentials = true;
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.setRequestHeader('X-CSRF-TOKEN', csrfToken);

        request.onload = () => {
            const payload = parseJsonText(typeof request.responseText === 'string' ? request.responseText : '');

            if (isSessionExpired(request.status, request.responseURL, payload)) {
                fail(new EditorRequestError(SESSION_EXPIRED_MESSAGE, request.status));
                return;
            }

            if (request.status < 200 || request.status >= 300) {
                fail(new EditorRequestError(responseMessage(payload, 'Gambar gagal diunggah. Silakan coba kembali.'), request.status));
                return;
            }

            if (payload === null) {
                fail(new EditorRequestError('Respons unggah gambar tidak valid. Silakan muat ulang halaman.'));
                return;
            }

            succeed(payload);
        };

        request.onerror = () => {
            fail(new EditorRequestError('Tidak dapat terhubung ke server saat mengunggah gambar.'));
        };

        request.ontimeout = () => {
            fail(new EditorRequestError('Unggah gambar terlalu lama. Silakan coba kembali.'));
        };

        request.onabort = () => {
            fail(createAbortError());
        };

        const body = new FormData();
        body.append('image', file, file.name);

        request.send(body);

        if (signalState.destroyed) {
            request.abort();
        }
    });
}

async function requestJson({ url, method, body, csrfToken, signal }) {
    let response;

    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(body),
            signal,
        });
    } catch (error) {
        if (error?.name === 'AbortError') {
            throw error;
        }

        throw new EditorRequestError('Tidak dapat terhubung ke server. Periksa koneksi Anda lalu coba kembali.');
    }

    const text = await response.text();
    const payload = parseJsonText(text);

    if (isSessionExpired(response.status, response.url, payload)) {
        throw new EditorRequestError(SESSION_EXPIRED_MESSAGE, response.status);
    }

    if (!response.ok) {
        throw new EditorRequestError(responseMessage(payload, 'Draft desain gagal disimpan. Silakan coba kembali.'), response.status);
    }

    if (payload === null) {
        throw new EditorRequestError('Respons server tidak valid. Silakan muat ulang halaman.');
    }

    return payload;
}

async function exportPreviewBlob(canvas) {
    if (!canvas || typeof canvas.toBlob !== 'function') {
        throw new EditorRequestError('Pratinjau desain tidak dapat dibuat.');
    }

    const previousZoom = canvas.getZoom();

    try {
        canvas.setZoom(1);
        const blob = await canvas.toBlob({ format: 'png', quality: 1, multiplier: 1 });

        if (!isBrowserInstance('Blob', blob) || blob.size === 0) {
            throw new EditorRequestError('Pratinjau desain tidak dapat dibuat.');
        }

        return blob;
    } finally {
        canvas.setZoom(previousZoom);
        canvas.requestRenderAll();
    }
}

function attachPreviewFile(input, blob, side) {
    if (!isBrowserInstance('HTMLInputElement', input) || typeof DataTransfer !== 'function' || typeof File !== 'function') {
        throw new EditorRequestError('Browser ini tidak dapat melampirkan pratinjau desain. Silakan gunakan browser terbaru.');
    }

    const file = new File([blob], `design-${side}-${Date.now()}.png`, { type: 'image/png' });
    const transfer = new DataTransfer();

    transfer.items.add(file);
    input.files = transfer.files;

    if (!input.files || input.files.length !== 1) {
        throw new EditorRequestError('Pratinjau desain gagal dilampirkan. Silakan coba kembali.');
    }
}

function layerLabel(object, metadata, stickerMap) {
    if (metadata.type === 'text') {
        const text = typeof object.text === 'string' ? object.text.replace(/\s+/g, ' ').trim() : '';

        return text === '' ? 'Teks' : (text.length > 40 ? `${text.slice(0, 40)}...` : text);
    }

    if (metadata.type === 'sticker') {
        return stickerMap.get(metadata.stickerKey)?.name ?? 'Stiker';
    }

    return 'Gambar';
}

function editorObjectOptions(base) {
    return {
        originX: 'center',
        originY: 'center',
        selectable: true,
        evented: true,
        ...base,
    };
}

export function createDesignEditor(configuration = {}) {
    const config = isPlainObject(configuration) ? configuration : {};
    const { stickerMap, safeStickers } = normalizeStickerConfiguration(config.stickers);
    const maxElements = normalizeMaxElements(config.maxElements ?? 50);
    const maxImageBytes = normalizeMaxImageBytes(config.maxImageBytes ?? 5 * 1024 * 1024);
    const imageSources = new Map();
    const initialDraft = isPlainObject(config.initialDraft) ? config.initialDraft : null;
    const initialDesign = initialDraft && 'design' in initialDraft ? initialDraft.design : config.initialDesign;
    const initialSpecification = initialDraft && 'specification' in initialDraft ? initialDraft.specification : config.initialSpecification;
    const hasBack = config.hasBack === true;
    const saveEndpoint = typeof config.saveEndpoint === 'string' ? config.saveEndpoint : '';
    const updateEndpointTemplate = typeof config.updateEndpointTemplate === 'string' ? config.updateEndpointTemplate : '';
    const assetUploadEndpointTemplate = typeof config.assetUploadEndpointTemplate === 'string' ? config.assetUploadEndpointTemplate : '';
    const csrfToken = typeof config.csrfToken === 'string' ? config.csrfToken : '';
    const mockups = isPlainObject(config.mockups)
        ? {
              front: typeof config.mockups.front === 'string' ? config.mockups.front : null,
              back: typeof config.mockups.back === 'string' ? config.mockups.back : null,
          }
        : { front: null, back: null };
    const normalizationContext = { stickerMap, imageSources, usedIds: new Set(), maxElements };

    let initialStates = { front: [], back: [] };
    let initialError = '';
    let initialWarning = '';

    try {
        const records = designRecords(initialDesign);

        initialStates = normalizeInitialDesign(initialDesign, normalizationContext);

        if (records.front.length > maxElements || records.back.length > maxElements) {
            initialWarning = `Desain awal melebihi batas ${maxElements} elemen per sisi. Elemen tambahan diabaikan.`;
        }
    } catch (error) {
        for (const id of normalizationContext.usedIds) {
            imageSources.delete(id);
        }

        initialStates = { front: [], back: [] };
        initialError = `Desain awal tidak dapat dimuat. ${friendlyError(error, 'Mulai dengan kanvas kosong.')}`;
    }

    let canvas = null;
    let canvasHost = null;
    let resizeObserver = null;
    let sideLoadController = null;
    let sideLoadToken = 0;
    let savePromise = null;
    let saveAbortController = null;
    let pendingImageAction = null;
    let previewModeValue = false;
    let destroyed = false;
    let allowNextCartAction = false;
    const activeRequests = new Set();
    const formBindings = [];
    const canvasBindings = [];
    const requestState = { destroyed: false };

    function activeDesignObject(activeCanvas = canvas) {
        const object = activeCanvas?.getActiveObject?.() ?? null;

        return object && metadataFor(object) !== null ? object : null;
    }

    function rawDesignObject(value) {
        return metadataFor(unwrapReactive(value)) ? unwrapReactive(value) : null;
    }

    /**
     * Plain, serialisable view of the active element.
     *
     * The raw Fabric object must never travel through Alpine's reactive surface: Alpine
     * re-proxies values returned from accessors, and Fabric's collection helpers match on
     * reference identity, so a proxied object would make every layer/selection operation a
     * silent no-op. Internal logic therefore always uses `activeDesignObject()` directly,
     * and the template only ever receives this inert snapshot.
     */
    function selectionSnapshot() {
        const object = activeDesignObject();
        const metadata = metadataFor(object);

        if (object === null || metadata === null) {
            return null;
        }

        const snapshot = {
            id: metadata.id,
            type: metadata.type,
            left: roundNumber(toFiniteNumber(object.left) ?? 0),
            top: roundNumber(toFiniteNumber(object.top) ?? 0),
            width: roundNumber(toFiniteNumber(object.width) ?? 0),
            height: roundNumber(toFiniteNumber(object.height) ?? 0),
            scaledWidth: roundNumber(toFiniteNumber(object.getScaledWidth?.()) ?? 0),
            scaledHeight: roundNumber(toFiniteNumber(object.getScaledHeight?.()) ?? 0),
            angle: roundNumber(toFiniteNumber(object.angle) ?? 0),
            rotation: roundNumber(toFiniteNumber(object.angle) ?? 0),
            scaleX: roundNumber(toFiniteNumber(object.scaleX) ?? 1),
            scaleY: roundNumber(toFiniteNumber(object.scaleY) ?? 1),
            flipX: object.flipX === true,
            flipY: object.flipY === true,
            opacity: roundNumber(clamp(toFiniteNumber(object.opacity) ?? 1, 0, 1)),
            visible: object.visible !== false,
        };

        if (metadata.type === 'text') {
            snapshot.text = typeof object.text === 'string' ? object.text : '';
            snapshot.fontSize = roundNumber(toFiniteNumber(object.fontSize) ?? DEFAULT_FONT_SIZE);
            snapshot.fontFamily = object.fontFamily ?? DEFAULT_FONT_FAMILY;
            snapshot.fontWeight = object.fontWeight ?? 'normal';
            snapshot.fontStyle = object.fontStyle ?? 'normal';
            snapshot.textAlign = object.textAlign ?? 'left';
            snapshot.fill = typeof object.fill === 'string' ? object.fill : DEFAULT_FILL;
        }

        return snapshot;
    }

    function refreshLayers(editor) {
        editor._selectionVersion += 1;

        if (!canvas) {
            editor.layers = [];
            return;
        }

        const layers = canvas.getObjects().map((object, index) => {
            const metadata = metadataFor(object);

            if (metadata === null) {
                return null;
            }

            return {
                id: metadata.id,
                type: metadata.type,
                label: layerLabel(object, metadata, stickerMap),
                visible: object.visible !== false,
                layer: index,
            };
        });

        editor.layers = layers.filter(Boolean).reverse();
    }

    function applyInteractionState(editor) {
        if (!canvas) {
            return;
        }

        canvas.selection = !previewModeValue;
        canvas.skipTargetFind = previewModeValue;
        canvas.defaultCursor = previewModeValue ? 'default' : 'move';

        for (const object of canvas.getObjects()) {
            object.selectable = !previewModeValue && object.visible !== false;
            object.evented = !previewModeValue && object.visible !== false;
            object.setCoords();
        }

        if (previewModeValue) {
            canvas.discardActiveObject();
        }

        if (canvas.upperCanvasEl) {
            canvas.upperCanvasEl.style.pointerEvents = previewModeValue ? 'none' : '';
        }

        if (canvas.wrapperEl?.setAttribute) {
            canvas.wrapperEl.setAttribute('data-preview', previewModeValue ? 'true' : 'false');
        }

        refreshLayers(editor);
        canvas.requestRenderAll();
    }

    function updateBoundary() {
        if (!canvas || fabricLibrary === null) {
            return;
        }

        const previousBoundary = canvas.clipPath;

        canvas.clipPath = new fabricLibrary.Rect({
            left: 0,
            top: 0,
            originX: 'left',
            originY: 'top',
            absolutePositioned: true,
            selectable: false,
            evented: false,
            width: canvas.getWidth(),
            height: canvas.getHeight(),
        });
        previousBoundary?.dispose?.();
    }

    /**
     * Size the canvas to the host box (the `.design-canvas-host` element), not the
     * canvas element itself, because Fabric sets the canvas' intrinsic size. Measuring
     * the host keeps a crisp 1:1 buffer and correct pointer mapping at any breakpoint.
     */
    function measureCanvasElement(element, activeCanvas) {
        const host = canvasHost ?? element;
        const rect = host.getBoundingClientRect?.();
        const width = Math.max(1, Math.round(rect?.width ?? 0) || 600);
        const height = Math.max(1, Math.round(rect?.height ?? 0) || Math.round(width * 0.7));

        if (activeCanvas.getWidth() !== width || activeCanvas.getHeight() !== height) {
            activeCanvas.setDimensions({ width, height });
        }

        updateBoundary();
        activeCanvas.getObjects().forEach((object) => clampObjectToCanvas(object, activeCanvas));
    }

    function bindCanvasEvent(editor, name, handler) {
        canvas.on(name, handler);
        canvasBindings.push({ name, handler });
    }

    function bindFormEvent(editor, target, name) {
        if (typeof target?.addEventListener !== 'function') {
            return;
        }

        const listener = (event) => {
            if (event.target === editor.$refs?.previewInput || event.target === editor.$refs?.imageInput) {
                return;
            }

            editor._markDirty();
        };

        target.addEventListener(name, listener);
        formBindings.push({ target, name, listener });
    }

    function unbindAll() {
        for (const { target, name, listener } of formBindings.splice(0)) {
            target.removeEventListener(name, listener);
        }

        for (const { name, handler } of canvasBindings.splice(0)) {
            canvas?.off(name, handler);
        }
    }

    function disposeLoadedObjects(objects) {
        objects.forEach((object) => {
            if (typeof object?.dispose === 'function') {
                object.dispose();
            }
        });
    }

    function abortSideLoad() {
        sideLoadToken += 1;
        sideLoadController?.abort?.();
        sideLoadController = null;
    }

    function endpointFor(template, id, label) {
        if (typeof template !== 'string' || !template.includes('{id}')) {
            throw new EditorRequestError(`Endpoint ${label} tidak dikonfigurasi dengan benar.`);
        }

        return template.replace('{id}', encodeURIComponent(String(id)));
    }

    function markSuccessful(editor) {
        editor.error = '';
    }

    function updateDraftHiddenInput(editor, draftId) {
        const hiddenInput = editor.$refs?.designDraftId;

        if (hiddenInput) {
            hiddenInput.value = draftId;
        }
    }

    function imageCreationOptions(record) {
        return editorObjectOptions({
            left: record.x,
            top: record.y,
            width: record.width,
            height: record.height,
            scaleX: record.scaleX,
            scaleY: record.scaleY,
            angle: record.rotation,
            flipX: record.flipX,
            flipY: record.flipY,
            opacity: record.opacity,
            visible: record.visible,
            crossOrigin: 'anonymous',
        });
    }

    function fittedImageOptions(record, left, top) {
        return editorObjectOptions({
            left,
            top,
            angle: record.rotation,
            flipX: record.flipX,
            flipY: record.flipY,
            opacity: record.opacity,
            visible: record.visible,
            crossOrigin: 'anonymous',
        });
    }

    async function createRestoredObject(editor, record, signal) {
        const { FabricImage, Textbox } = await loadFabric();

        if (record.type === 'text') {
            const textObject = new Textbox(
                record.text,
                editorObjectOptions({
                    left: record.x,
                    top: record.y,
                    width: record.width,
                    scaleX: record.scaleX,
                    scaleY: record.scaleY,
                    angle: record.rotation,
                    flipX: record.flipX,
                    flipY: record.flipY,
                    opacity: record.opacity,
                    visible: record.visible,
                    fontSize: record.font_size,
                    fontFamily: record.font_family,
                    fontWeight: record.font_weight,
                    fontStyle: record.font_style,
                    textAlign: record.text_align,
                    fill: record.fill,
                    editable: true,
                    splitByGrapheme: false,
                    lineHeight: 1.16,
                }),
            );

            attachMetadata(textObject, { id: record.id, type: 'text' });
            styleEditorObject(textObject);

            return Promise.resolve(textObject);
        }

        if (record.type === 'sticker') {
            const sticker = stickerMap.get(record.sticker_key);
            const source = toSameOriginUrl(sticker?.url);

            if (!sticker || source === null) {
                return Promise.reject(new Error('Sumber stiker tidak diizinkan.'));
            }

            return FabricImage.fromURL(source, { crossOrigin: 'anonymous', signal }, imageCreationOptions(record)).then(
                (imageObject) => {
                    attachMetadata(imageObject, {
                        id: record.id,
                        type: 'sticker',
                        stickerKey: record.sticker_key,
                        source: sticker.url,
                    });
                    styleEditorObject(imageObject);

                    return imageObject;
                },
            );
        }

        const source = toSameOriginUrl(imageSources.get(record.id));

        if (source === null) {
            return Promise.reject(new Error('Sumber gambar tidak diizinkan.'));
        }

        return FabricImage.fromURL(source, { crossOrigin: 'anonymous', signal }, imageCreationOptions(record)).then(
            (imageObject) => {
                attachMetadata(imageObject, {
                    id: record.id,
                    type: 'image',
                    assetId: record.asset_id,
                    source,
                });
                styleEditorObject(imageObject);

                return imageObject;
            },
        );
    }

    function captureSide(editor, side) {
        if (!canvas) {
            return [];
        }

        const activeObject = canvas.getActiveObject();

        if (typeof activeObject?.exitEditing === 'function') {
            activeObject.exitEditing();
        }

        return canvas
            .getObjects()
            .map((object) => serializeObject(object, side, 0))
            .filter((element) => element !== null)
            .map((element, layer) => ({ ...element, layer }));
    }

    function fitImageToCanvas(imageObject) {
        const maxWidth = Math.max(20, canvas.getWidth() * 0.6);
        const maxHeight = Math.max(20, canvas.getHeight() * 0.6);
        const naturalWidth = Math.max(1, toFiniteNumber(imageObject.width) ?? maxWidth);
        const naturalHeight = Math.max(1, toFiniteNumber(imageObject.height) ?? maxHeight);
        const scale = Math.min(1, maxWidth / naturalWidth, maxHeight / naturalHeight);

        imageObject.set({
            scaleX: scale,
            scaleY: scale,
        });
        canvas.centerObject(imageObject);
    }

    const editor = {
        productId: config.productId ?? null,
        saveEndpoint,
        updateEndpointTemplate,
        assetUploadEndpointTemplate,
        csrfToken,
        mockups,
        stickers: safeStickers,
        hasBack,
        continueLabel: typeof config.continueLabel === 'string' ? config.continueLabel : 'Lanjut ke cart',
        maxElements,
        maxImageBytes,
        draftId: initialDraft?.id ?? null,
        draftVersion: initialDraft?.version ?? null,
        specification: sanitizeSpecificationValue(initialSpecification ?? {}) ?? {},
        sideStates: initialStates,
        currentSide: 'front',
        error: initialError,
        statusMessage: initialWarning,
        hasUnsavedChanges: false,
        continuing: false,
        saving: false,
        uploading: false,
        zoomPercent: 100,
        layers: [],
        stickerPickerOpen: false,
        changeRevision: 0,
        savedRevision: 0,
        _selectionVersion: 0,
        _initialized: false,
        _destroyed: false,


        get selectedLabel() {
            void this._selectionVersion;

            const object = activeDesignObject();
            const metadata = metadataFor(object);

            if (object === null || metadata === null) {
                return '';
            }

            if (metadata.type === 'text') {
                const value = typeof object.text === 'string' ? object.text.replace(/\s+/g, ' ').trim() : '';

                return value === '' ? 'Teks' : (value.length > 40 ? value.slice(0, 40) + '...' : value);
            }

            if (metadata.type === 'sticker') {
                return stickerMap.get(metadata.stickerKey)?.name ?? 'Stiker';
            }

            return 'Gambar';
        },

        get previewMode() {
            return previewModeValue;
        },

        set previewMode(value) {
            const nextValue = Boolean(value);

            if (nextValue === previewModeValue || destroyed) {
                return;
            }

            previewModeValue = nextValue;
            applyInteractionState(this);
        },

        get selected() {
            void this._selectionVersion;

            return selectionSnapshot();
        },

        get selectedType() {
            // Touch the selection revision so Alpine re-evaluates this getter whenever
            // the selection changes, mirroring the `selected` getter below.
            void this._selectionVersion;

            return metadataFor(activeDesignObject())?.type ?? null;
        },

        get selectedId() {
            void this._selectionVersion;

            return metadataFor(activeDesignObject())?.id ?? null;
        },

        init() {
            if (this._initialized || destroyed) {
                return Promise.resolve();
            }

            this._initialized = true;
            const form = this.$refs?.productForm;

            if (form) {
                bindFormEvent(this, form, 'input');
                bindFormEvent(this, form, 'change');
            }

            const imageInput = this.$refs?.imageInput;

            if (typeof imageInput?.addEventListener === 'function') {
                const listener = () => {
                    void this._consumeImageInput();
                };

                imageInput.addEventListener('change', listener);
                formBindings.push({ target: imageInput, name: 'change', listener });
            }

            return new Promise((resolve) => {
                this.$nextTick(() => {
                    this._mount()
                        .catch((error) => {
                            this.error = friendlyError(error, 'Editor desain gagal dimuat.');
                        })
                        .finally(resolve);
                });
            });
        },

        async _mount() {
            const canvasElement = this.$refs?.canvas;

            if (!isBrowserInstance('HTMLCanvasElement', canvasElement)) {
                throw new EditorRequestError('Kanvas desain tidak tersedia.');
            }

            const { Canvas } = await loadFabric();

            canvas = new Canvas(canvasElement, {
                backgroundColor: 'transparent',
                // `preserveObjectStacking` must stay off: it pins the render order to the
                // insertion order, which would silently defeat the layer controls
                // (bring forward / send backward / to front / to back).
                preserveObjectStacking: false,
                controlsAboveOverlay: true,
                selection: true,
                selectionColor: 'rgba(225, 29, 46, 0.10)',
                selectionBorderColor: '#e11d2e',
                selectionLineWidth: 1,
                uniformScaling: true,
                uniScaleKey: 'shiftKey',
                stopContextMenu: true,
                fireRightClick: false,
                perPixelTargetFind: false,
            });

            for (const element of [canvas.lowerCanvasEl, canvas.upperCanvasEl]) {
                if (element) {
                    element.style.backgroundColor = 'transparent';
                }
            }

            if (canvas.wrapperEl) {
                canvas.wrapperEl.style.backgroundColor = 'transparent';
            }

            // Fabric moves the <canvas> into its own wrapper, so the measurable
            // element is the wrapper's parent (the host supplied by the Blade view).
            canvasHost = canvas.wrapperEl?.parentElement ?? canvasElement.parentElement ?? canvasElement;

            measureCanvasElement(canvasElement, canvas);
            this._syncZoom();

            bindCanvasEvent(this, 'selection:created', () => {
                if (previewModeValue) {
                    canvas.discardActiveObject();
                }

                refreshLayers(this);
            });
            bindCanvasEvent(this, 'selection:updated', () => refreshLayers(this));
            bindCanvasEvent(this, 'selection:cleared', () => refreshLayers(this));

            for (const eventName of ['object:moving', 'object:scaling', 'object:rotating']) {
                bindCanvasEvent(this, eventName, ({ target }) => {
                    clampObjectToCanvas(target, canvas);
                });
            }

            bindCanvasEvent(this, 'object:modified', ({ target }) => {
                clampObjectToCanvas(target, canvas);
                this._markDirty();
                refreshLayers(this);
            });

            bindCanvasEvent(this, 'text:changed', () => this._markDirty());
            bindCanvasEvent(this, 'text:editing:entered', () => {
                this.statusMessage = 'Edit teks pada kanvas, lalu klik di luar teks untuk menyimpan.';
            });

            if (typeof ResizeObserver === 'function') {
                resizeObserver = new ResizeObserver(() => {
                    if (!canvas || destroyed) {
                        return;
                    }

                    measureCanvasElement(canvasElement, canvas);
                    canvas.requestRenderAll();
                });
                resizeObserver.observe(canvasHost ?? canvasElement);
            } else {
                const listener = () => {
                    if (!canvas || destroyed) {
                        return;
                    }

                    measureCanvasElement(canvasElement, canvas);
                    canvas.requestRenderAll();
                };

                globalThis.addEventListener?.('resize', listener);
                formBindings.push({ target: globalThis, name: 'resize', listener });
            }

            applyInteractionState(this);
            await this._loadSide('front');

            if (this.error === '' && this.statusMessage === '') {
                this.statusMessage = 'Editor siap. Tambahkan gambar, teks, atau stiker.';
            }
        },

        _markDirty() {
            if (destroyed) {
                return;
            }

            this.changeRevision += 1;
            this.hasUnsavedChanges = this.changeRevision !== this.savedRevision;
        },

        _refreshLayers() {
            refreshLayers(this);
        },

        _updateSelected(mutator) {
            const object = activeDesignObject();

            if (!object) {
                return false;
            }

            mutator(object);
            clampObjectToCanvas(object, canvas);
            object.setCoords();
            canvas.requestRenderAll();
            this._markDirty();
            this._refreshLayers();

            return true;
        },

        async _loadSide(side) {
            if (!canvas || destroyed) {
                return false;
            }

            abortSideLoad();
            sideLoadController = typeof AbortController === 'function' ? new AbortController() : null;
            const token = sideLoadToken;
            const signal = sideLoadController?.signal;
            const records = [...(this.sideStates[side] ?? [])];
            const loadedObjects = [];

            try {
                for (const record of records) {
                    if (token !== sideLoadToken || destroyed || !canvas) {
                        disposeLoadedObjects(loadedObjects);
                        return false;
                    }

                    const object = await createRestoredObject(this, record, signal);
                    loadedObjects.push(object);
                }

                if (token !== sideLoadToken || destroyed || !canvas || this.currentSide !== side) {
                    disposeLoadedObjects(loadedObjects);
                    return false;
                }

                canvas.add(...loadedObjects);
                loadedObjects.forEach((object) => clampObjectToCanvas(object, canvas));
                canvas.discardActiveObject();
                canvas.requestRenderAll();
                this._refreshLayers();

                return true;
            } catch (error) {
                disposeLoadedObjects(loadedObjects);

                if (token !== sideLoadToken || destroyed || error?.name === 'AbortError') {
                    return false;
                }

                this.sideStates = { ...this.sideStates, [side]: [] };

                if (canvas && this.currentSide === side) {
                    clearCanvas(canvas);
                    this._refreshLayers();
                }

                this.error = `Desain sisi ${side === 'back' ? 'belakang' : 'depan'} tidak dapat dimuat. ${friendlyError(error, 'Kanvas dikosongkan.')}`;

                return false;
            }
        },

        async switchSide(side) {
            const targetSide = typeof side === 'string' ? side.trim().toLowerCase() : '';

            if (!['front', 'back'].includes(targetSide)) {
                return false;
            }

            if (targetSide === 'back' && !hasBack) {
                this.statusMessage = 'Produk ini hanya memiliki satu sisi desain.';
                return false;
            }

            if (targetSide === this.currentSide) {
                return true;
            }

            this.sideStates = { ...this.sideStates, [this.currentSide]: captureSide(this, this.currentSide) };
            this.currentSide = targetSide;
            clearCanvas(canvas);
            this._refreshLayers();
            markSuccessful(this);

            return this._loadSide(targetSide);
        },

        addText() {
            if (!canvas || destroyed || fabricLibrary === null) {
                return null;
            }

            if (this.previewMode) {
                return null;
            }

            if (canvas.getObjects().length >= maxElements) {
                this.error = `Maksimal ${maxElements} elemen per sisi. Hapus elemen sebelum menambah yang baru.`;
                return null;
            }

            const { Textbox } = fabricLibrary;
            const width = clamp(canvas.getWidth() * 0.6, 80, 320);
            const textObject = new Textbox(
                DEFAULT_TEXT,
                editorObjectOptions({
                    left: canvas.getWidth() / 2,
                    top: canvas.getHeight() / 2,
                    width,
                    fontSize: DEFAULT_FONT_SIZE,
                    fontFamily: DEFAULT_FONT_FAMILY,
                    fontWeight: 'normal',
                    fontStyle: 'normal',
                    textAlign: 'left',
                    fill: DEFAULT_FILL,
                    editable: true,
                    splitByGrapheme: false,
                    lineHeight: 1.16,
                }),
            );

            attachMetadata(textObject, { id: createElementId(), type: 'text' });
            styleEditorObject(textObject);
            canvas.add(textObject);
            clampObjectToCanvas(textObject, canvas);
            canvas.setActiveObject(textObject);
            canvas.requestRenderAll();
            this.error = '';
            this._markDirty();
            this._refreshLayers();

            return textObject;
        },

        openImagePicker() {
            const imageInput = this.$refs?.imageInput;

            if (!isBrowserInstance('HTMLInputElement', imageInput) || this.previewMode) {
                this.error = 'Pemilih gambar tidak tersedia pada mode ini.';
                return false;
            }

            pendingImageAction = { mode: 'add', side: this.currentSide };
            imageInput.value = '';
            imageInput.click();

            return true;
        },

        replaceSelectedImage() {
            const imageInput = this.$refs?.imageInput;
            const object = activeDesignObject();
            const metadata = metadataFor(object);

            if (!isBrowserInstance('HTMLInputElement', imageInput) || metadata?.type !== 'image') {
                this.error = 'Pilih gambar terlebih dahulu untuk menggantinya.';
                return false;
            }

            if (this.previewMode) {
                this.error = 'Keluar dari mode pratinjau untuk mengganti gambar.';
                return false;
            }

            pendingImageAction = { mode: 'replace', id: metadata.id, side: this.currentSide };
            imageInput.value = '';
            imageInput.click();

            return true;
        },

        async _consumeImageInput() {
            const imageInput = this.$refs?.imageInput;
            const file = imageInput?.files?.[0] ?? null;
            const action = pendingImageAction;
            pendingImageAction = null;

            if (imageInput) {
                imageInput.value = '';
            }

            if (file === null || action === null) {
                return null;
            }

            try {
                validateImageFile(file, maxImageBytes);
                await this.saveDraft();

                if (destroyed || !canvas) {
                    return null;
                }

                if (action.mode === 'replace' && action.side !== this.currentSide) {
                    throw new EditorRequestError('Sisi desain berubah. Pilih gambar lalu ulangi penggantian.');
                }

                if (this.draftId === null || this.draftId === undefined || this.draftId === '') {
                    throw new EditorRequestError('Draft desain belum tersimpan. Silakan coba kembali.');
                }

                this.uploading = true;
                const url = endpointFor(assetUploadEndpointTemplate, this.draftId, 'unggah aset');
                const payload = await uploadImageFile({
                    file,
                    url,
                    csrfToken,
                    requests: activeRequests,
                    signalState: requestState,
                });

                if (destroyed || !canvas) {
                    return null;
                }

                const asset = extractUploadedAsset(payload);
                const object = await this._applyUploadedImage(action, asset);
                markSuccessful(this);
                this.statusMessage = action.mode === 'replace' ? 'Gambar berhasil diganti.' : 'Gambar berhasil ditambahkan.';

                return object;
            } catch (error) {
                this.error = friendlyError(error, 'Gambar gagal ditambahkan.');
                this.statusMessage = '';

                return null;
            } finally {
                this.uploading = false;
            }
        },

        async _applyUploadedImage(action, asset) {
            if (action.mode === 'replace') {
                const objects = canvas.getObjects();
                const targetIndex = objects.findIndex((object) => metadataFor(object)?.id === action.id);
                const target = objects[targetIndex];

                if (target === undefined || metadataFor(target)?.type !== 'image') {
                    throw new EditorRequestError('Gambar yang akan diganti tidak lagi tersedia. Silakan pilih ulang gambar.');
                }

                return this._replaceImageObject(target, Math.max(0, targetIndex), asset);
            }

            if (canvas.getObjects().length >= maxElements) {
                throw new EditorRequestError(`Maksimal ${maxElements} elemen per sisi.`);
            }

            const id = createElementId();
            const record = {
                x: canvas.getWidth() / 2,
                y: canvas.getHeight() / 2,
                rotation: 0,
                flipX: false,
                flipY: false,
                opacity: 1,
                visible: true,
            };
            const imageObject = await this._buildImage(record, asset, id);

            imageSources.set(id, asset.source);
            fitImageToCanvas(imageObject);
            clampObjectToCanvas(imageObject, canvas);
            canvas.add(imageObject);
            canvas.setActiveObject(imageObject);
            canvas.requestRenderAll();
            this._markDirty();
            this._refreshLayers();

            return imageObject;
        },

        async _buildImage(record, asset, id) {
            const { FabricImage } = await loadFabric();
            const imageObject = await FabricImage.fromURL(
                asset.source,
                { crossOrigin: 'anonymous' },
                fittedImageOptions(record, record.x, record.y),
            );

            imageObject.set({
                opacity: record.opacity,
                visible: record.visible,
            });
            attachMetadata(imageObject, {
                id,
                type: 'image',
                assetId: asset.assetId,
                source: asset.source,
            });
            styleEditorObject(imageObject);

            return imageObject;
        },

        async _replaceImageObject(target, targetIndex, asset) {
            const id = metadataFor(target).id;
            const record = {
                x: toFiniteNumber(target.left) ?? canvas.getWidth() / 2,
                y: toFiniteNumber(target.top) ?? canvas.getHeight() / 2,
                rotation: toFiniteNumber(target.angle) ?? 0,
                flipX: target.flipX === true,
                flipY: target.flipY === true,
                opacity: clamp(toFiniteNumber(target.opacity) ?? 1, 0, 1),
                visible: target.visible !== false,
            };
            const renderedWidth = Math.max(1, target.getScaledWidth());
            const renderedHeight = Math.max(1, target.getScaledHeight());
            const imageObject = await this._buildImage(record, asset, id);

            if (target === activeDesignObject()) {
                canvas.discardActiveObject();
            }

            canvas.remove(target);
            target.dispose?.();
            imageObject.set({ width: renderedWidth, height: renderedHeight, scaleX: 1, scaleY: 1 });
            canvas.add(imageObject);
            canvas.moveObjectTo(imageObject, Math.min(targetIndex, Math.max(0, canvas.getObjects().length - 1)));
            clampObjectToCanvas(imageObject, canvas);
            imageObject.setCoords();
            canvas.setActiveObject(imageObject);
            canvas.requestRenderAll();
            imageSources.set(id, asset.source);
            this._markDirty();
            this._refreshLayers();

            return imageObject;
        },

        async addSticker(sticker) {
            if (!canvas || destroyed || this.previewMode) {
                return null;
            }

            if (canvas.getObjects().length >= maxElements) {
                this.error = `Maksimal ${maxElements} elemen per sisi. Hapus elemen sebelum menambah yang baru.`;
                return null;
            }

            const key = typeof sticker === 'string'
                ? toSafeIdentifier(sticker)
                : toSafeIdentifier(isPlainObject(sticker) ? sticker.key : null);
            const configuredSticker = key === null ? undefined : stickerMap.get(key);

            if (configuredSticker === undefined) {
                this.error = 'Stiker yang dipilih tidak tersedia.';
                return null;
            }

            const source = toSameOriginUrl(configuredSticker.url);

            if (source === null) {
                this.error = 'Sumber stiker tidak diizinkan.';
                return null;
            }

            const side = this.currentSide;

            try {
                const { FabricImage } = await loadFabric();
                const id = createElementId();
                const record = {
                    x: canvas.getWidth() / 2,
                    y: canvas.getHeight() / 2,
                    rotation: 0,
                    flipX: false,
                    flipY: false,
                    opacity: 1,
                    visible: true,
                };
                const imageObject = await FabricImage.fromURL(
                    source,
                    { crossOrigin: 'anonymous' },
                    fittedImageOptions(record, record.x, record.y),
                );

                if (destroyed || !canvas || this.currentSide !== side || canvas.getObjects().length >= maxElements) {
                    imageObject.dispose?.();
                    throw new EditorRequestError('Sisi desain berubah atau batas elemen tercapai.');
                }

                attachMetadata(imageObject, {
                    id,
                    type: 'sticker',
                    stickerKey: configuredSticker.key,
                    source: configuredSticker.url,
                });
                styleEditorObject(imageObject);
                fitImageToCanvas(imageObject);
                clampObjectToCanvas(imageObject, canvas);
                canvas.add(imageObject);
                canvas.setActiveObject(imageObject);
                canvas.requestRenderAll();
                markSuccessful(this);
                this.statusMessage = `${configuredSticker.name} ditambahkan.`;
                this._markDirty();
                this._refreshLayers();

                return imageObject;
            } catch (error) {
                this.error = friendlyError(error, 'Stiker gagal ditambahkan.');
                this.statusMessage = '';

                return null;
            }
        },

        toggleMode() {
            this.previewMode = !this.previewMode;
        },

        toggleStickerPicker() {
            this.stickerPickerOpen = !this.stickerPickerOpen;
        },

        selectObject(object) {
            if (!canvas || this.previewMode) {
                return false;
            }

            this.stickerPickerOpen = false;

            const target = rawDesignObject(
                typeof object === 'string'
                    ? canvas.getObjects().find((candidate) => metadataFor(candidate)?.id === object)
                    : object,
            );

            if (!target || !canvas.getObjects().includes(target) || target.visible === false) {
                return false;
            }

            canvas.setActiveObject(target);
            canvas.requestRenderAll();
            this._refreshLayers();

            return true;
        },

        selectLayer(id) {
            return this.selectObject(id);
        },

        toggleLayerVisibility(id) {
            const object = canvas?.getObjects().find((candidate) => metadataFor(candidate)?.id === id);

            if (!object) {
                return false;
            }

            const visible = object.visible === false;
            object.set({ visible });
            object.set({ evented: visible && !this.previewMode, selectable: visible && !this.previewMode });

            if (!visible && object === activeDesignObject()) {
                canvas.discardActiveObject();
            }

            object.setCoords();
            canvas.requestRenderAll();
            this._markDirty();
            this._refreshLayers();

            return true;
        },

        toggleSelectedVisibility() {
            const object = activeDesignObject();

            if (!object) {
                return false;
            }

            const visible = object.visible === false;
            object.set({ visible });
            object.set({ evented: visible && !this.previewMode, selectable: visible && !this.previewMode });

            if (!visible && object === activeDesignObject()) {
                canvas.discardActiveObject();
            }

            object.setCoords();
            canvas.requestRenderAll();
            this._markDirty();
            this._refreshLayers();

            return true;
        },

        updateSelectedText(value) {
            if (typeof value !== 'string' || this.selectedType !== 'text') {
                return false;
            }

            return this._updateSelected((object) => object.set({ text: value.slice(0, MAX_TEXT_LENGTH) }));
        },

        updateSelectedFontSize(value) {
            const fontSize = toFiniteNumber(value);

            if (fontSize === null || this.selectedType !== 'text') {
                return false;
            }

            return this._updateSelected((object) =>
                object.set({ fontSize: clamp(fontSize, MIN_FONT_SIZE, MAX_FONT_SIZE) }),
            );
        },

        updateSelectedFontFamily(value) {
            const fontFamily = isSafeFontFamily(value);

            if (fontFamily === null || this.selectedType !== 'text') {
                return false;
            }

            return this._updateSelected((object) => object.set({ fontFamily }));
        },

        toggleBold() {
            if (this.selectedType !== 'text') {
                return false;
            }

            return this._updateSelected((object) =>
                object.set({ fontWeight: object.fontWeight === 'bold' ? 'normal' : 'bold' }),
            );
        },

        toggleItalic() {
            if (this.selectedType !== 'text') {
                return false;
            }

            return this._updateSelected((object) =>
                object.set({ fontStyle: object.fontStyle === 'italic' ? 'normal' : 'italic' }),
            );
        },

        updateSelectedAlign(value) {
            const alignment = typeof value === 'string' ? value.trim().toLowerCase() : '';

            if (!TEXT_ALIGNMENTS.has(alignment) || this.selectedType !== 'text') {
                return false;
            }

            return this._updateSelected((object) => object.set({ textAlign: alignment }));
        },

        updateSelectedColor(value) {
            const color = isSafeColor(value);

            if (color === null || this.selectedType !== 'text') {
                return false;
            }

            return this._updateSelected((object) => object.set({ fill: color }));
        },

        updateSelectedRotation(value) {
            const rotation = toFiniteNumber(value);

            if (rotation === null) {
                return false;
            }

            return this._updateSelected((object) => object.set({ angle: ((rotation % 360) + 360) % 360 }));
        },

        updateSelectedOpacity(value) {
            const numericValue = toFiniteNumber(value);

            if (numericValue === null) {
                return false;
            }

            const opacity = clamp(numericValue > 1 ? numericValue / 100 : numericValue, 0, 1);

            return this._updateSelected((object) => object.set({ opacity }));
        },

        selectedZoomIn() {
            if (this.selectedType !== 'image') {
                return false;
            }

            return this._updateSelected((object) =>
                object.set({ scaleX: (toFiniteNumber(object.scaleX) ?? 1) * 1.1, scaleY: (toFiniteNumber(object.scaleY) ?? 1) * 1.1 }),
            );
        },

        selectedZoomOut() {
            if (this.selectedType !== 'image') {
                return false;
            }

            return this._updateSelected((object) =>
                object.set({ scaleX: (toFiniteNumber(object.scaleX) ?? 1) * 0.9, scaleY: (toFiniteNumber(object.scaleY) ?? 1) * 0.9 }),
            );
        },

        rotateSelected() {
            if (this.selectedType !== 'image') {
                return false;
            }

            return this._updateSelected((object) => object.set({ angle: ((toFiniteNumber(object.angle) ?? 0) + 90) % 360 }));
        },

        flipSelectedHorizontal() {
            if (this.selectedType !== 'image') {
                return false;
            }

            return this._updateSelected((object) => object.set({ flipX: object.flipX !== true }));
        },

        flipSelectedVertical() {
            if (this.selectedType !== 'image') {
                return false;
            }

            return this._updateSelected((object) => object.set({ flipY: object.flipY !== true }));
        },

        deleteSelected() {
            const object = activeDesignObject();

            if (!object || !canvas) {
                return false;
            }

            canvas.remove(object);
            object.dispose?.();
            canvas.discardActiveObject();
            canvas.requestRenderAll();
            this._markDirty();
            this._refreshLayers();

            return true;
        },

        _moveLayer(method) {
            const object = activeDesignObject();

            if (!object || typeof canvas[method] !== 'function') {
                return false;
            }

            const changed = canvas[method](object) === true;

            if (changed) {
                object.setCoords();
                canvas.requestRenderAll();
                this._markDirty();
                this._refreshLayers();
            }

            return changed;
        },

        bringForward() {
            return this._moveLayer('bringObjectForward');
        },

        sendBackward() {
            return this._moveLayer('sendObjectBackwards');
        },

        bringToFront() {
            return this._moveLayer('bringObjectToFront');
        },

        sendToBack() {
            return this._moveLayer('sendObjectToBack');
        },

        zoomIn() {
            if (!canvas) {
                return;
            }

            const nextZoom = clamp(roundNumber((toFiniteNumber(this.zoomPercent) ?? 100) / 100 + ZOOM_STEP), MIN_ZOOM, MAX_ZOOM);
            canvas.zoomToPoint(canvas.getCenterPoint(), nextZoom);
            this._syncZoom();
            canvas.requestRenderAll();
        },

        zoomOut() {
            if (!canvas) {
                return;
            }

            const nextZoom = clamp(roundNumber((toFiniteNumber(this.zoomPercent) ?? 100) / 100 - ZOOM_STEP), MIN_ZOOM, MAX_ZOOM);
            canvas.zoomToPoint(canvas.getCenterPoint(), nextZoom);
            this._syncZoom();
            canvas.requestRenderAll();
        },

        resetZoom() {
            if (!canvas) {
                return;
            }

            canvas.zoomToPoint(canvas.getCenterPoint(), 1);
            this._syncZoom();
            canvas.requestRenderAll();
        },

        _syncZoom() {
            this.zoomPercent = canvas ? Math.round(canvas.getZoom() * 100) : 100;
        },

        async saveDraft() {
            if (savePromise) {
                return savePromise;
            }

            if (destroyed || !canvas) {
                throw new EditorRequestError('Editor desain belum siap.');
            }

            savePromise = this._performSave();

            try {
                return await savePromise;
            } finally {
                savePromise = null;
            }
        },

        async _performSave() {
            if (typeof this.productId === 'undefined' || this.productId === null || this.productId === '') {
                throw new EditorRequestError('Produk tidak valid.');
            }

            if (saveEndpoint === '' || (this.draftId !== null && this.draftId !== undefined && updateEndpointTemplate === '')) {
                throw new EditorRequestError('Endpoint penyimpanan desain tidak dikonfigurasi dengan benar.');
            }

            if (csrfToken === '') {
                throw new EditorRequestError('Token keamanan formulir tidak tersedia. Muat ulang halaman.');
            }

            const form = this.$refs?.productForm;
            validateForm(form);
            this.specification = readFormSpecification(form, this.specification);
            this.sideStates = {
                ...this.sideStates,
                [this.currentSide]: captureSide(this, this.currentSide),
            };

            // The server contract is `{ front: { elements: [...] }, back: { elements: [...] } }`.
            // The restore path tolerates a flat array, but saves must always use the
            // canonical shape or the elements would be silently dropped.
            const design = {
                front: { elements: this.sideStates.front.map((element) => ({ ...element })) },
                back: { elements: this.sideStates.back.map((element) => ({ ...element })) },
            };
            const body = {
                product_id: this.productId,
                specification: sanitizeSpecificationValue(this.specification) ?? {},
                design,
            };
            const hasDraft = this.draftId !== null && this.draftId !== undefined && this.draftId !== '';
            const url = hasDraft ? endpointFor(updateEndpointTemplate, this.draftId, 'pembaruan draft') : saveEndpoint;
            const revision = this.changeRevision;

            this.saving = true;
            this.error = '';
            saveAbortController = typeof AbortController === 'function' ? new AbortController() : null;

            try {
                const payload = await requestJson({
                    url,
                    method: hasDraft ? 'PUT' : 'POST',
                    body,
                    csrfToken,
                    signal: saveAbortController?.signal,
                });
                const record = isPlainObject(payload?.data?.draft)
                    ? payload.data.draft
                    : isPlainObject(payload?.draft)
                      ? payload.draft
                      : isPlainObject(payload?.data)
                        ? payload.data
                        : payload;
                const draftId = toSafeIdentifier(record?.id);

                if (draftId === null) {
                    throw new EditorRequestError('Server tidak mengembalikan ID draft desain.');
                }

                this.draftId = draftId;
                this.draftVersion = record.version ?? null;
                this.savedRevision = revision;
                this.hasUnsavedChanges = this.changeRevision !== this.savedRevision;
                updateDraftHiddenInput(this, this.draftId);
                this.statusMessage = 'Draft desain tersimpan.';

                return payload;
            } catch (error) {
                this.error = friendlyError(error, 'Draft desain gagal disimpan.');
                this.statusMessage = '';
                throw error;
            } finally {
                this.saving = false;
                saveAbortController = null;
            }
        },

        async prepareCartSubmission(event) {
            const submitter = resolveCartSubmitter(event);

            if (submitter === null) {
                return;
            }

            if (allowNextCartAction) {
                allowNextCartAction = false;
                this.continuing = false;
                return;
            }

            event?.preventDefault?.();

            if (this.continuing || destroyed) {
                return;
            }

            const form = this.$refs?.productForm;

            if (!isBrowserInstance('HTMLFormElement', form)) {
                this.error = 'Formulir produk tidak tersedia.';
                return;
            }

            this.continuing = true;
            this.error = '';

            try {
                await this.saveDraft();

                if (destroyed || !canvas) {
                    allowNextCartAction = false;
                    this.continuing = false;
                    return;
                }

                const blob = await exportPreviewBlob(canvas);
                attachPreviewFile(this.$refs?.previewInput, blob, this.currentSide);
                updateDraftHiddenInput(this, this.draftId);

                if (typeof this.$dispatch === 'function') {
                    this.$dispatch('design-editor:continue', {
                        draftId: this.draftId,
                        side: this.currentSide,
                    });
                }

                allowNextCartAction = true;
                this.continuing = false;

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter);
                } else if (typeof submitter?.click === 'function') {
                    submitter.click();
                } else {
                    allowNextCartAction = false;
                    globalThis.HTMLFormElement?.prototype.submit.call(form);
                }
            } catch (error) {
                allowNextCartAction = false;
                this.continuing = false;
                this.error = friendlyError(error, 'Pesanan tidak dapat dilanjutkan. Silakan periksa kembali.');
                this.statusMessage = '';
            }
        },

        destroy() {
            if (destroyed) {
                return;
            }

            destroyed = true;
            this._destroyed = true;
            requestState.destroyed = true;
            abortSideLoad();
            unbindAll();
            resizeObserver?.disconnect();
            resizeObserver = null;
            saveAbortController?.abort?.();
            saveAbortController = null;
            activeRequests.forEach((request) => request.abort?.());
            activeRequests.clear();

            if (canvas) {
                canvas.cancelRequestedRender?.();
                canvas.discardActiveObject?.();
                const disposeResult = canvas.dispose?.();

                if (disposeResult && typeof disposeResult.catch === 'function') {
                    disposeResult.catch(() => undefined);
                }
            }

            canvas = null;
            canvasHost = null;
            allowNextCartAction = false;
            pendingImageAction = null;
            savePromise = null;
        },
    };

    return editor;
}
