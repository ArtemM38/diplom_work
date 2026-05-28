/**
 * @param {Record<string, string>|undefined} errors
 * @param {string} field
 */
export function hasError(errors, field) {
    return Boolean(errors?.[field]);
}

/**
 * @param {Record<string, string>|undefined} errors
 * @param {string} field
 * @param {string} [base]
 */
export function fieldClass(errors, field, base = '') {
    const invalid = hasError(errors, field);

    return [
        base,
        invalid ? 'border-red-500 focus:border-red-500 focus:ring-red-500 bg-red-50/80' : 'border-gray-300 focus:border-indigo-500 focus:ring-indigo-500',
    ]
        .filter(Boolean)
        .join(' ');
}
