# UI Styling System

A comprehensive, organized SCSS styling system for the UI components that provides beautiful, high-contrast styling for dialogs and all imported components.

## Features

✨ **Beautiful Design System**
- High-contrast colors for excellent readability
- Insurance blue theme with emerald accents
- Consistent gradients and shadows
- Smooth animations and transitions

🎨 **Comprehensive Component Coverage**
- Dialog components (ConfirmDialog, InfoDialog)
- Form components (inputs, textareas, file uploads)
- Button components (ActionButton overrides)
- Card and badge components
- Progress indicators

📱 **Responsive Design**
- Mobile-friendly dialog layouts
- Responsive typography and spacing
- Touch-friendly button sizes

🚀 **Performance Optimized**
- CSS custom properties for theming
- Efficient selectors with .ui-app scope
- Smooth hardware-accelerated animations

## File Structure

```
src/ui/shared/styles/
├── index.scss              # Main entry point
├── _variables.scss          # Design tokens & CSS variables
├── _base.scss              # Base styles & utilities
├── components/
│   ├── index.scss          # Component imports
│   ├── _cards.scss         # Card component styles
│   ├── _buttons.scss       # Button component styles
│   ├── _forms.scss         # Form component styles
│   ├── _badges.scss        # Badge component styles
│   └── _progress.scss      # Progress component styles
└── overrides/
    ├── index.scss          # Override imports
    ├── _dialogs.scss       # Dialog component overrides
    ├── _quasar.scss        # Quasar component overrides
    └── _danx.scss          # Danx component overrides
```

## Usage

The styles are automatically applied to all UI components by importing them in `UiAppLayout.vue`:

```scss
@import '../styles/index.scss';
```

All UI components must be wrapped with the `.ui-app` class to receive the styling.

## Design Tokens

### Colors

- **Primary**: Insurance blue (`--ui-primary-*`)
- **Secondary**: Emerald accent (`--ui-secondary-*`)
- **Neutral**: Warm grays (`--ui-neutral-*`)
- **Status**: Success, warning, error, info variants

### Typography

- **Font Family**: Inter, system fonts fallback
- **Weights**: 400 (normal), 500 (medium), 600 (semibold), 700 (bold)
- **Sizes**: xs (12px) to 4xl (36px)
- **Line Heights**: tight (1.25), normal (1.5), relaxed (1.75)

### Spacing

- **Scale**: xs (4px) to 3xl (64px)
- **Consistent rhythm**: All components use the same spacing scale

### Shadows & Effects

- **4 shadow levels**: sm, md, lg, xl, 2xl
- **Gradients**: Primary, secondary, surface variants
- **Animations**: Fade in, slide up, shimmer effects

## Component Highlights

### Dialogs (ConfirmDialog, InfoDialog)

- **Beautiful headers** with gradient backgrounds
- **High-contrast content** areas for readability
- **Prominent action buttons** with hover effects
- **Mobile-responsive** layouts
- **Smooth animations** (fade in backdrop, slide up dialog)
- **Status variants** (success, warning, error)

### Buttons (ActionButton)

- **Gradient backgrounds** for primary actions
- **Smooth hover effects** with transform and shadow
- **Focus indicators** for accessibility
- **Loading states** with disabled styling
- **Size variants** (small, medium, large)

### Forms

- **Consistent field styling** across all input types
- **MultiFileField** with drag-and-drop styling
- **Error and success states** with color indicators
- **Smooth focus transitions**

### Cards

- **Subtle shadows** and borders
- **Interactive hover effects** for clickable cards
- **Status variants** with colored headers
- **Consistent padding** and spacing

## Accessibility

- **High contrast ratios** for text readability
- **Focus indicators** on all interactive elements
- **Keyboard navigation** support
- **Screen reader friendly** markup

## Browser Support

- **Modern browsers** (Chrome, Firefox, Safari, Edge)
- **CSS Grid and Flexbox** for layouts
- **CSS Custom Properties** for theming
- **CSS Animations** for smooth interactions

## Customization

To customize the design system, modify the CSS custom properties in `_variables.scss`:

```scss
:root {
  --ui-primary-500: #your-brand-color;
  --ui-radius-lg: 1rem; // Adjust border radius
  --ui-shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1); // Adjust shadows
}
```

The styling system is designed to be easily customizable while maintaining consistency across all components.