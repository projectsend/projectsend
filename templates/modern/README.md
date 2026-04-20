# Modern Cards Template for ProjectSend

A contemporary, responsive card-based template featuring modern design principles and enhanced user experience.

## Features

### Design
- **Modern Card Layout**: Clean, card-based interface with hover effects
- **Responsive Design**: Optimized for mobile, tablet, and desktop
- **CSS Grid & Flexbox**: Modern layout techniques for responsive grids
- **Contemporary Styling**: Using CSS custom properties and modern design patterns
- **File Type Indicators**: Color-coded badges for different file types

### Functionality
- **Advanced Pagination**: Configurable pagination respecting admin settings
- **Dual View Modes**: Switch between card and list views
- **Enhanced Search**: Live search with debounced input
- **Bulk Actions**: Modern checkbox selection and bulk download
- **Preview Modal**: In-place preview for images and embeddable content
- **Interactive Features**: Hover effects, loading states, and smooth animations

### Technical
- **Modern JavaScript (ES6+)**: Modular, maintainable code
- **CSS Variables**: Easy theming and customization
- **Accessibility**: ARIA labels and keyboard navigation
- **Performance**: Lazy loading and optimized animations
- **Progressive Enhancement**: Graceful fallbacks for older browsers

## File Structure

```
templates/modern/
├── template.php           # Main template file
├── main.css              # Compiled CSS stylesheet
├── main.scss             # Source SCSS file
├── js/
│   └── template.js       # JavaScript functionality
├── lang/
│   ├── modern.pot        # Translation template
│   ├── en.po            # English translations
│   └── en.mo            # Compiled translations
├── cover.png            # Admin preview thumbnail
├── screenshot.png       # Admin preview image
└── README.md           # This documentation
```

## Installation

1. The template is already installed in `/templates/modern/`
2. In ProjectSend admin panel, go to Options > Clients
3. Select "Modern Cards" from the template dropdown
4. Save changes

## Customization

### Colors
The template uses CSS custom properties for easy theming. Edit the `:root` section in `main.css` or `main.scss`:

```css
:root {
  --primary-color: #2563eb;     /* Main brand color */
  --success-color: #059669;     /* Success/upload color */
  --warning-color: #d97706;     /* Warning color */
  --danger-color: #dc2626;      /* Error/danger color */
  /* ... more variables ... */
}
```

### Layout
- **Grid columns**: Automatically adjusts based on screen size
- **Card sizing**: Minimum 320px width, responsive height
- **Spacing**: Consistent spacing using CSS variables

### JavaScript Features
The template includes several interactive features:
- View toggling (cards/list)
- Preview modals
- Bulk selection
- Download tracking
- Keyboard shortcuts
- Search functionality

## Browser Support

- **Modern browsers**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
- **Fallbacks**: Graceful degradation for older browsers
- **Mobile**: Optimized for iOS Safari and Chrome Mobile

## Performance

- **Lazy loading**: Images load as they enter viewport
- **Debounced search**: Reduces server requests
- **CSS animations**: Hardware-accelerated transforms
- **Minimal dependencies**: Uses existing ProjectSend libraries

## Dependencies

The template leverages existing ProjectSend dependencies:
- jQuery 3.5+
- Bootstrap 5.2.1 (for base styles and utilities)
- Font Awesome 4.7.0 (for icons)
- js-cookie (for preferences storage)

## Development

### Building CSS from SCSS
If you have Sass installed:
```bash
sass main.scss main.css
```

Or use the project's build system:
```bash
npm run prod
```

### Translation
To add new languages:
1. Copy `lang/en.po` to `lang/[language].po`
2. Translate the strings
3. Compile with `msgfmt [language].po -o [language].mo`

## Accessibility

- **Keyboard navigation**: Full keyboard support
- **ARIA labels**: Screen reader compatible
- **Color contrast**: WCAG 2.1 AA compliant
- **Focus indicators**: Clear focus states
- **Semantic HTML**: Proper heading hierarchy and landmarks

## Notes

- **Image previews**: Placeholder image files need to be replaced with actual PNG images
- **Pagination**: Respects the global pagination setting from ProjectSend admin
- **File types**: Supports all ProjectSend file types with appropriate styling
- **Mobile**: Touch-optimized with proper tap targets

## Contributing

To contribute improvements:
1. Edit the source files (SCSS, JS)
2. Test across different browsers and devices
3. Ensure accessibility compliance
4. Update translations if adding new strings

## License

This template follows the same license as ProjectSend (GPL-3.0-or-later).