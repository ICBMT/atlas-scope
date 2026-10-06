/**
 * Finishes a build of the renderer bundle.
 *
 * Two jobs, both about the files the package serves rather than builds:
 *
 *   - the favicon is copied next to the bundle, so the package's own assets do
 *     not depend on the host application having one;
 *   - `assets/version` is written from a hash of everything in `assets/`. The
 *     layout appends it as a query string, so a rebuilt bundle is a new URL and
 *     no browser serves a stale copy of the renderer.
 */
import { createHash } from 'node:crypto';
import { copyFileSync, readdirSync, readFileSync, statSync, writeFileSync } from 'node:fs';
import { join, relative } from 'node:path';

const dist = new URL('../assets/', import.meta.url).pathname;
const root = new URL('../', import.meta.url).pathname;

copyFileSync(join(root, 'resources/favicon.svg'), join(dist, 'favicon.svg'));

const walk = (dir) => readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const path = join(dir, entry.name);

    return entry.isDirectory() ? walk(path) : [path];
});

const hash = createHash('sha256');

for (const file of walk(dist).filter((f) => !f.endsWith('version')).sort()) {
    hash.update(relative(dist, file));
    hash.update(readFileSync(file));
}

const version = hash.digest('hex').slice(0, 12);

writeFileSync(join(dist, 'version'), version + '\n');

const size = walk(dist).reduce((total, file) => total + statSync(file).size, 0);

console.log(`assets/ stamped: version ${version}, ${(size / 1024).toFixed(0)} kB across ${walk(dist).length} files`);
