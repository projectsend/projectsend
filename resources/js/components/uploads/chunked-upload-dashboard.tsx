import Dashboard from '@uppy/react/dashboard';
import { useEffect, useState } from 'react';

import { useChunkedUploadUppy } from '@/hooks/use-chunked-upload';

import '@uppy/core/css/style.min.css';
import '@uppy/dashboard/css/style.min.css';

interface ChunkedUploadDashboardProps {
    maxFileSizeMb: number;
    partSizeMb: number;
    allowedExtensions: string[] | null;
    maxNumberOfFiles?: number | null;
    remainingBytes?: number | null;
    description?: string;
    folderId?: number | null;
    previousFileId?: number | null;
    storageLocationId?: number | null;
    height?: number;
    onComplete: (fileIds: number[]) => void;
    /** True from the first upload of a batch until the batch is over. */
    onBusyChange?: (busy: boolean) => void;
    onVersionError?: (message: string) => void;
}

export default function ChunkedUploadDashboard({
    maxFileSizeMb,
    partSizeMb,
    allowedExtensions,
    maxNumberOfFiles,
    remainingBytes,
    description,
    folderId,
    previousFileId,
    storageLocationId,
    height = 440,
    onComplete,
    onBusyChange,
    onVersionError,
}: ChunkedUploadDashboardProps) {
    const [uploadedFileIds] = useState<number[]>([]);

    const { uppy, theme } = useChunkedUploadUppy({
        maxFileSizeMb,
        partSizeMb,
        allowedExtensions,
        maxNumberOfFiles,
        remainingBytes,
        description,
        folderId,
        previousFileId,
        storageLocationId,
        onFileUploaded: (fileId) => uploadedFileIds.push(fileId),
        onVersionError,
    });

    useEffect(() => {
        const handleComplete = () => {
            onBusyChange?.(false);
            onComplete([...uploadedFileIds]);
            uploadedFileIds.length = 0;
        };
        const handleStart = () => onBusyChange?.(true);
        const handleCancel = () => onBusyChange?.(false);

        uppy.on('complete', handleComplete);
        uppy.on('upload', handleStart);
        uppy.on('cancel-all', handleCancel);

        return () => {
            uppy.off('complete', handleComplete);
            uppy.off('upload', handleStart);
            uppy.off('cancel-all', handleCancel);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [uppy]);

    return <Dashboard uppy={uppy} theme={theme} width="100%" height={height} proudlyDisplayPoweredByUppy={false} />;
}
