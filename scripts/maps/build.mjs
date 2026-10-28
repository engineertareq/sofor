/**
 * Builds the travel maps: `npm run maps:build` (all) or `npm run maps:build -- bd`.
 *
 * Reads the open boundary files listed in maps.config.mjs (downloaded once
 * into scripts/maps/.cache), simplifies them without opening gaps between
 * neighbours, projects them to SVG paths and writes resources/maps/<key>.json.
 * The app only ever reads those JSON files, so none of this runs in production.
 */
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { geoArea, geoEqualEarth, geoMercator, geoPath } from 'd3-geo';
import { feature as toFeatures } from 'topojson-client';
import { topology } from 'topojson-server';
import { presimplify, quantile, simplify, sphericalTriangleArea } from 'topojson-simplify';
import maps from './maps.config.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const cacheDir = join(here, '.cache');
const outDir = join(here, '..', '..', 'resources', 'maps');

/** Areas smaller than this (px² at the map's own size) are flagged tiny: once visited they get a dot, so they show. */
const TINY_AREA = 30;

const projections = { mercator: geoMercator, equalEarth: geoEqualEarth };

async function loadSource(map) {
    const file = join(cacheDir, map.source.file);

    try {
        return JSON.parse(await readFile(file, 'utf8'));
    } catch {
        console.log(`  downloading ${map.source.url}`);
    }

    const response = await fetch(map.source.url);

    if (!response.ok) {
        throw new Error(`Download failed (${response.status}): ${map.source.url}`);
    }

    const text = await response.text();
    await mkdir(cacheDir, { recursive: true });
    await writeFile(file, text);

    return JSON.parse(text);
}

/**
 * d3 draws polygons by spherical winding: a ring wound the "wrong" way (as
 * RFC 7946 files often are) means "the whole globe except this", which
 * renders as one giant filled shape. Flip any polygon bigger than a hemisphere.
 */
function rewind(geometry) {
    const fix = (rings) => (geoArea({ type: 'Polygon', coordinates: rings }) > 2 * Math.PI
        ? rings.map((ring) => [...ring].reverse())
        : rings);

    if (geometry.type === 'Polygon') {
        return { ...geometry, coordinates: fix(geometry.coordinates) };
    }

    if (geometry.type === 'MultiPolygon') {
        return { ...geometry, coordinates: geometry.coordinates.map(fix) };
    }

    return geometry;
}

/** Drop the pieces of a multi-part area smaller than minArea px², always keeping its biggest piece. */
function prune(path, geometry, minArea) {
    if (geometry.type !== 'MultiPolygon') {
        return geometry;
    }

    const parts = geometry.coordinates.map((coordinates) => ({ coordinates, area: path.area({ type: 'Polygon', coordinates }) }));
    const largest = Math.max(...parts.map((part) => part.area));
    const kept = parts.filter((part) => part.area >= minArea || part.area === largest).map((part) => part.coordinates);

    return { type: 'MultiPolygon', coordinates: kept };
}

/** The middle of the biggest piece, so a label or dot never lands in the sea between islands. */
function anchor(path, geometry) {
    const polygons = geometry.type === 'MultiPolygon'
        ? geometry.coordinates.map((coordinates) => ({ type: 'Polygon', coordinates }))
        : [geometry];

    const largest = polygons.reduce((best, polygon) => (path.area(polygon) > path.area(best) ? polygon : best));

    return path.centroid(largest).map((value) => Math.round(value * 10) / 10);
}

async function build(key, map) {
    console.log(`\n${key}`);

    const source = await loadSource(map);
    const usedIds = new Set();
    const ordered = [...source.features].sort((a, b) => (map.order?.(a) ?? 0) - (map.order?.(b) ?? 0));
    const entries = [];

    for (const feature of ordered) {
        const meta = map.read(feature, usedIds);

        if (meta === null) {
            continue;
        }

        if (usedIds.has(meta.id)) {
            throw new Error(`Duplicate area id ${meta.id} (${meta.name})`);
        }

        usedIds.add(meta.id);
        entries.push({ meta, feature: { type: 'Feature', id: meta.id, properties: {}, geometry: rewind(feature.geometry) } });
    }

    const projection = projections[map.projection]().fitWidth(map.width, {
        type: 'FeatureCollection',
        features: entries.map((entry) => entry.feature),
    });
    const path = geoPath(projection).digits(1);

    // Islands smaller than a pixel cannot be seen, but every ring costs bytes.
    for (const entry of entries) {
        entry.feature.geometry = prune(path, entry.feature.geometry, map.minPolygonArea ?? 0.5);
    }

    const collection = { type: 'FeatureCollection', features: entries.map((entry) => entry.feature) };
    const height = Math.ceil(path.bounds(collection)[1][1]);

    // Simplify on a shared topology, so neighbouring areas keep one common border.
    // quantile(p) is the weight that keeps roughly the share p of all points.
    const topo = presimplify(topology({ areas: collection }, 1e5), sphericalTriangleArea);
    const simplified = simplify(topo, quantile(topo, map.keep));
    const simpleById = new Map(toFeatures(simplified, simplified.objects.areas).features.map((f) => [f.id, f]));

    const areas = entries.map(({ meta, feature }) => {
        const area = path.area(feature);

        if (area > map.width * height * 0.6) {
            throw new Error(`${meta.name} covers the whole map - check its winding`);
        }

        const [cx, cy] = anchor(path, feature.geometry);
        const d = path(simpleById.get(meta.id)) ?? '';

        return {
            id: meta.id,
            name: meta.name,
            group: meta.group,
            ...(meta.aliases.length ? { aliases: meta.aliases } : {}),
            ...(meta.counted ? {} : { counted: false }),
            ...(area < (map.tinyArea ?? TINY_AREA) ? { tiny: true } : {}),
            cx,
            cy,
            d,
        };
    }).sort((a, b) => a.name.localeCompare(b.name));

    const counted = areas.filter((area) => area.counted !== false).length;

    if (map.expectAreas && areas.length !== map.expectAreas) {
        throw new Error(`Expected ${map.expectAreas} areas, built ${areas.length}`);
    }

    if (map.expectCounted && counted !== map.expectCounted) {
        throw new Error(`Expected ${map.expectCounted} counted areas, built ${counted}`);
    }

    const output = JSON.stringify({
        key,
        attribution: map.attribution,
        width: map.width,
        height,
        groups: map.groups,
        counted,
        areas,
    });

    await mkdir(outDir, { recursive: true });
    await writeFile(join(outDir, `${key}.json`), output + '\n');

    console.log(`  ${areas.length} areas (${counted} counted), ${map.width}x${height}, ${(output.length / 1024).toFixed(0)} KB`);
    console.log(`  tiny: ${areas.filter((a) => a.tiny).map((a) => a.name).join(', ') || 'none'}`);
}

const only = process.argv.slice(2);

for (const [key, map] of Object.entries(maps)) {
    if (only.length === 0 || only.includes(key)) {
        await build(key, map);
    }
}
