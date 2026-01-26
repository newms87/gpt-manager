/**
 * FragmentSelector Module
 *
 * A visual schema editor and fragment selector component for JSON schemas.
 * Provides both selection (for extracting schema fragments) and editing
 * (for building schemas) capabilities.
 *
 * Main Components:
 * - FragmentSelectorCanvas: The main canvas component for displaying and interacting with schemas
 * - FragmentSelectorDialog: A dialog wrapper for the canvas component
 *
 * Key Composables:
 * - useFragmentSelection: Manages fragment selection state
 * - useFragmentSchemaEditor: Provides immutable schema editing operations
 */

// Main Components
export { default as FragmentSelectorCanvas } from "./FragmentSelectorCanvas.vue";
export { default as FragmentSelectorDialog } from "./FragmentSelectorDialog.vue";

// Key Composables
export { useFragmentSelection } from "./useFragmentSelection";
export { useFragmentSchemaEditor } from "./useFragmentSchemaEditor";

// Types
export type {
	LayoutDirection,
	SelectionMode,
	PropertyInfo,
	SelectionRollupState,
	FragmentModelNodeData,
	RefOrGetter
} from "./types";

// Statistics helpers
export { countModels, countProperties } from "./fragmentSelectorStats";
