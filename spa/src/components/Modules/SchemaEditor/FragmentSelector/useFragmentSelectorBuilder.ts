import { FragmentSelector, JsonSchema } from "@/types";
import { computed, toValue } from "vue";
import { getSchemaProperties, isModelType } from "./useSchemaNavigation";

/**
 * Composable that handles building and parsing FragmentSelector structures.
 * Converts between internal selection state and the FragmentSelector format.
 */
export function useFragmentSelectorBuilder(
	selectionMap: Map<string, Set<string>>,
	schemaGetter: () => JsonSchema
) {
	/**
	 * Build a FragmentSelector tree for the given path from the current selection state.
	 */
	function buildSelectorForPath(path: string, nodeSchema: JsonSchema): FragmentSelector | null {
		const selected = selectionMap.get(path);
		// If path is not in selectionMap at all, return null
		if (!selected) return null;

		// If selected is empty but path is in map (e.g., root with no children), return just the type
		if (selected.size === 0) {
			return { type: nodeSchema.type };
		}

		const properties = getSchemaProperties(nodeSchema);
		if (!properties) return { type: nodeSchema.type };

		const children: Record<string, FragmentSelector> = {};

		for (const name of selected) {
			const childSchema = properties[name];
			if (!childSchema) continue;

			if (isModelType(childSchema.type)) {
				const childPath = `${path}.${name}`;
				const childSelector = buildSelectorForPath(childPath, childSchema);
				children[name] = childSelector || { type: childSchema.type };
			} else {
				children[name] = { type: childSchema.type };
			}
		}

		return {
			type: nodeSchema.type,
			children
		};
	}

	/**
	 * Computed FragmentSelector built from the current selection state.
	 * Returns {} when nothing is selected, {type: "object"} when root is selected.
	 */
	const fragmentSelector = computed<FragmentSelector>(() => {
		return buildSelectorForPath("root", toValue(schemaGetter())) || {};
	});

	/**
	 * Parse a FragmentSelector into the internal selection map.
	 */
	function parseFragmentSelector(selector: FragmentSelector | null, path: string): void {
		if (!selector) return;

		// If selector has a type but no children, mark this path as selected with empty set
		// This handles model-only mode where root is selected with no children
		if (selector.type && !selector.children) {
			selectionMap.set(path, new Set());
			return;
		}

		if (!selector.children) return;

		const selectedNames = new Set<string>();

		for (const [childName, childSelector] of Object.entries(selector.children)) {
			selectedNames.add(childName);

			// Recurse into child models
			if (childSelector.children && isModelType(childSelector.type)) {
				parseFragmentSelector(childSelector, `${path}.${childName}`);
			}
		}

		if (selectedNames.size > 0) {
			selectionMap.set(path, selectedNames);
		}
	}

	/**
	 * Sync external FragmentSelector value into internal selection state.
	 */
	function syncFromExternal(value: FragmentSelector | null): void {
		selectionMap.clear();
		parseFragmentSelector(value, "root");
	}

	return {
		fragmentSelector,
		syncFromExternal
	};
}
