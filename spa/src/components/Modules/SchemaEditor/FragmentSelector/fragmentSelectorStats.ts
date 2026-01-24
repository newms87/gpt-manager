import { isModelType } from "./useFragmentSelectorGraph";
import { FragmentSelector } from "@/types";

/**
 * Recursively count the number of model nodes in a FragmentSelector tree.
 */
export function countModels(selector: FragmentSelector | null): number {
	if (!selector) return 0;

	let count = 1;
	if (!selector.children) return count;

	for (const child of Object.values(selector.children)) {
		if (isModelType(child.type)) {
			count += countModels(child);
		}
	}
	return count;
}

/**
 * Recursively count the number of scalar (non-model) properties in a FragmentSelector tree.
 */
export function countProperties(selector: FragmentSelector | null): number {
	if (!selector) return 0;

	let count = 0;
	if (!selector.children) return count;

	for (const child of Object.values(selector.children)) {
		if (isModelType(child.type)) {
			count += countProperties(child);
		} else {
			count++;
		}
	}
	return count;
}
