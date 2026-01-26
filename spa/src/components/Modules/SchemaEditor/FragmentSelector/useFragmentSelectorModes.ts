import { getItem, setItem } from "quasar-ui-danx";
import { computed, ref, Ref, toValue } from "vue";
import { RefOrGetter, SelectionMode } from "./types";

export interface FragmentSelectorModesResult {
	isEditModeActive: Ref<boolean>;
	effectiveSelectionEnabled: Ref<boolean>;
	effectiveEditEnabled: Ref<boolean>;
	showPropertiesInternal: Ref<boolean>;
	toggleShowProperties: () => void;
}

/**
 * Composable that manages mode state for FragmentSelectorCanvas.
 * Handles edit/selection mode toggling and show properties persistence.
 */
export function useFragmentSelectorModes(
	selectionEnabled: RefOrGetter<boolean>,
	editEnabled: RefOrGetter<boolean>,
	selectionMode: RefOrGetter<SelectionMode>,
	onModeChange?: () => void
): FragmentSelectorModesResult {
	// Internal state for showProperties with localStorage persistence
	const showPropertiesInternal = ref<boolean>(getItem("fragmentSelector.showProperties") ?? false);

	// Internal state for edit mode toggle (only used when both selectionEnabled and editEnabled are true)
	const isEditModeActive = ref(false);

	// Computed for determining effective modes when both are enabled
	const effectiveSelectionEnabled = computed(() => {
		const selEnabled = toValue(selectionEnabled);
		const edEnabled = toValue(editEnabled);
		if (selEnabled && edEnabled) {
			return !isEditModeActive.value; // Selection when NOT in edit mode
		}
		return selEnabled;
	});

	const effectiveEditEnabled = computed(() => {
		const selEnabled = toValue(selectionEnabled);
		const edEnabled = toValue(editEnabled);
		if (selEnabled && edEnabled) {
			return isEditModeActive.value; // Edit when toggle is on
		}
		return edEnabled;
	});

	// Toggle show properties with persistence
	function toggleShowProperties(): void {
		showPropertiesInternal.value = !showPropertiesInternal.value;
		setItem("fragmentSelector.showProperties", showPropertiesInternal.value);
		// Notify parent that mode changed (for re-layout)
		onModeChange?.();
	}

	return {
		isEditModeActive,
		effectiveSelectionEnabled,
		effectiveEditEnabled,
		showPropertiesInternal,
		toggleShowProperties
	};
}
