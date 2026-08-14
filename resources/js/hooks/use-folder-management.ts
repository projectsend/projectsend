import { router, useForm } from '@inertiajs/react';
import { type FormEventHandler, useState } from 'react';

import { type Crumb, type FolderRow } from '@/types/portal';

/**
 * Creating, renaming and deleting the client's own folders.
 *
 * Separate from usePortalFiles because a theme can offer file browsing
 * without offering folder management, and the permissions that gate it
 * (can_create_folders, plus upload permission) are answered per request.
 */
export function useFolderManagement(folder: Crumb | null) {
    const [newFolderOpen, setNewFolderOpen] = useState(false);
    const createForm = useForm({ name: '', parent_id: folder?.id ?? null });
    const createFolder: FormEventHandler = (e) => {
        e.preventDefault();
        createForm.post(route('my-folders.store'), {
            preserveScroll: true,
            onSuccess: () => {
                createForm.reset('name');
                setNewFolderOpen(false);
            },
        });
    };

    const [renamingFolder, setRenamingFolder] = useState<FolderRow | null>(null);
    const renameForm = useForm({ name: '' });
    const startRename = (row: FolderRow) => {
        renameForm.setData('name', row.name);
        setRenamingFolder(row);
    };
    const renameFolder: FormEventHandler = (e) => {
        e.preventDefault();
        if (renamingFolder === null) return;
        renameForm.patch(route('my-folders.update', renamingFolder.id), {
            preserveScroll: true,
            onSuccess: () => setRenamingFolder(null),
        });
    };

    const deleteFolder = (id: number) => router.delete(route('my-folders.destroy', id), { preserveScroll: true });

    return {
        newFolderOpen,
        setNewFolderOpen,
        createForm,
        createFolder,
        renamingFolder,
        setRenamingFolder,
        startRename,
        renameForm,
        renameFolder,
        deleteFolder,
    };
}
