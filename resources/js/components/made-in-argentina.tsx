import { useTranslation } from '@/hooks/use-translation';

/**
 * The line from projectsend.org, on the two pages that greet somebody —
 * the only two screens in the application where it is worth saying who
 * made this rather than getting on with the work.
 *
 * Drawn rather than set as the 🇦🇷 emoji the website uses. Flag emoji are
 * regional-indicator pairs, and neither Windows nor most Linux desktops
 * ship glyphs for them: both fall back to a pair of small letters, so a
 * good half of the people self-hosting this would read "AR Made with care
 * in Argentina" (which is exactly what the first render of this component
 * did). Eleven lines of SVG look the same on every machine.
 *
 * The Sun of May is a filled disc here on purpose — at sixteen pixels its
 * thirty-two rays are a smudge, and a clean circle reads as the flag
 * while a smudge reads as a rendering fault.
 */
function ArgentineFlag() {
    return (
        <svg viewBox="0 0 24 16" className="inline-block h-3 w-[1.125rem] rounded-[2px] align-[-1px] ring-1 ring-black/10" aria-hidden="true">
            <rect width="24" height="16" fill="#74ACDF" />
            <rect y="5.33" width="24" height="5.33" fill="#FFFFFF" />
            <circle cx="12" cy="8" r="1.6" fill="#F6B40E" />
        </svg>
    );
}

export default function MadeInArgentina() {
    const { t } = useTranslation();

    return (
        <p className="text-muted-foreground/80 flex items-center justify-center gap-1.5 text-center text-xs">
            <ArgentineFlag />
            {/* The flag is not language, so it stays out of the translated
                string — a translator carrying an image through sixteen
                catalogues will eventually drop one. */}
            {t('Made with care in Argentina. Shared with the world.')}
        </p>
    );
}
