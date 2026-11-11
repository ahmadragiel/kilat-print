/**
 * Crop maths for the auto-fit photo frames.
 *
 * Everything here is plain arithmetic so the same rules run in the browser and in
 * the Node tests. Source images are square-cropped, so a slot can store one
 * `crop_width`/`crop_height` pair without distorting the face.
 */

export const MIN_PHOTO_ZOOM = 1;
export const MAX_PHOTO_ZOOM = 3;
export const DEFAULT_OVERSCAN = 1.4;

export function clampNumber(value, minimum, maximum) {
    const numeric = Number(value);

    if (!Number.isFinite(numeric)) {
        return minimum;
    }

    return Math.min(maximum, Math.max(minimum, numeric));
}

export function cropCenter(crop) {
    return {
        x: crop.x + crop.width / 2,
        y: crop.y + crop.height / 2,
    };
}

export function clampCrop(crop, imageWidth, imageHeight) {
    const width = Math.max(1, Number(imageWidth) || 1);
    const height = Math.max(1, Number(imageHeight) || 1);
    const sideLimit = Math.max(1, Math.min(width, height));
    const size = clampNumber(crop?.width ?? sideLimit, 1, sideLimit);
    const centerX = clampNumber(crop?.x ?? 0, 0, width) + size / 2;
    const centerY = clampNumber(crop?.y ?? 0, 0, height) + size / 2;

    return {
        x: Math.min(Math.max(0, centerX - size / 2), Math.max(0, width - size)),
        y: Math.min(Math.max(0, centerY - size / 2), Math.max(0, height - size)),
        width: size,
        height: size,
    };
}

export function squareCrop(centerX, centerY, size, imageWidth, imageHeight) {
    const width = Math.max(1, Number(imageWidth) || 1);
    const height = Math.max(1, Number(imageHeight) || 1);
    const side = clampNumber(size, 1, Math.min(width, height));
    const center = {
        x: clampNumber(centerX, 0, width),
        y: clampNumber(centerY, 0, height),
    };

    return clampCrop(
        {
            x: center.x - side / 2,
            y: center.y - side / 2,
            width: side,
        },
        width,
        height,
    );
}

export function centerCrop(imageWidth, imageHeight) {
    const width = Math.max(1, Number(imageWidth) || 1);
    const height = Math.max(1, Number(imageHeight) || 1);
    const side = Math.min(width, height);

    return squareCrop(width / 2, height / 2, side, width, height);
}

export function faceCrop(box, imageWidth, imageHeight, padding = 0.28) {
    const width = Math.max(1, Number(imageWidth) || 1);
    const height = Math.max(1, Number(imageHeight) || 1);
    const boxX = Number(box?.x ?? box?.originX ?? 0);
    const boxY = Number(box?.y ?? box?.originY ?? 0);
    const boxWidth = Math.max(1, Number(box?.width) || 1);
    const boxHeight = Math.max(1, Number(box?.height) || 1);
    const pad = clampNumber(padding, 0, 2);
    const side = Math.min(
        Math.min(width, height),
        Math.max(boxWidth, boxHeight) * (1 + pad * 2),
    );

    return squareCrop(boxX + boxWidth / 2, boxY + boxHeight / 2, side, width, height);
}

/**
 * The first crop shown in a slot: the face crop plus the overscan margin that
 * makes dragging inside the fixed circular frame possible without exposing blank
 * space.
 */
export function initialSlotCrop(baseCrop, overscan, imageWidth, imageHeight) {
    const margin = clampNumber(overscan, 1, 3);
    const side = Math.min(
        Math.min(imageWidth, imageHeight),
        Math.max(1, Number(baseCrop?.width) || 1) * margin,
    );
    const center = cropCenter(baseCrop);

    return squareCrop(center.x, center.y, side, imageWidth, imageHeight);
}

export function slotDisplaySize(frameSize, overscan) {
    return Math.max(1, Number(frameSize) || 1) * clampNumber(overscan, 1, 3);
}

export function maxPhotoOffset(frameSize, overscan) {
    return Math.max(0, (slotDisplaySize(frameSize, overscan) - Math.max(1, Number(frameSize) || 1)) / 2);
}

export function clampPhotoOffset(offset, frameSize, overscan) {
    const limit = maxPhotoOffset(frameSize, overscan);

    return {
        x: clampNumber(offset?.x ?? 0, -limit, limit),
        y: clampNumber(offset?.y ?? 0, -limit, limit),
    };
}

export function cropAtZoom(crop, currentZoom, targetZoom, imageWidth, imageHeight) {
    const fromZoom = clampNumber(currentZoom, MIN_PHOTO_ZOOM, MAX_PHOTO_ZOOM);
    const toZoom = clampNumber(targetZoom, MIN_PHOTO_ZOOM, MAX_PHOTO_ZOOM);
    const side = Math.max(1, Number(crop?.width) || 1);
    const baseSide = side * fromZoom;
    const nextSide = baseSide / toZoom;
    const center = cropCenter(crop);
    const result = squareCrop(center.x, center.y, nextSide, imageWidth, imageHeight);

    return {
        ...result,
        zoom: toZoom,
    };
}
