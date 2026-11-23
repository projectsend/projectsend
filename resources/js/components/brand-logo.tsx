import { cn } from '@/lib/utils';

interface BrandLogoProps {
    /** The main logo. */
    src: string;
    /** The logo for the dark theme, or null when the main one serves both. */
    darkSrc: string | null;
    alt: string;
    className?: string;
    /**
     * The surface is always dark, whatever the theme: the Gallery layouts.
     * The dark-mode logo is made for that, so it is used as it is; without
     * one, the main logo is turned white so it still shows (#1845).
     */
    darkSurface?: boolean;
}

/**
 * The installation's uploaded logo, the right one for the theme.
 *
 * Both images are rendered and CSS picks one, rather than JavaScript
 * reading the theme: the `.dark` class is set before React mounts, so the
 * right logo is there on the first paint and follows a theme switch with
 * no flash.
 */
export default function BrandLogo({ src, darkSrc, alt, className, darkSurface = false }: BrandLogoProps) {
    if (darkSurface) {
        return darkSrc ? (
            <img src={darkSrc} alt={alt} className={className} />
        ) : (
            <img src={src} alt={alt} className={cn(className, 'brightness-0 invert')} />
        );
    }

    if (!darkSrc) {
        return <img src={src} alt={alt} className={className} />;
    }

    return (
        <>
            <img src={src} alt={alt} className={cn(className, 'dark:hidden')} />
            <img src={darkSrc} alt={alt} className={cn(className, 'hidden dark:block')} />
        </>
    );
}
