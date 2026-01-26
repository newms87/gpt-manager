import { getItem, setItem } from "quasar-ui-danx";
import { computed, ComputedRef, ref, Ref, toValue } from "vue";
import { RefOrGetter, SelectionMode } from "./types";

export interface FragmentSelectorModesResult {
	// Input parameters (exposed for child components)
	selectionEnabled: ComputedRef<boolean>;
	editEnabled: ComputedRef<boolean>;
	selectionMode: ComputedRef<SelectionMode>;
	// Internal state
	isEditModeActive: Ref<boolean | null>;
	effectiveSelectionEnabled: ComputedRef<boolean>;
	effectiveEditEnabled: ComputedRef<boolean>;
	showPropertiesInternal: Ref<boolean>;
	showCodeSidebar: Ref<boolean>;
	// Methods
	toggleShowProperties: () => void;
	toggleShowCode: () => void;
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

	// Internal state for showCode sidebar with localStorage persistence
	const showCodeSidebar = ref<boolean>(getItem("fragmentSelector.showCode") ?? false);

	// Internal state for edit mode toggle (only used when both selectionEnabled and editEnabled are true)
	// null = neither mode active (readonly), true = edit mode, false = select mode
	const isEditModeActive = ref<boolean | null>(null);

	// Computed for determining effective modes when both are enabled
	const effectiveSelectionEnabled = computed(() => {
		const selEnabled = toValue(selectionEnabled);
		const edEnabled = toValue(editEnabled);
		if (selEnabled && edEnabled) {
			return isEditModeActive.value === false; // Selection only when explicitly in select mode
		}
		return selEnabled && isEditModeActive.value !== false;
	});

	const effectiveEditEnabled = computed(() => {
		const selEnabled = toValue(selectionEnabled);
		const edEnabled = toValue(editEnabled);
		if (selEnabled && edEnabled) {
			return isEditModeActive.value === true; // Edit only when explicitly in edit mode
		}
		return edEnabled && isEditModeActive.value !== false;
	});

	// Toggle show properties with persistence
	function toggleShowProperties(): void {
		showPropertiesInternal.value = !showPropertiesInternal.value;
		setItem("fragmentSelector.showProperties", showPropertiesInternal.value);
		// Notify parent that mode changed (for re-layout)
		onModeChange?.();
	}

	// Toggle show code sidebar with persistence
	function toggleShowCode(): void {
		showCodeSidebar.value = !showCodeSidebar.value;
		setItem("fragmentSelector.showCode", showCodeSidebar.value);
	}

	// Expose input parameters as computed properties for child components
	const selectionEnabledComputed = computed(() => toValue(selectionEnabled));
	const editEnabledComputed = computed(() => toValue(editEnabled));
	const selectionModeComputed = computed(() => toValue(selectionMode));

	return {
		// Input parameters (exposed for child components)
		selectionEnabled: selectionEnabledComputed,
		editEnabled: editEnabledComputed,
		selectionMode: selectionModeComputed,
		// Internal state
		isEditModeActive,
		effectiveSelectionEnabled,
		effectiveEditEnabled,
		showPropertiesInternal,
		showCodeSidebar,
		// Methods
		toggleShowProperties,
		toggleShowCode
	};
}
