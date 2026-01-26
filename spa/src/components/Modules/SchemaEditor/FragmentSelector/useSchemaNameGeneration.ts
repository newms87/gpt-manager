import { JsonSchema } from "@/types";

/**
 * Generate a unique property name by auto-incrementing if necessary.
 * E.g., "prop", "prop_1", "prop_2", etc.
 */
export function generateUniqueName(baseName: string, existingNames: string[]): string {
	if (!existingNames.includes(baseName)) {
		return baseName;
	}

	let count = 1;
	let name = `${baseName}_${count}`;
	while (existingNames.includes(name)) {
		count++;
		name = `${baseName}_${count}`;
	}

	return name;
}

/**
 * Get the next position value for a new property.
 * Finds the maximum existing position and returns max + 1.
 */
export function getNextPosition(properties: Record<string, JsonSchema>): number {
	const positions = Object.values(properties).map(p => p.position ?? 0);
	return positions.length > 0 ? Math.max(...positions) + 1 : 0;
}
