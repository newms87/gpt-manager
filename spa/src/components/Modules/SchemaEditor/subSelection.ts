import { JsonSchema, SelectionSchema } from "@/types";
import { computed, Ref } from "vue";

export function useSubSelection(relationName: string, schema: JsonSchema, subSelection: Ref<SelectionSchema>) {
	const isSelected = computed(() => !!subSelection.value);

	function changeSelection() {
		console.log("toggle", isSelected.value, subSelection.value);

		if (isSelected.value) {
			subSelection.value = null;
		} else {
			subSelection.value = {
				type: "object",
				children: {}
			};
		}
	}

	function changeChildSelection(childName: string, selection: SelectionSchema | null) {
		console.log("changing child", childName, selection);
		if (selection) {
			// Add the child and its selection to the parent's selected children list
			subSelection.value = {
				type: "object",
				children: {
					...subSelection.value?.children,
					[childName]: selection
				}
			};
		} else {
			// Remove the child from the selection
			const newChildren = { ...subSelection.value?.children };
			delete newChildren[childName];
			subSelection.value = {
				type: "object",
				children: newChildren
			};
		}

		console.log("selected child", subSelection.value);
	}

	return {
		changeSelection,
		changeChildSelection,
		isSelected
	};
}
