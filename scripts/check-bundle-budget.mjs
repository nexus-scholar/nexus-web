import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { gzipSync } from 'node:zlib';

const buildDir = path.resolve('public/build');
const assetsDir = path.join(buildDir, 'assets');
const manifestPath = path.join(buildDir, 'manifest.json');

const budgets = {
    rootAppRawKiB: 20,
    rootAppGzipKiB: 8,
    jsChunkRawKiB: 250,
    jsChunkGzipKiB: 90,
    authImageRawKiB: 120,
};

function sizeKiB(bytes) {
    return bytes / 1024;
}

function formatKiB(bytes) {
    return `${sizeKiB(bytes).toFixed(2)} KiB`;
}

function readAsset(relativeFile) {
    const filePath = path.join(buildDir, relativeFile);
    const buffer = readFileSync(filePath);

    return {
        file: relativeFile,
        rawBytes: buffer.byteLength,
        gzipBytes: gzipSync(buffer).byteLength,
    };
}

function fail(message) {
    console.error(`Bundle budget failed: ${message}`);
    process.exitCode = 1;
}

if (!existsSync(manifestPath) || !existsSync(assetsDir)) {
    fail('run `npm run build` before `npm run bundle:check`.');
    process.exit();
}

const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'));
const rootEntry = manifest['resources/js/app.tsx'];

if (!rootEntry?.file) {
    fail('missing resources/js/app.tsx in public/build/manifest.json.');
    process.exit();
}

const rootAsset = readAsset(rootEntry.file);
const jsAssets = readdirSync(assetsDir)
    .filter((file) => file.endsWith('.js'))
    .map((file) => readAsset(path.posix.join('assets', file)))
    .sort((a, b) => b.rawBytes - a.rawBytes);

const authImage = readdirSync(assetsDir).find((file) =>
    /^auth-evidence-workspace-.*\.webp$/.test(file),
);

console.log('Bundle budget report');
console.log(
    `- root app: ${rootAsset.file}, raw ${formatKiB(rootAsset.rawBytes)}, gzip ${formatKiB(rootAsset.gzipBytes)}`,
);

for (const asset of jsAssets.slice(0, 5)) {
    console.log(
        `- js chunk: ${asset.file}, raw ${formatKiB(asset.rawBytes)}, gzip ${formatKiB(asset.gzipBytes)}`,
    );
}

if (authImage) {
    const authImagePath = path.join(assetsDir, authImage);
    const authImageBytes = statSync(authImagePath).size;
    console.log(
        `- auth image: assets/${authImage}, raw ${formatKiB(authImageBytes)}`,
    );

    if (sizeKiB(authImageBytes) > budgets.authImageRawKiB) {
        fail(
            `auth image is ${formatKiB(authImageBytes)}; limit is ${budgets.authImageRawKiB} KiB.`,
        );
    }
}

if (sizeKiB(rootAsset.rawBytes) > budgets.rootAppRawKiB) {
    fail(
        `root app raw size is ${formatKiB(rootAsset.rawBytes)}; limit is ${budgets.rootAppRawKiB} KiB.`,
    );
}

if (sizeKiB(rootAsset.gzipBytes) > budgets.rootAppGzipKiB) {
    fail(
        `root app gzip size is ${formatKiB(rootAsset.gzipBytes)}; limit is ${budgets.rootAppGzipKiB} KiB.`,
    );
}

for (const asset of jsAssets) {
    if (sizeKiB(asset.rawBytes) > budgets.jsChunkRawKiB) {
        fail(
            `${asset.file} raw size is ${formatKiB(asset.rawBytes)}; limit is ${budgets.jsChunkRawKiB} KiB.`,
        );
    }

    if (sizeKiB(asset.gzipBytes) > budgets.jsChunkGzipKiB) {
        fail(
            `${asset.file} gzip size is ${formatKiB(asset.gzipBytes)}; limit is ${budgets.jsChunkGzipKiB} KiB.`,
        );
    }
}

if (!process.exitCode) {
    console.log('Bundle budget passed.');
}
