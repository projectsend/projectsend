import { type ReactNode } from 'react';

export default function Heading({ title, description, badge }: { title: string; description?: string; badge?: ReactNode }) {
    return (
        <>
            <div className="mb-8 space-y-0.5">
                {/* The page title: h1, not h2 — this is the only Heading a page
                    renders, and screen readers anchor "where am I" on level 1.
                    The visual size lives in the classes, so no layout change. */}
                <h1 className="flex items-center gap-2 text-xl font-semibold tracking-tight">
                    {title}
                    {badge}
                </h1>
                {description && <p className="text-muted-foreground text-sm">{description}</p>}
            </div>
        </>
    );
}
