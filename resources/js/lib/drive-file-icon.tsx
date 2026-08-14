import { File, FileArchive, FileAudio, FileCode, FileImage, FileSpreadsheet, FileText, FileVideo, LucideIcon, Presentation } from 'lucide-react';

interface DriveFileIconDef {
    icon: LucideIcon;
    /** Text color class only — callers decide size/background. */
    color: string;
}

const SPREADSHEET_MIME_TYPES = [
    'text/csv',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.oasis.opendocument.spreadsheet',
];

const PRESENTATION_MIME_TYPES = [
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.oasis.opendocument.presentation',
];

const ARCHIVE_MIME_TYPES = [
    'application/zip',
    'application/x-rar-compressed',
    'application/x-7z-compressed',
    'application/gzip',
    'application/x-tar',
];

const CODE_MIME_TYPES = ['application/json', 'application/xml', 'text/html', 'text/css', 'application/javascript', 'text/x-php'];

const DOCUMENT_MIME_TYPES = [
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.oasis.opendocument.text',
    'text/plain',
];

/**
 * The Drive theme's signature: a colored file-type icon per row/card,
 * echoing how Google Drive tints Docs blue, Sheets green, PDFs red, etc.
 * A visual cue only — never the sole way to tell file types apart.
 */
export function driveFileIcon(mimeType: string): DriveFileIconDef {
    if (mimeType.startsWith('image/')) return { icon: FileImage, color: 'text-blue-600' };
    if (mimeType.startsWith('video/')) return { icon: FileVideo, color: 'text-purple-600' };
    if (mimeType.startsWith('audio/')) return { icon: FileAudio, color: 'text-pink-600' };
    if (mimeType === 'application/pdf') return { icon: FileText, color: 'text-red-600' };
    if (SPREADSHEET_MIME_TYPES.includes(mimeType)) return { icon: FileSpreadsheet, color: 'text-green-600' };
    if (PRESENTATION_MIME_TYPES.includes(mimeType)) return { icon: Presentation, color: 'text-yellow-600' };
    if (ARCHIVE_MIME_TYPES.includes(mimeType)) return { icon: FileArchive, color: 'text-orange-600' };
    if (CODE_MIME_TYPES.includes(mimeType)) return { icon: FileCode, color: 'text-teal-600' };
    if (DOCUMENT_MIME_TYPES.includes(mimeType)) return { icon: FileText, color: 'text-blue-600' };

    return { icon: File, color: 'text-muted-foreground' };
}
