// Mirrors ThumbnailGenerator::SUPPORTED_MIME_TYPES on the backend — SVGs are
// already vector/small, so they're excluded and rendered directly instead.
const THUMBNAILABLE_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

export function isThumbnailable(mimeType: string): boolean {
    return THUMBNAILABLE_MIME_TYPES.includes(mimeType);
}
