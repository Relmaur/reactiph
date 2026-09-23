/**
 * Runtime support for Reactiph's transpiled PHP->JS output
 * (Transpiler\PhpToJs). Transpiled component method bodies call these
 * helpers rather than relying on JS's native semantics directly, because
 * PHP and JS diverge on some of them in ways that would otherwise
 * silently produce wrong behavior -- see
 * docs/adr/0011-transpiler-slice-1-scope-and-semantics.md.
 */

/**
 * PHP's truthiness rules, applied wherever transpiled code evaluates a
 * value as a boolean (if conditions, &&, ||, !). PHP and JS diverge here:
 * notably, the string "0" is falsy in PHP but truthy in JS (only "" is
 * falsy for strings in JS) -- covered by ParityTest's truthiness cases.
 */
function __phpBool(value) {
    if (value === false || value === null || value === undefined) {
        return false;
    }
    if (value === 0 || value === 0.0) {
        return false;
    }
    if (value === '' || value === '0') {
        return false;
    }
    if (Array.isArray(value) && value.length === 0) {
        return false;
    }
    return true;
}

/**
 * PHP's string-cast rules, applied wherever transpiled code stringifies a
 * value (string concatenation, and any future builtin like implode()
 * that does the same). PHP and JS diverge here too: PHP casts `true` to
 * `"1"` and `false` to `""`, not `"true"`/`"false"` — covered by
 * ParityTest's boolean-concatenation cases.
 */
function __phpString(value) {
    if (value === true) {
        return '1';
    }
    if (value === false || value === null || value === undefined) {
        return '';
    }
    return String(value);
}

/**
 * `foreach`'s [key, value] pairs, for either JS representation a
 * transpiled PHP array can have (see docs/adr/0013-*.md): a list-style
 * PHP array transpiles to a JS Array (keys are numeric indices), an
 * associative one to a plain Object (keys are its string keys). A
 * transpiled `foreach` loop is generated as a plain inline
 * `for (const [k, v] of __phpEntries(arr))`, not a callback, so that any
 * PHP variable assigned inside the loop body stays function-scoped
 * (readable after the loop) exactly like it does for `if`/`while`/`for`.
 */
function __phpEntries(value) {
    if (Array.isArray(value)) {
        return value.map(function (v, i) {
            return [i, v];
        });
    }
    return Object.keys(value).map(function (k) {
        return [k, value[k]];
    });
}

/**
 * Stdlib builtins (Transpiler\PhpToJs's `compileFuncCall()`), each
 * checked against a real PHP/JS behavioral gap before being implemented
 * as a naive 1:1 call — see docs/adr/0015-*.md for the full reasoning
 * behind each one and why several common builtins (array_map,
 * array_filter, sprintf) are deliberately NOT here yet.
 */

/** count() — dispatches on the same Array/Object duality as __phpEntries(). */
function __phpCount(value) {
    return Array.isArray(value) ? value.length : Object.keys(value).length;
}

/**
 * strlen() — PHP counts *bytes*, not characters; JS's .length counts
 * UTF-16 code units. These only agree for pure-ASCII strings. Using
 * TextEncoder to count actual UTF-8 bytes matches PHP's behavior exactly
 * for multi-byte input too (e.g. emoji, non-Latin scripts).
 */
function __phpStrlen(value) {
    return new TextEncoder().encode(value).length;
}

/**
 * in_array($needle, $haystack, true) — PHP's default (`$strict = false`)
 * uses loose comparison, which PhpToJs never transpiles (see
 * looseComparisonNotSupported() and ADR 0011) — only the strict form
 * reaches this helper; the compiler rejects the rest at compile time.
 * Dispatches Array/Object the same way __phpCount() does.
 */
function __phpInArray(needle, haystack) {
    var values = Array.isArray(haystack) ? haystack : Object.values(haystack);
    return values.indexOf(needle) !== -1;
}

/** implode() — stringifies each value with __phpString(), not native String(). */
function __phpImplode(glue, value) {
    var values = Array.isArray(value) ? value : Object.values(value);
    return values.map(__phpString).join(glue);
}

/** explode() — PHP always returns a plain list array, matching .split()'s own Array result. */
function __phpExplode(delimiter, value) {
    return value.split(delimiter);
}

/**
 * trim() — PHP's default character set is a fixed ASCII list (space, tab,
 * newline, CR, null byte, vertical tab), not JS's broader Unicode
 * whitespace set that .trim() strips. Matches PHP's default exactly (the
 * optional second $characters argument isn't supported).
 */
function __phpTrim(value) {
    return value.replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '');
}

/**
 * strtolower()/strtoupper() — PHP's default (no locale set) only touches
 * ASCII A-Z/a-z; JS's .toLowerCase()/.toUpperCase() are full
 * Unicode-aware and would also transform accented/non-Latin characters
 * PHP leaves untouched (e.g. German ß uppercasing to "SS").
 */
function __phpStrtolower(value) {
    return value.replace(/[A-Z]/g, function (c) {
        return c.toLowerCase();
    });
}

function __phpStrtoupper(value) {
    return value.replace(/[a-z]/g, function (c) {
        return c.toUpperCase();
    });
}

/** str_replace() — literal (non-regex) global replacement, scalar args only. */
function __phpStrReplace(search, replace, subject) {
    return subject.split(search).join(replace);
}
