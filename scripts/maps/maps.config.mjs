/**
 * Travel map definitions for `npm run maps:build`.
 *
 * Each entry turns one open boundary file into resources/maps/<key>.json: SVG
 * paths, names and codes the app renders and stores visits against. To add a
 * country's provinces or states later:
 *
 *   1. Add an entry here (source file, projection, how to read each feature).
 *   2. Add the same key to config/travel_maps.php (labels, parent country).
 *   3. Optionally add resources/maps/places/<key>.php so trips can suggest it.
 *   4. Run `npm run maps:build` and commit the generated JSON.
 *
 * Area ids are ISO codes (ISO 3166-1 for countries, ISO 3166-2 for
 * subdivisions), so stored visits never depend on a data source's own names.
 */

/** ISO 3166-2:BD districts with their division. */
const BD_DISTRICTS = {
    'BD-01': ['Bandarban', 'BD-B'], 'BD-02': ['Barguna', 'BD-A'], 'BD-03': ['Bogura', 'BD-E'],
    'BD-04': ['Brahmanbaria', 'BD-B'], 'BD-05': ['Bagerhat', 'BD-D'], 'BD-06': ['Barishal', 'BD-A'],
    'BD-07': ['Bhola', 'BD-A'], 'BD-08': ['Cumilla', 'BD-B'], 'BD-09': ['Chandpur', 'BD-B'],
    'BD-10': ['Chattogram', 'BD-B'], 'BD-11': ["Cox's Bazar", 'BD-B'], 'BD-12': ['Chuadanga', 'BD-D'],
    'BD-13': ['Dhaka', 'BD-C'], 'BD-14': ['Dinajpur', 'BD-F'], 'BD-15': ['Faridpur', 'BD-C'],
    'BD-16': ['Feni', 'BD-B'], 'BD-17': ['Gopalganj', 'BD-C'], 'BD-18': ['Gazipur', 'BD-C'],
    'BD-19': ['Gaibandha', 'BD-F'], 'BD-20': ['Habiganj', 'BD-G'], 'BD-21': ['Jamalpur', 'BD-H'],
    'BD-22': ['Jashore', 'BD-D'], 'BD-23': ['Jhenaidah', 'BD-D'], 'BD-24': ['Joypurhat', 'BD-E'],
    'BD-25': ['Jhalakathi', 'BD-A'], 'BD-26': ['Kishoreganj', 'BD-C'], 'BD-27': ['Khulna', 'BD-D'],
    'BD-28': ['Kurigram', 'BD-F'], 'BD-29': ['Khagrachhari', 'BD-B'], 'BD-30': ['Kushtia', 'BD-D'],
    'BD-31': ['Lakshmipur', 'BD-B'], 'BD-32': ['Lalmonirhat', 'BD-F'], 'BD-33': ['Manikganj', 'BD-C'],
    'BD-34': ['Mymensingh', 'BD-H'], 'BD-35': ['Munshiganj', 'BD-C'], 'BD-36': ['Madaripur', 'BD-C'],
    'BD-37': ['Magura', 'BD-D'], 'BD-38': ['Moulvibazar', 'BD-G'], 'BD-39': ['Meherpur', 'BD-D'],
    'BD-40': ['Narayanganj', 'BD-C'], 'BD-41': ['Netrakona', 'BD-H'], 'BD-42': ['Narsingdi', 'BD-C'],
    'BD-43': ['Narail', 'BD-D'], 'BD-44': ['Natore', 'BD-E'], 'BD-45': ['Chapai Nawabganj', 'BD-E'],
    'BD-46': ['Nilphamari', 'BD-F'], 'BD-47': ['Noakhali', 'BD-B'], 'BD-48': ['Naogaon', 'BD-E'],
    'BD-49': ['Pabna', 'BD-E'], 'BD-50': ['Pirojpur', 'BD-A'], 'BD-51': ['Patuakhali', 'BD-A'],
    'BD-52': ['Panchagarh', 'BD-F'], 'BD-53': ['Rajbari', 'BD-C'], 'BD-54': ['Rajshahi', 'BD-E'],
    'BD-55': ['Rangpur', 'BD-F'], 'BD-56': ['Rangamati', 'BD-B'], 'BD-57': ['Sherpur', 'BD-H'],
    'BD-58': ['Satkhira', 'BD-D'], 'BD-59': ['Sirajganj', 'BD-E'], 'BD-60': ['Sylhet', 'BD-G'],
    'BD-61': ['Sunamganj', 'BD-G'], 'BD-62': ['Shariatpur', 'BD-C'], 'BD-63': ['Tangail', 'BD-C'],
    'BD-64': ['Thakurgaon', 'BD-F'],
};

/** Older spellings used by the boundary file, mapped to today's official names. */
const BD_SOURCE_NAMES = {
    Barisal: 'Barishal', Bogra: 'Bogura', Brahamanbaria: 'Brahmanbaria', Chittagong: 'Chattogram',
    Comilla: 'Cumilla', Jessore: 'Jashore', Jhalokati: 'Jhalakathi', Maulvibazar: 'Moulvibazar',
    Nawabganj: 'Chapai Nawabganj',
};

const BD_BY_NAME = Object.fromEntries(Object.entries(BD_DISTRICTS).map(([code, [name]]) => [name, code]));

/** Display names where Natural Earth's English name reads oddly. */
const WORLD_NAMES = { CN: 'China', US: 'United States', CZ: 'Czechia', MO: 'Macao', TL: 'Timor-Leste' };

/** Extra search words people actually type. */
const WORLD_ALIASES = {
    US: ['USA', 'America'], GB: ['UK', 'Britain', 'England', 'Scotland', 'Wales'], AE: ['UAE', 'Emirates'],
    KR: ['Korea'], NL: ['Holland'], MM: ['Burma'], CZ: ['Czech Republic'], TR: ['Turkiye', 'Türkiye'],
    SA: ['KSA'], CD: ['DRC'], CI: ["Cote d'Ivoire"], VA: ['Vatican'],
};

/** Natural Earth puts small island states in "Seven seas"; place them by subregion. */
const SUBREGION_CONTINENT = {
    'Southern Asia': 'Asia', 'South-Eastern Asia': 'Asia', 'Eastern Africa': 'Africa', 'Western Africa': 'Africa',
    'Southern Africa': 'Africa', 'Middle Africa': 'Africa', 'Caribbean': 'North America', 'Melanesia': 'Oceania',
    'Micronesia': 'Oceania', 'Polynesia': 'Oceania', 'South America': 'South America',
};

/** Remote territories Natural Earth leaves in the open ocean with no subregion either. */
const WORLD_CONTINENT_BY_ID = { GS: 'South America', IO: 'Asia', TF: 'Africa', HM: 'Oceania' };

/** Not countries anybody lists as visited. */
const WORLD_SKIP = new Set(['ATA', 'KAS']);

/** Sovereign entries Natural Earth types as "Country" alongside their own dependencies. */
const WORLD_SOVEREIGN_COUNTRY_TYPE = new Set(['US', 'GB', 'NZ', 'NL', 'FR', 'FI', 'DK', 'CN', 'AU']);

/** Counted alongside the 193 UN members: the two UN observer states. */
const WORLD_OBSERVERS = new Set(['VA', 'PS']);

/** On many maps, but not UN members, so they do not count towards the 195. */
const WORLD_NOT_COUNTED = new Set(['TW', 'XK']);

export default {
    bd: {
        source: {
            url: 'https://github.com/wmgeolab/geoBoundaries/raw/9469f09/releaseData/gbOpen/BGD/ADM2/geoBoundaries-BGD-ADM2_simplified.geojson',
            file: 'geoBoundaries-BGD-ADM2_simplified.geojson',
        },
        attribution: 'District boundaries: Bangladesh Bureau of Statistics via geoBoundaries (CC BY 3.0 IGO)',
        projection: 'mercator',
        width: 600,
        // Share of points kept after simplification; shared borders stay shared.
        keep: 0.12,
        expectAreas: 64,
        groups: {
            'BD-A': 'Barishal', 'BD-B': 'Chattogram', 'BD-C': 'Dhaka', 'BD-D': 'Khulna',
            'BD-E': 'Rajshahi', 'BD-F': 'Rangpur', 'BD-G': 'Sylhet', 'BD-H': 'Mymensingh',
        },
        read(feature) {
            const sourceName = feature.properties.shapeName;
            const name = BD_SOURCE_NAMES[sourceName] ?? sourceName;
            const id = BD_BY_NAME[name];

            if (!id) {
                throw new Error(`Unknown Bangladesh district in source: ${sourceName}`);
            }

            const [official, group] = BD_DISTRICTS[id];
            const aliases = sourceName !== official ? [sourceName] : [];

            return { id, name: official, group, aliases, counted: true };
        },
    },

    world: {
        source: {
            url: 'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/v5.1.2/geojson/ne_50m_admin_0_countries.geojson',
            file: 'ne_50m_admin_0_countries.geojson',
        },
        attribution: 'Country borders: Natural Earth (public domain)',
        projection: 'equalEarth',
        width: 1000,
        keep: 0.1,
        tinyArea: 8,
        expectCounted: 195,
        groups: {
            Africa: 'Africa', Asia: 'Asia', Europe: 'Europe', 'North America': 'North America',
            'South America': 'South America', Oceania: 'Oceania',
        },
        // Sovereign states first, so a dependency never takes its country's code.
        order(feature) {
            return ['Sovereign country', 'Country', 'Sovereignty'].includes(feature.properties.TYPE) ? 0 : 1;
        },
        read(feature, usedIds) {
            const p = feature.properties;

            if (WORLD_SKIP.has(p.ADM0_A3)) {
                return null;
            }

            const iso = p.ISO_A2_EH;
            // Unrecognised places, and dependencies sharing their country's code, keep a 3 letter id.
            const id = iso !== '-99' && !usedIds.has(iso) ? iso : p.ADM0_A3;
            const name = WORLD_NAMES[id] ?? p.NAME_EN;
            const group = WORLD_CONTINENT_BY_ID[id]
                ?? (p.CONTINENT === 'Seven seas (open ocean)' ? SUBREGION_CONTINENT[p.SUBREGION] : p.CONTINENT);

            if (!group) {
                throw new Error(`No continent for ${name} (${p.SUBREGION})`);
            }

            const sovereign = p.TYPE === 'Sovereign country' || p.TYPE === 'Sovereignty'
                || (p.TYPE === 'Country' && WORLD_SOVEREIGN_COUNTRY_TYPE.has(id)) || id === 'IL';
            const counted = id.length === 2 && !WORLD_NOT_COUNTED.has(id) && (sovereign || WORLD_OBSERVERS.has(id));

            const aliases = [...new Set([p.NAME, p.NAME_LONG, p.NAME_EN, p.ADMIN, p.FORMAL_EN, ...(WORLD_ALIASES[id] ?? [])])]
                .filter((alias) => alias && alias !== name);

            return { id, name, group, aliases, counted };
        },
    },
};
