import { Transition } from '@headlessui/react';
import { type ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';

interface SaveButtonProps {
    processing: boolean;
    recentlySuccessful: boolean;
    /** Button label. Defaults to "Save"; pass a specific verb where the page has one. */
    children?: ReactNode;
    /** Submits a form rendered elsewhere in the tree, by id. */
    form?: string;
}

/**
 * The "Saved" acknowledgement that fades in once Inertia reports the request
 * succeeded. Use it directly when the button row holds more than the submit
 * button -- a delete dialog, a secondary action -- and SaveButton below when
 * it does not.
 */
export function SavedIndicator({ recentlySuccessful }: { recentlySuccessful: boolean }) {
    const { t } = useTranslation();

    return (
        <Transition show={recentlySuccessful} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
            <p className="text-sm text-neutral-600">{t('Saved')}</p>
        </Transition>
    );
}

/**
 * A form's submit button and its "Saved" acknowledgement -- the pair most
 * editable pages in the app end with.
 */
export function SaveButton({ processing, recentlySuccessful, children, form }: SaveButtonProps) {
    const { t } = useTranslation();

    return (
        <div className="flex items-center gap-4">
            <Button type="submit" form={form} disabled={processing}>
                {children ?? t('Save')}
            </Button>

            <SavedIndicator recentlySuccessful={recentlySuccessful} />
        </div>
    );
}
