/**
 * The few sentences the browser writes itself (toasts, the offline queue,
 * live status), in the page's language. The server puts the translations
 * on the page as window.SoforI18n (see components/head), so there is no
 * extra request; English needs none.
 */
const dictionary = window.SoforI18n || {};

/** The locale for numbers: Bangla digits and lakh grouping in Bangla. */
export const locale = document.documentElement.lang === 'bn' ? 'bn-BD' : 'en-US';

/**
 * How a Bangla case ending joins a Bangla word, as on the server
 * (App\Support\Bangla::ending): "জেলা" + "এর" is "জেলার", "বাংলাদেশ" + "এর"
 * is "বাংলাদেশের". Null keeps the hyphen (a Latin name, a figure).
 */
function ending(word, suffix) {
    const last = String(word).trim().slice(-1);

    if (last === '' || last === 'ৎ' || !/[ঁ-ঃঅ-হ়-ৗড়-য়]/.test(last)) {
        return null;
    }

    const vowel = /[অ-ঔা-ৌৗ]/.test(last);
    const nasal = /[ঁ-ঃ]/.test(last);

    if (suffix === 'এর') {
        return vowel ? 'র' : (nasal ? 'য়ের' : 'ের');
    }

    if (suffix === 'এ') {
        return last === 'া' ? 'য়' : (vowel ? 'তে' : (nasal ? 'য়ে' : 'ে'));
    }

    return suffix;
}

export function t(text, replace = {}) {
    let out = dictionary[text] ?? text;

    for (const [key, value] of Object.entries(replace)) {
        out = out.replace(new RegExp(`:${key}-(এর|এ|কে|তে)(?![\\u0980-\\u09FF])`, 'g'), (match, suffix) => {
            const joined = ending(value, suffix);

            return joined === null ? match : `:${key}${joined}`;
        });
        out = out.split(`:${key}`).join(String(value));
    }

    return out;
}

export function num(value, options = {}) {
    return Number(value || 0).toLocaleString(locale, options);
}

// Inline Alpine expressions in the views can use them too.
window.SoforT = t;
window.SoforNum = num;
window.SoforLocale = locale;
