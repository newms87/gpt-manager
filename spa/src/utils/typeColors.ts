/**
 * Backward-compatible wrapper around useHashedColor composable.
 *
 * This file maintains the existing getTypeColor() API while delegating
 * to the centralized useHashedColor composable to eliminate code duplication.
 *
 * All hash logic, palette definitions, and caching are now handled by
 * the composable in @/composables/useHashedColor.
 */

import { getHashedColor, type FullColorConfig } from "@/composables/useHashedColor";

/**
 * Type alias for backward compatibility.
 * Components can continue using TypeColor while we use FullColorConfig internally.
 */
export type TypeColor = FullColorConfig;

/**
 * Gets color for a team object type with consistent hash-based assignment.
 * Colors are cached for performance.
 *
 * @param objectType - The type name to get color for
 * @returns TypeColor configuration with all color variants
 */
export function getTypeColor(objectType: string): TypeColor {
  // Delegate to composable with caching enabled
  // The composable handles all hash logic, palette selection, and caching
  return getHashedColor(objectType, {
    format: 'full',
    cache: true
  }) as TypeColor;
}

