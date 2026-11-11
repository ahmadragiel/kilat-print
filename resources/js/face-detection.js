/**
 * Local face detection for the auto-fit photo frames.
 *
 * The MediaPipe runtime and model are served from this application. The customer's
 * photo is decoded in the browser, passed to the detector, and never uploaded to a
 * face-detection service.
 */

import { faceCrop } from './photo-crop';

let detectorPromise = null;

function loadImage(file) {
    return new Promise((resolve, reject) => {
        if (typeof Image !== 'function' || typeof URL?.createObjectURL !== 'function') {
            reject(new Error('Browser tidak mendukung pemrosesan foto.'));
            return;
        }

        const objectUrl = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => resolve({ image, objectUrl });
        image.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            reject(new Error('Foto tidak dapat dibaca.'));
        };
        image.src = objectUrl;
    });
}

async function buildDetector(configuration) {
    const vision = await import('@mediapipe/tasks-vision');
    const fileset = await vision.FilesetResolver.forVisionTasks(configuration.wasmPath);
    const baseOptions = {
        modelAssetPath: configuration.modelPath,
        delegate: 'GPU',
    };
    const options = {
        baseOptions,
        runningMode: 'IMAGE',
        minDetectionConfidence: 0.5,
    };

    try {
        return await vision.FaceDetector.createFromOptions(fileset, options);
    } catch {
        baseOptions.delegate = 'CPU';

        return vision.FaceDetector.createFromOptions(fileset, options);
    }
}

function detectorFor(configuration) {
    if (detectorPromise === null) {
        detectorPromise = buildDetector(configuration).catch((error) => {
            detectorPromise = null;
            throw error;
        });
    }

    return detectorPromise;
}

function readBox(detection) {
    const box = detection?.boundingBox ?? detection?.bounding_box ?? {};

    return {
        x: Number(box.originX ?? box.x ?? 0),
        y: Number(box.originY ?? box.y ?? 0),
        width: Math.max(1, Number(box.width) || 1),
        height: Math.max(1, Number(box.height) || 1),
    };
}

function readScore(detection) {
    const scores = detection?.categories ?? detection?.scores ?? [];
    const score = Array.isArray(scores) && scores.length > 0 ? scores[0]?.score : detection?.score;

    return Number.isFinite(Number(score)) ? Number(score) : 0;
}

function makeThumbnail(image, crop, size = 160) {
    if (typeof document === 'undefined') {
        return null;
    }

    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const context = canvas.getContext('2d');

    if (context === null) {
        return null;
    }

    context.drawImage(image, crop.x, crop.y, crop.width, crop.height, 0, 0, size, size);

    try {
        return canvas.toDataURL('image/jpeg', 0.82);
    } catch {
        return null;
    }
}

export async function readImageDimensions(file) {
    const { image, objectUrl } = await loadImage(file);

    try {
        return {
            width: Math.max(1, image.naturalWidth || image.width || 1),
            height: Math.max(1, image.naturalHeight || image.height || 1),
        };
    } finally {
        URL.revokeObjectURL(objectUrl);
    }
}

/**
 * @returns {Promise<{faces: Array<object>, width: number, height: number, lowResolution: boolean}>}
 */
export async function detectFaceCandidates(file, configuration = {}) {
    const wasmPath = typeof configuration.wasmPath === 'string' ? configuration.wasmPath : '';
    const modelPath = typeof configuration.modelPath === 'string' ? configuration.modelPath : '';

    if (wasmPath === '' || modelPath === '') {
        throw new Error('Deteksi wajah belum dikonfigurasi.');
    }

    const padding = Number.isFinite(Number(configuration.facePadding))
        ? Number(configuration.facePadding)
        : 0.28;
    const minimumDimension = Math.max(1, Number(configuration.minSourceDimension) || 800);
    const { image, objectUrl } = await loadImage(file);

    try {
        const width = Math.max(1, image.naturalWidth || image.width || 1);
        const height = Math.max(1, image.naturalHeight || image.height || 1);
        const detector = await detectorFor({ wasmPath, modelPath });
        const result = detector.detect(image);
        const detections = Array.isArray(result?.detections) ? result.detections : [];
        const faces = detections
            .map((detection, index) => {
                const box = readBox(detection);
                const crop = faceCrop(box, width, height, padding);

                return {
                    id: `face-${index}`,
                    box,
                    score: readScore(detection),
                    area: box.width * box.height,
                    crop,
                    thumbnail: makeThumbnail(image, crop),
                };
            })
            .sort((left, right) => right.area - left.area)
            .map((face, index) => ({ ...face, id: `face-${index}` }));

        return {
            faces,
            width,
            height,
            lowResolution: Math.min(width, height) < minimumDimension,
        };
    } finally {
        URL.revokeObjectURL(objectUrl);
    }
}
