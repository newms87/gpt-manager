/**
 * Workflow Color Palette System
 *
 * Provides a consistent color palette for workflow-related UI components.
 * Each color from the YAML config maps to a complete palette with:
 * - text: primary, secondary, muted
 * - bg: primary, secondary, light, dark
 * - border: primary
 * - progress: bar color
 * - button: ActionButton color name
 */

export interface WorkflowColorPalette {
  // Text colors
  textPrimary: string;      // Main text on light backgrounds
  textSecondary: string;    // Secondary/muted text on light backgrounds
  textOnDark: string;       // Text on dark backgrounds
  textOnLight: string;      // Text on light backgrounds (darker shade)

  // Background colors
  bgLight: string;          // Light background (for sections)
  bgMedium: string;         // Medium background
  bgDark: string;           // Dark background (for buttons, badges)
  bgProgress: string;       // Progress bar fill

  // Border colors
  borderPrimary: string;    // Primary border/accent
  borderLight: string;      // Light border

  // ActionButton color name
  buttonColor: string;      // For quasar-ui-danx ActionButton
}

/**
 * Complete color palettes for each workflow color
 * These map the simple color names from YAML to full Tailwind class sets
 */
export const WORKFLOW_COLOR_PALETTES: Record<string, WorkflowColorPalette> = {
  blue: {
    textPrimary: 'text-blue-700',
    textSecondary: 'text-blue-600',
    textOnDark: 'text-blue-100',
    textOnLight: 'text-blue-800',
    bgLight: 'bg-blue-50',
    bgMedium: 'bg-blue-100',
    bgDark: 'bg-blue-600',
    bgProgress: 'bg-blue-500',
    borderPrimary: 'border-blue-500',
    borderLight: 'border-blue-200',
    buttonColor: 'sky',
  },
  teal: {
    textPrimary: 'text-teal-700',
    textSecondary: 'text-teal-600',
    textOnDark: 'text-teal-100',
    textOnLight: 'text-teal-800',
    bgLight: 'bg-teal-50',
    bgMedium: 'bg-teal-100',
    bgDark: 'bg-teal-600',
    bgProgress: 'bg-teal-500',
    borderPrimary: 'border-teal-500',
    borderLight: 'border-teal-200',
    buttonColor: 'teal',
  },
  green: {
    textPrimary: 'text-green-700',
    textSecondary: 'text-green-600',
    textOnDark: 'text-green-100',
    textOnLight: 'text-green-800',
    bgLight: 'bg-green-50',
    bgMedium: 'bg-green-100',
    bgDark: 'bg-green-600',
    bgProgress: 'bg-green-500',
    borderPrimary: 'border-green-500',
    borderLight: 'border-green-200',
    buttonColor: 'green',
  },
  purple: {
    textPrimary: 'text-purple-700',
    textSecondary: 'text-purple-600',
    textOnDark: 'text-purple-100',
    textOnLight: 'text-purple-800',
    bgLight: 'bg-purple-50',
    bgMedium: 'bg-purple-100',
    bgDark: 'bg-purple-600',
    bgProgress: 'bg-purple-500',
    borderPrimary: 'border-purple-500',
    borderLight: 'border-purple-200',
    buttonColor: 'purple',
  },
  orange: {
    textPrimary: 'text-orange-700',
    textSecondary: 'text-orange-600',
    textOnDark: 'text-orange-100',
    textOnLight: 'text-orange-800',
    bgLight: 'bg-orange-50',
    bgMedium: 'bg-orange-100',
    bgDark: 'bg-orange-600',
    bgProgress: 'bg-orange-500',
    borderPrimary: 'border-orange-500',
    borderLight: 'border-orange-200',
    buttonColor: 'orange',
  },
  red: {
    textPrimary: 'text-red-700',
    textSecondary: 'text-red-600',
    textOnDark: 'text-red-100',
    textOnLight: 'text-red-800',
    bgLight: 'bg-red-50',
    bgMedium: 'bg-red-100',
    bgDark: 'bg-red-600',
    bgProgress: 'bg-red-500',
    borderPrimary: 'border-red-500',
    borderLight: 'border-red-200',
    buttonColor: 'red',
  },
  sky: {
    textPrimary: 'text-sky-700',
    textSecondary: 'text-sky-600',
    textOnDark: 'text-sky-100',
    textOnLight: 'text-sky-800',
    bgLight: 'bg-sky-50',
    bgMedium: 'bg-sky-100',
    bgDark: 'bg-sky-600',
    bgProgress: 'bg-sky-500',
    borderPrimary: 'border-sky-500',
    borderLight: 'border-sky-200',
    buttonColor: 'sky',
  },
  slate: {
    textPrimary: 'text-slate-700',
    textSecondary: 'text-slate-600',
    textOnDark: 'text-slate-100',
    textOnLight: 'text-slate-800',
    bgLight: 'bg-slate-50',
    bgMedium: 'bg-slate-100',
    bgDark: 'bg-slate-600',
    bgProgress: 'bg-slate-500',
    borderPrimary: 'border-slate-500',
    borderLight: 'border-slate-200',
    buttonColor: 'slate',
  },
};

// Default palette when color is not found
const DEFAULT_PALETTE = WORKFLOW_COLOR_PALETTES.slate;

/**
 * Get the complete color palette for a workflow color name
 */
export function getWorkflowPalette(color: string): WorkflowColorPalette {
  return WORKFLOW_COLOR_PALETTES[color] || DEFAULT_PALETTE;
}

/**
 * Get specific color classes for common use cases
 */
export function getWorkflowColors(color: string) {
  const palette = getWorkflowPalette(color);

  return {
    // Section container (light bg with dark text)
    sectionClasses: `${palette.bgLight} ${palette.borderPrimary} border-l-4`,

    // Section title
    titleClasses: `${palette.textOnLight} font-semibold`,

    // Count badge
    badgeClasses: `${palette.bgMedium} ${palette.textPrimary} px-2 py-1 rounded-full text-sm font-medium`,

    // Progress bar
    progressBarClasses: palette.bgProgress,

    // Artifact item container (slate bg with colored border)
    artifactClasses: `bg-slate-800 ${palette.borderPrimary} border-l-4`,

    // Status timeline icon (completed state)
    timelineIconBg: palette.bgProgress,
    timelineIconText: palette.textOnDark,

    // Button color for ActionButton
    buttonColor: palette.buttonColor,

    // Full palette for custom usage
    palette,
  };
}
