export function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    const units = ['KB', 'MB', 'GB', 'TB'];
    let value = bytes;
    let unit = '';
    for (const next of units) {
        value /= 1024;
        unit = next;
        if (value < 1024) break;
    }

    return `${value.toFixed(value >= 100 ? 0 : 1)} ${unit}`;
}
