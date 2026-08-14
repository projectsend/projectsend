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
    height?: number;
    onComplete: (fileIds: number[]) => void;
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
    height = 440,
    onComplete,
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
        onFileUploaded: (fileId) => uploadedFileIds.push(fileId),
        onVersionError,
    });

    useEffect(() => {
        const handleComplete = () => {
            onComplete([...uploadedFileIds]);
            uploadedFileIds.length = 0;
        };

        uppy.on('complete', handleComplete);

        return () => {
            uppy.off('complete', handleComplete);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [uppy]);

    return <Dashboard uppy={uppy} theme={theme} width="100%" height={height} proudlyDisplayPoweredByUppy={false} />;
}
