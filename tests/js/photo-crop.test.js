import assert from 'node:assert/strict';
import { describe, it } from 'node:test';

import {
    MAX_PHOTO_ZOOM,
    MIN_PHOTO_ZOOM,
    centerCrop,
    clampCrop,
    clampPhotoOffset,
    cropAtZoom,
    cropCenter,
    faceCrop,
    initialSlotCrop,
    maxPhotoOffset,
    slotDisplaySize,
    squareCrop,
} from '../../resources/js/photo-crop.js';

/**
 * Decimal crop maths can land a few ULPs away from the expected value (for
 * example 13.599999999999994 instead of 13.6), so every geometric assertion uses
 * a small tolerance instead of strict equality.
 */
const EPSILON = 1e-9;

function assertClose(actual, expected, label = 'value') {
    assert.ok(
        Math.abs(actual - expected) <= EPSILON,
        `${label}: expected ${expected}, received ${actual}`,
    );
}

function assertCropClose(actual, expected) {
    for (const [key, value] of Object.entries(expected)) {
        assertClose(actual[key], value, key);
    }
}

describe('clampCrop', () => {
    it('keeps a crop that already fits', () => {
        assertCropClose(clampCrop({ x: 20, y: 30, width: 100 }, 300, 200), {
            x: 20,
            y: 30,
            width: 100,
            height: 100,
        });
    });

    it('shrinks an oversized crop to the shortest source side', () => {
        assertCropClose(clampCrop({ x: 0, y: 0, width: 500 }, 300, 200), {
            x: 0,
            y: 0,
            width: 200,
            height: 200,
        });
    });

    it('pulls a crop back inside every edge', () => {
        assertCropClose(clampCrop({ x: -80, y: -90, width: 100 }, 400, 300), {
            x: 0,
            y: 0,
            width: 100,
            height: 100,
        });
        assertCropClose(clampCrop({ x: 380, y: 280, width: 100 }, 400, 300), {
            x: 300,
            y: 200,
            width: 100,
            height: 100,
        });
    });

    it('never returns a zero-sized crop', () => {
        const crop = clampCrop({ x: 0, y: 0, width: 0 }, 100, 100);

        assert.equal(crop.width, 1);
        assert.equal(crop.height, 1);
    });

    it('falls back to the shortest side for invalid dimensions', () => {
        assertCropClose(clampCrop({ x: 0, y: 0, width: 50 }, 0, -10), {
            x: 0,
            y: 0,
            width: 1,
            height: 1,
        });
    });
});

describe('centerCrop / squareCrop', () => {
    it('centres a landscape source', () => {
        assertCropClose(centerCrop(800, 600), { x: 100, y: 0, width: 600, height: 600 });
    });

    it('centres a portrait source', () => {
        assertCropClose(centerCrop(600, 800), { x: 0, y: 100, width: 600, height: 600 });
    });

    it('keeps a square source unchanged', () => {
        assertCropClose(centerCrop(512, 512), { x: 0, y: 0, width: 512, height: 512 });
    });

    it('clamps a requested square to the source edges', () => {
        assertCropClose(squareCrop(0, 0, 200, 400, 300), { x: 0, y: 0, width: 200, height: 200 });
        assertCropClose(squareCrop(400, 300, 200, 400, 300), { x: 200, y: 100, width: 200, height: 200 });
    });
});

describe('faceCrop', () => {
    it('adds symmetric padding around the face box', () => {
        assertCropClose(faceCrop({ x: 100, y: 100, width: 100, height: 100 }, 800, 800, 0.28), {
            x: 72,
            y: 72,
            width: 156,
            height: 156,
        });
    });

    it('uses the larger box side so the crop stays square', () => {
        const crop = faceCrop({ x: 100, y: 100, width: 100, height: 50 }, 800, 800, 0.5);

        assert.equal(crop.width, crop.height);
        assertClose(crop.width, 200, 'width');
    });

    it('clamps padding at the image edges instead of exposing blank space', () => {
        const crop = faceCrop({ x: 0, y: 0, width: 100, height: 100 }, 400, 400, 0.5);

        assertCropClose(crop, { x: 0, y: 0, width: 200, height: 200 });
    });

    it('never exceeds the source and never shrinks below one pixel', () => {
        const crop = faceCrop({ x: 0, y: 0, width: 400, height: 400 }, 400, 400, 2);

        assertCropClose(crop, { x: 0, y: 0, width: 400, height: 400 });
    });

    it('accepts origin-style boxes', () => {
        assertCropClose(faceCrop({ originX: 40, originY: 60, width: 20, height: 20 }, 200, 200, 0), {
            x: 40,
            y: 60,
            width: 20,
            height: 20,
        });
    });
});

describe('initialSlotCrop', () => {
    it('adds the overscan margin around the base crop', () => {
        assertCropClose(initialSlotCrop({ x: 100, y: 100, width: 100, height: 100 }, 1.4, 800, 800), {
            x: 80,
            y: 80,
            width: 140,
            height: 140,
        });
    });

    it('clamps the overscanned crop at the source edge', () => {
        assertCropClose(initialSlotCrop({ x: 0, y: 0, width: 100, height: 100 }, 1.4, 800, 800), {
            x: 0,
            y: 0,
            width: 140,
            height: 140,
        });
    });
});

describe('zoom helpers', () => {
    it('reports the displayed slot size with overscan', () => {
        assertClose(slotDisplaySize(68, 1.4), 95.2, 'display size');
        assertClose(slotDisplaySize(0, 1.4), 1.4, 'minimum display size');
    });

    it('caps the drag offset at half of the overscan margin', () => {
        assertClose(maxPhotoOffset(68, 1.4), 13.6, 'maximum offset');
        assert.equal(maxPhotoOffset(68, 1), 0);
        assertCropClose(clampPhotoOffset({ x: 50, y: -50 }, 68, 1.4), { x: 13.6, y: -13.6 });
        assertCropClose(clampPhotoOffset({}, 68, 1.4), { x: 0, y: 0 });
    });

    it('zooms in around the current crop centre', () => {
        assertCropClose(cropAtZoom({ x: 0, y: 0, width: 100, height: 100 }, 1, 2, 400, 400), {
            x: 25,
            y: 25,
            width: 50,
            height: 50,
            zoom: 2,
        });
    });

    it('zooms out and clamps at the source edges', () => {
        assertCropClose(cropAtZoom({ x: 50, y: 50, width: 50, height: 50 }, 2, 1, 400, 400), {
            x: 25,
            y: 25,
            width: 100,
            height: 100,
            zoom: 1,
        });
    });

    it('keeps the zoom factor inside the supported range', () => {
        assert.equal(cropAtZoom({ x: 0, y: 0, width: 100, height: 100 }, 1, 99, 400, 400).zoom, MAX_PHOTO_ZOOM);
        assert.equal(cropAtZoom({ x: 0, y: 0, width: 100, height: 100 }, 1, -5, 400, 400).zoom, MIN_PHOTO_ZOOM);
    });

    it('reports crop centres', () => {
        assertCropClose(cropCenter({ x: 10, y: 20, width: 100, height: 40 }), { x: 60, y: 40 });
    });
});
