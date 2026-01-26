import { JsonSchema } from "@/types";
import { reactive, toValue } from "vue";
import { RefOrGetter } from "./types";
import { useFragmentSelectorBuilder } from "./useFragmentSelectorBuilder";
import { useSelectionParentChain } from "./useSelectionParentChain";
import { useSelectionQueries } from "./useSelectionQueries";
import { useToggleHandlers } from "./useToggleHandlers";

/**
 * Composable that manages fragment selection state for a schema graph.
 * Encapsulates all selection logic: toggling individual properties,
 * toggling all (with recursive/model-only modes), building the
 * FragmentSelector output, and syncing from external values.
 *
 * Selection is controlled by two parameters:
 * - selectionMode: "by-model" (include models only) | "by-property" (include properties)
 * - recursive: whether to recurse into child models
 *
 * This is a facade that composes smaller, focused composables:
 * - useSelectionParentChain: Parent chain management
 * - useToggleHandlers: Toggle operations
 * - useFragmentSelectorBuilder: Build/parse FragmentSelector
 * - useSelectionQueries: Query selection state
 */
export function useFragmentSelection(
	schema: RefOrGetter<JsonSchema>,
	selectionMode: RefOrGetter<"by-model" | "by-property">,
	recursive: RefOrGetter<boolean> = () => true
) {
	// Internal selection state: path -> Set of selected property names
	const selectionMap = reactive(new Map<string, Set<string>>());

	// Schema getter for child composables
	const schemaGetter = () => toValue(schema);

	// Compose parent chain utilities
	const parentChain = useSelectionParentChain(selectionMap);

	// Compose toggle handlers
	const { onToggleProperty, onToggleAll } = useToggleHandlers(
		selectionMap,
		schemaGetter,
		selectionMode,
		recursive,
		parentChain
	);

	// Compose selector builder
	const { fragmentSelector, syncFromExternal } = useFragmentSelectorBuilder(
		selectionMap,
		schemaGetter
	);

	// Compose selection queries
	const { getSelectionRollupState } = useSelectionQueries(
		selectionMap,
		schemaGetter,
		parentChain
	);

	return {
		selectionMap,
		onToggleProperty,
		onToggleAll,
		fragmentSelector,
		syncFromExternal,
		getSelectionRollupState
	};
}
