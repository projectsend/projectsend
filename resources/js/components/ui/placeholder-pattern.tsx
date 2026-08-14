import { useId } from 'react';

interface PlaceholderPatternProps {
    className?: string;
}

export function PlaceholderPattern({ className }: PlaceholderPatternProps) {
    const patternId = useId();

    return (
        <svg className={className} fill="none">
            <defs>
                <pattern id={patternId} x="0" y="0" width="8" height="8" patternUnits="userSpaceOnUse">
                    <path d="M-1 5L5 -1M3 9L8.5 3.5" stroke-width="0.5"></path>
                </pattern>
            </defs>
            <rect stroke="none" fill={`url(#${patternId})`} width="100%" height="100%"></rect>
        </svg>
    );
}
