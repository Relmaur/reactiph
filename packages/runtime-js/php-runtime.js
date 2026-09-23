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
