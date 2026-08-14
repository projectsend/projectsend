export interface CategoryColorDef {
    value: string;
    label: string;
    /** Full Badge className override (background + text, light and dark). */
    badge: string;
    /** Solid dot, used by the color picker and the categories list. */
    swatch: string;
}

// A fixed palette, not free-form color — keeps contrast/dark-mode
// legibility guaranteed for every category. Values must match the
// backend's App\Modules\Files\CategoryColor enum exactly.
export const CATEGORY_COLORS: CategoryColorDef[] = [
    {
        value: 'gray',
        label: 'Gray',
        badge: 'border-transparent bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
        swatch: 'bg-gray-400',
    },
    { value: 'red', label: 'Red', badge: 'border-transparent bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300', swatch: 'bg-red-500' },
    {
        value: 'orange',
        label: 'Orange',
        badge: 'border-transparent bg-orange-100 text-orange-700 dark:bg-orange-950 dark:text-orange-300',
        swatch: 'bg-orange-500',
    },
    {
        value: 'yellow',
        label: 'Yellow',
        badge: 'border-transparent bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300',
        swatch: 'bg-yellow-400',
    },
    {
        value: 'green',
        label: 'Green',
        badge: 'border-transparent bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300',
        swatch: 'bg-green-500',
    },
    {
        value: 'blue',
        label: 'Blue',
        badge: 'border-transparent bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-300',
        swatch: 'bg-blue-500',
    },
    {
        value: 'purple',
        label: 'Purple',
        badge: 'border-transparent bg-purple-100 text-purple-700 dark:bg-purple-950 dark:text-purple-300',
        swatch: 'bg-purple-500',
    },
    {
        value: 'pink',
        label: 'Pink',
        badge: 'border-transparent bg-pink-100 text-pink-700 dark:bg-pink-950 dark:text-pink-300',
        swatch: 'bg-pink-500',
    },
];

const BY_VALUE = new Map(CATEGORY_COLORS.map((color) => [color.value, color]));

export function categoryColor(value: string): CategoryColorDef {
    return BY_VALUE.get(value) ?? CATEGORY_COLORS[0];
}
