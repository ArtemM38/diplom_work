export function storageUrl(path) {
    if (!path) {
        return null;
    }

    return route('files.show', { path: String(path).replace(/^\//, '') });
}
