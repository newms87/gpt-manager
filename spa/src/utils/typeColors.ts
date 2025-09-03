/**
 * Simple type color configuration and assignment
 */

export interface TypeColor {
  bgColor: string;
  textColor: string;
  borderColor: string;
  bgColorLight: string;  // For light backgrounds (like attribute cards)
  borderColorLight: string;  // For light borders
}

// Simple color palette - 10 distinct colors
export const COLOR_PALETTE: TypeColor[] = [
  {
    bgColor: 'bg-blue-500',
    textColor: 'text-blue-100',
    borderColor: 'border-blue-400',
    bgColorLight: 'bg-blue-500/10',
    borderColorLight: 'border-blue-400/30'
  },
  {
    bgColor: 'bg-green-500',
    textColor: 'text-green-100',
    borderColor: 'border-green-400',
    bgColorLight: 'bg-green-500/10',
    borderColorLight: 'border-green-400/30'
  },
  {
    bgColor: 'bg-purple-500',
    textColor: 'text-purple-100',
    borderColor: 'border-purple-400',
    bgColorLight: 'bg-purple-500/10',
    borderColorLight: 'border-purple-400/30'
  },
  {
    bgColor: 'bg-red-500',
    textColor: 'text-red-100',
    borderColor: 'border-red-400',
    bgColorLight: 'bg-red-500/10',
    borderColorLight: 'border-red-400/30'
  },
  {
    bgColor: 'bg-yellow-500',
    textColor: 'text-yellow-100',
    borderColor: 'border-yellow-400',
    bgColorLight: 'bg-yellow-500/10',
    borderColorLight: 'border-yellow-400/30'
  },
  {
    bgColor: 'bg-indigo-500',
    textColor: 'text-indigo-100',
    borderColor: 'border-indigo-400',
    bgColorLight: 'bg-indigo-500/10',
    borderColorLight: 'border-indigo-400/30'
  },
  {
    bgColor: 'bg-pink-500',
    textColor: 'text-pink-100',
    borderColor: 'border-pink-400',
    bgColorLight: 'bg-pink-500/10',
    borderColorLight: 'border-pink-400/30'
  },
  {
    bgColor: 'bg-teal-500',
    textColor: 'text-teal-100',
    borderColor: 'border-teal-400',
    bgColorLight: 'bg-teal-500/10',
    borderColorLight: 'border-teal-400/30'
  },
  {
    bgColor: 'bg-orange-500',
    textColor: 'text-orange-100',
    borderColor: 'border-orange-400',
    bgColorLight: 'bg-orange-500/10',
    borderColorLight: 'border-orange-400/30'
  },
  {
    bgColor: 'bg-cyan-500',
    textColor: 'text-cyan-100',
    borderColor: 'border-cyan-400',
    bgColorLight: 'bg-cyan-500/10',
    borderColorLight: 'border-cyan-400/30'
  }
];

// Default fallback color
export const DEFAULT_COLOR: TypeColor = {
  bgColor: 'bg-slate-500',
  textColor: 'text-slate-100',
  borderColor: 'border-slate-400',
  bgColorLight: 'bg-slate-500/10',
  borderColorLight: 'border-slate-400/30'
};

// Cache for assigned colors
const colorCache = new Map<string, TypeColor>();

/**
 * Simple hash function for consistent color assignment
 */
function simpleHash(str: string): number {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash = hash & hash; // Convert to 32bit integer
  }
  return Math.abs(hash);
}

/**
 * Gets color for a team object type with consistent hash-based assignment
 */
export function getTypeColor(objectType: string): TypeColor {
  // Check cache first
  if (colorCache.has(objectType)) {
    return colorCache.get(objectType)!;
  }

  // Auto-assign using hash for consistency
  const hash = simpleHash(objectType);
  const colorIndex = hash % COLOR_PALETTE.length;
  const assignedColor = COLOR_PALETTE[colorIndex];
  
  // Cache the assignment
  colorCache.set(objectType, assignedColor);
  
  console.log(`🎨 Auto-assigned color ${colorIndex} to type: ${objectType}`);
  
  return assignedColor;
}

