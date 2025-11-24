/**
 * Unified Hash-Based Color Assignment Composable
 * 
 * Provides deterministic color assignment based on string hashing.
 * Supports multiple color formats for different use cases:
 * - Simple color names (e.g., "sky", "blue")
 * - Tailwind class objects (e.g., {bg: "bg-sky-950", text: "text-sky-400"})
 * - Full color objects with multiple properties
 * 
 * Usage Examples:
 * 
 * // Simple color names (for LabelPillWidget)
 * const color = useHashedColor(operationName);
 * // Returns: "sky"
 * 
 * // Tailwind dark theme classes (for log entries)
 * const colors = useHashedColor(entityName, { format: 'tailwind-dark' });
 * // Returns: {bg: "bg-sky-950", text: "text-sky-400"}
 * 
 * // Full color objects (for team object types)
 * const typeColor = useHashedColor(typeName, { format: 'full' });
 * // Returns: {bgColor: "bg-sky-500", textColor: "text-sky-100", ...}
 * 
 * // Custom palette
 * const color = useHashedColor(value, { 
 *   palette: ['red', 'blue', 'green'],
 *   defaultColor: 'gray'
 * });
 * 
 * // With caching enabled (useful for repeated lookups)
 * const color = useHashedColor(typeName, { cache: true });
 */

import { computed, Ref, unref } from "vue";

/**
 * Simple color name type (used by LabelPillWidget and other components)
 */
export type SimpleColorName = 
  | "slate" | "gray" | "zinc" | "neutral" | "stone"
  | "red" | "orange" | "amber" | "yellow" | "lime"
  | "green" | "emerald" | "teal" | "cyan" | "sky"
  | "blue" | "indigo" | "violet" | "purple" | "fuchsia"
  | "pink" | "rose";

/**
 * Tailwind dark theme color configuration
 * Uses dark backgrounds (950) with light text (400)
 */
export interface TailwindDarkConfig {
  bg: string;
  text: string;
}

/**
 * Full color configuration with light/dark variants
 * Used for complex UI elements like team object types
 */
export interface FullColorConfig {
  bgColor: string;
  textColor: string;
  borderColor: string;
  bgColorLight: string;
  borderColorLight: string;
}

/**
 * Color format types
 */
export type ColorFormat = 'simple' | 'tailwind-dark' | 'full';

/**
 * Generic color type that can be any of the above
 */
export type AnyColor = SimpleColorName | TailwindDarkConfig | FullColorConfig;

/**
 * Options for color generation
 */
export interface UseHashedColorOptions<T extends AnyColor = SimpleColorName> {
  format?: ColorFormat;
  palette?: T[];
  defaultColor?: T;
  cache?: boolean;
}

/**
 * Default palettes for each format
 */

// Simple color names palette (14 colors)
const SIMPLE_PALETTE: SimpleColorName[] = [
  "sky",
  "blue",
  "indigo",
  "purple",
  "violet",
  "fuchsia",
  "rose",
  "orange",
  "amber",
  "lime",
  "green",
  "emerald",
  "teal",
  "cyan"
];

// Tailwind dark theme palette (14 colors)
const TAILWIND_DARK_PALETTE: TailwindDarkConfig[] = [
  { bg: "bg-sky-950", text: "text-sky-400" },
  { bg: "bg-blue-950", text: "text-blue-400" },
  { bg: "bg-indigo-950", text: "text-indigo-400" },
  { bg: "bg-purple-950", text: "text-purple-400" },
  { bg: "bg-pink-950", text: "text-pink-400" },
  { bg: "bg-rose-950", text: "text-rose-400" },
  { bg: "bg-orange-950", text: "text-orange-400" },
  { bg: "bg-amber-950", text: "text-amber-400" },
  { bg: "bg-yellow-950", text: "text-yellow-400" },
  { bg: "bg-lime-950", text: "text-lime-400" },
  { bg: "bg-green-950", text: "text-green-400" },
  { bg: "bg-emerald-950", text: "text-emerald-400" },
  { bg: "bg-teal-950", text: "text-teal-400" },
  { bg: "bg-cyan-950", text: "text-cyan-400" }
];

// Full color palette (10 colors)
const FULL_PALETTE: FullColorConfig[] = [
  {
    bgColor: "bg-blue-500",
    textColor: "text-blue-100",
    borderColor: "border-blue-400",
    bgColorLight: "bg-blue-500/10",
    borderColorLight: "border-blue-400/30"
  },
  {
    bgColor: "bg-green-500",
    textColor: "text-green-100",
    borderColor: "border-green-400",
    bgColorLight: "bg-green-500/10",
    borderColorLight: "border-green-400/30"
  },
  {
    bgColor: "bg-purple-500",
    textColor: "text-purple-100",
    borderColor: "border-purple-400",
    bgColorLight: "bg-purple-500/10",
    borderColorLight: "border-purple-400/30"
  },
  {
    bgColor: "bg-red-500",
    textColor: "text-red-100",
    borderColor: "border-red-400",
    bgColorLight: "bg-red-500/10",
    borderColorLight: "border-red-400/30"
  },
  {
    bgColor: "bg-yellow-500",
    textColor: "text-yellow-100",
    borderColor: "border-yellow-400",
    bgColorLight: "bg-yellow-500/10",
    borderColorLight: "border-yellow-400/30"
  },
  {
    bgColor: "bg-indigo-500",
    textColor: "text-indigo-100",
    borderColor: "border-indigo-400",
    bgColorLight: "bg-indigo-500/10",
    borderColorLight: "border-indigo-400/30"
  },
  {
    bgColor: "bg-pink-500",
    textColor: "text-pink-100",
    borderColor: "border-pink-400",
    bgColorLight: "bg-pink-500/10",
    borderColorLight: "border-pink-400/30"
  },
  {
    bgColor: "bg-teal-500",
    textColor: "text-teal-100",
    borderColor: "border-teal-400",
    bgColorLight: "bg-teal-500/10",
    borderColorLight: "border-teal-400/30"
  },
  {
    bgColor: "bg-orange-500",
    textColor: "text-orange-100",
    borderColor: "border-orange-400",
    bgColorLight: "bg-orange-500/10",
    borderColorLight: "border-orange-400/30"
  },
  {
    bgColor: "bg-cyan-500",
    textColor: "text-cyan-100",
    borderColor: "border-cyan-400",
    bgColorLight: "bg-cyan-500/10",
    borderColorLight: "border-cyan-400/30"
  }
];

/**
 * Default colors for each format
 */
const DEFAULT_SIMPLE: SimpleColorName = "slate";
const DEFAULT_TAILWIND_DARK: TailwindDarkConfig = { bg: "bg-slate-950", text: "text-slate-400" };
const DEFAULT_FULL: FullColorConfig = {
  bgColor: "bg-slate-500",
  textColor: "text-slate-100",
  borderColor: "border-slate-400",
  bgColorLight: "bg-slate-500/10",
  borderColorLight: "border-slate-400/30"
};

/**
 * Global caches for each format
 * Keyed by format:value for isolation
 */
const simpleCache = new Map<string, SimpleColorName>();
const tailwindDarkCache = new Map<string, TailwindDarkConfig>();
const fullCache = new Map<string, FullColorConfig>();

/**
 * Simple hash function for deterministic color assignment
 * Same input will always produce the same hash
 */
function hashString(str: string): number {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash = hash & hash; // Convert to 32-bit integer
  }
  return Math.abs(hash);
}

/**
 * Get the appropriate cache for a format
 */
function getCache<T extends AnyColor>(format: ColorFormat): Map<string, T> | null {
  switch (format) {
    case 'simple':
      return simpleCache as Map<string, T>;
    case 'tailwind-dark':
      return tailwindDarkCache as Map<string, T>;
    case 'full':
      return fullCache as Map<string, T>;
    default:
      return null;
  }
}

/**
 * Get the default palette for a format
 */
function getDefaultPalette<T extends AnyColor>(format: ColorFormat): T[] {
  switch (format) {
    case 'simple':
      return SIMPLE_PALETTE as T[];
    case 'tailwind-dark':
      return TAILWIND_DARK_PALETTE as T[];
    case 'full':
      return FULL_PALETTE as T[];
    default:
      return SIMPLE_PALETTE as T[];
  }
}

/**
 * Get the default color for a format
 */
function getDefaultColor<T extends AnyColor>(format: ColorFormat): T {
  switch (format) {
    case 'simple':
      return DEFAULT_SIMPLE as T;
    case 'tailwind-dark':
      return DEFAULT_TAILWIND_DARK as T;
    case 'full':
      return DEFAULT_FULL as T;
    default:
      return DEFAULT_SIMPLE as T;
  }
}

/**
 * Reactive hash-based color assignment
 * 
 * @param value - String value to hash (can be reactive)
 * @param options - Color generation options
 * @returns Computed color value that updates when input changes
 */
export function useHashedColor<T extends AnyColor = SimpleColorName>(
  value: Ref<string | null | undefined> | string | null | undefined,
  options: UseHashedColorOptions<T> = {}
): Ref<T> {
  const {
    format = 'simple',
    palette = getDefaultPalette<T>(format),
    defaultColor = getDefaultColor<T>(format),
    cache = false
  } = options;

  return computed(() => {
    const inputValue = unref(value);

    // Handle null/undefined
    if (!inputValue) {
      return defaultColor;
    }

    // Check cache if enabled
    if (cache) {
      const cacheMap = getCache<T>(format);
      const cacheKey = `${format}:${inputValue}`;
      
      if (cacheMap?.has(cacheKey)) {
        return cacheMap.get(cacheKey)!;
      }
    }

    // Calculate color from hash
    const hash = hashString(inputValue);
    const index = hash % palette.length;
    const color = palette[index];

    // Store in cache if enabled
    if (cache) {
      const cacheMap = getCache<T>(format);
      const cacheKey = `${format}:${inputValue}`;
      cacheMap?.set(cacheKey, color);
    }

    return color;
  });
}

/**
 * Non-reactive hash-based color assignment
 * For use in non-reactive contexts or when you don't need reactivity
 * 
 * @param value - String value to hash
 * @param options - Color generation options
 * @returns Color value (not reactive)
 */
export function getHashedColor<T extends AnyColor = SimpleColorName>(
  value: string | null | undefined,
  options: UseHashedColorOptions<T> = {}
): T {
  const {
    format = 'simple',
    palette = getDefaultPalette<T>(format),
    defaultColor = getDefaultColor<T>(format),
    cache = false
  } = options;

  // Handle null/undefined
  if (!value) {
    return defaultColor;
  }

  // Check cache if enabled
  if (cache) {
    const cacheMap = getCache<T>(format);
    const cacheKey = `${format}:${value}`;
    
    if (cacheMap?.has(cacheKey)) {
      return cacheMap.get(cacheKey)!;
    }
  }

  // Calculate color from hash
  const hash = hashString(value);
  const index = hash % palette.length;
  const color = palette[index];

  // Store in cache if enabled
  if (cache) {
    const cacheMap = getCache<T>(format);
    const cacheKey = `${format}:${value}`;
    cacheMap?.set(cacheKey, color);
  }

  return color;
}

/**
 * Export palette constants for direct use
 */
export {
  SIMPLE_PALETTE,
  TAILWIND_DARK_PALETTE,
  FULL_PALETTE,
  DEFAULT_SIMPLE,
  DEFAULT_TAILWIND_DARK,
  DEFAULT_FULL
};
