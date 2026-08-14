import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import AwsS3 from '@uppy/aws-s3';
import Uppy from '@uppy/core';
import Spanish from '@uppy/locales/lib/es_ES';
import { useEffect, useRef, useState } from 'react';

import { useAppliedTheme } from '@/hooks/use-applied-theme';
import { apiFetch } from '@/lib/uploads-api';

interface UseChunkedUploadOptions {
    maxFileSizeMb: number;
    partSizeMb: number;
    allowedExtensions: string[] | null;
    /** undefined/null = unlimited (staff); 1 keeps a single description field 1:1 with the file (client). */
    maxNumberOfFiles?: number | null;
    /** UX cap only, combined with maxFileSizeMb via min() — the real boundary is always server-side. */
    remainingBytes?: number | null;
    /** Threaded into the uploads.store POST body when present. */
    description?: string;
    /** The folder to upload into — undefined/null = loose at the root. */
    folderId?: number | null;
    /**
     * The file this upload is a new version of. Held in a ref for the same
     * reason description is: the Uppy instance is built once, so a plain
     * prop would be captured stale when the picker is used after mount.
     */
    previousFileId?: number | null;
    onFileUploaded?: (fileId: number) => void;
    /** The server refused the version link, but the upload itself succeeded. */
    onVersionError?: (message: string) => void;
}

/**
 * Owns the Uppy instance + its aws-s3 multipart wiring (the resumable
 * chunked-upload contract shared by the staff and client upload pages).
 * On installs without object storage the part URLs point back at this
 * app (LocalPartStore); the cloud edition hands out real presigned S3
 * URLs behind the same contract — nothing here needs to know which.
 */
export function useChunkedUploadUppy(options: UseChunkedUploadOptions): { uppy: Uppy; theme: 'light' | 'dark' } {
    const { locale } = usePage<SharedData>().props;
    const theme = useAppliedTheme();

    // The Uppy instance is built once (below) and its callbacks close
    // over this ref rather than `options.description` directly — a plain
    // string prop would be captured stale at construction time (e.g. the
    // client types a description into a still-empty text field, then
    // drops a file), since nothing here ever rebuilds the Uppy instance.
    const descriptionRef = useRef(options.description);
    useEffect(() => {
        descriptionRef.current = options.description;
    }, [options.description]);

    const previousFileIdRef = useRef(options.previousFileId);
    useEffect(() => {
        previousFileIdRef.current = options.previousFileId;
    }, [options.previousFileId]);

    const maxFileSizeBytes =
        options.maxFileSizeMb > 0
            ? options.remainingBytes != null
                ? Math.min(options.maxFileSizeMb * 1024 * 1024, options.remainingBytes)
                : options.maxFileSizeMb * 1024 * 1024
            : (options.remainingBytes ?? null);

    const [uppy] = useState(() =>
        new Uppy({
            autoProceed: false,
            restrictions: {
                maxFileSize: maxFileSizeBytes,
                maxNumberOfFiles: options.maxNumberOfFiles ?? null,
                // UX hint only — the server re-checks on upload regardless.
                allowedFileTypes: options.allowedExtensions ? options.allowedExtensions.map((extension) => `.${extension}`) : null,
            },
            locale: locale === 'es' ? Spanish : undefined,
        }).use(AwsS3, {
            shouldUseMultipart: true,
            getChunkSize: () => options.partSizeMb * 1024 * 1024,
            async createMultipartUpload(file) {
                return apiFetch<{ uploadId: string; key: string }>(route('uploads.store'), 'POST', {
                    filename: file.name,
                    size: file.size,
                    type: file.type,
                    description: descriptionRef.current || undefined,
                    folder_id: options.folderId ?? undefined,
                    previous_file_id: previousFileIdRef.current ?? undefined,
                });
            },
            async signPart(_file, { uploadId, partNumber }) {
                return apiFetch<{ url: string; method: 'PUT' }>(route('uploads.parts.sign', { session: uploadId, part: partNumber }), 'GET');
            },
            async listParts(_file, { uploadId }) {
                return apiFetch<{ PartNumber: number; Size: number; ETag: string }[]>(route('uploads.parts.index', { session: uploadId }), 'GET');
            },
            async completeMultipartUpload(_file, { uploadId }) {
                const result = await apiFetch<{ location: string; file_id: number; version_error: string | null }>(
                    route('uploads.complete', { session: uploadId }),
                    'POST',
                );
                options.onFileUploaded?.(result.file_id);

                // The bytes are stored and the row is real; only the version
                // link failed. Surfaced rather than swallowed, but never
                // failing the upload over it.
                if (result.version_error) options.onVersionError?.(result.version_error);

                return { location: result.location };
            },
            async abortMultipartUpload(_file, { uploadId }) {
                await apiFetch(route('uploads.destroy', { session: uploadId }), 'DELETE');
            },
        }),
    );

    return { uppy, theme };
}
