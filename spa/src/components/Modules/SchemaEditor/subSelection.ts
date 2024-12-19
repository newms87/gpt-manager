import { JsonSchema, SelectionSchema } from "@/types";
import { computed, Ref } from "vue";

export function useSubSelection(subSelection: Ref<SelectionSchema>, schema: JsonSchema) {
	const type = schema.type;
	const isSelected = computed(() => !!subSelection.value);

	function changeSelection() {
		if (isSelected.value) {
			subSelection.value = null;
		} else {
			if (["object", "array"].includes(type)) {
				selectAllChildren(schema);
			} else {
				subSelection.value = {
					type
				};
			}
		}
	}

	/**
	 *  Change the selection of a child in the current selection.
	 *  If the selection is not null, this will add/replace the entry for the child in the selection set
	 *  If the selection is null, this will remove the entry of the child from the selection set.
	 */
	function changeChildSelection(childName: string, selection: SelectionSchema | null) {
		const children = { ...subSelection.value?.children };

		if (selection) {
			// Add the child and its selection to the parent's selected children list
			children[childName] = selection;
		} else {
			// Remove the child from the selection
			delete children[childName];
		}

		subSelection.value = {
			type: "object",
			children
		};
	}

	/**
	 * Select all properties of the top level object in the given schema.
	 * NOTE: This will also force selecting the top level object in order to select the properties
	 */
	function selectAllProperties(schema: JsonSchema) {
		const properties = schema.items?.properties || schema.properties;

		if (!properties) {
			return;
		}

		// Create a new object with the keys of the properties (primitive types only) and their types
		const children = Object.fromEntries(
				Object.keys(properties)
						// Filter out arrays / objects (only want to select primitive types)
						.filter(key => !["array", "object"].includes(properties[key].type))
						// Map the key to a [key, { type }] pair
						.map((key) => [key, { type: properties[key].type }])
		);
		subSelection.value = {
			type,
			children
		};
	}

	/**
	 * Select the current schema object and all its properties / child objects + their properties and so on
	 */
	function selectAllChildren(schema: JsonSchema) {
		const children = recursiveSelectAllChildren(schema);
		subSelection.value = {
			type,
			children
		};
	}

	/**
	 * Recursively selects all child objects / arrays and the properties of the children
	 * NOTE: This will also force selecting the top level object in order to select the properties
	 */
	function recursiveSelectAllChildren(schema: JsonSchema) {
		const properties = schema.items?.properties || schema.properties;

		if (!properties) {
			return {};
		}

		return Object.fromEntries(
				Object.keys(properties)
						.map((key) => {
							const childSchema = properties[key];
							const childType = childSchema.type;

							if (["object", "array"].includes(childType)) {
								return [key, {
									type: childType,
									children: recursiveSelectAllChildren(childSchema)
								}];
							}

							return [key, { type: childType }];
						})
		);
	}

	return {
		changeSelection,
		changeChildSelection,
		selectAllChildren,
		selectAllProperties,
		isSelected
	};
}
