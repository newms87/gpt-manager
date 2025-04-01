import { FragmentSelector, JsonSchema, JsonSchemaType } from "@/types";
import { computed, Ref } from "vue";

export function useFragmentSelector(fragmentSelector: Ref<FragmentSelector>, schema: JsonSchema | null) {
	const type = schema?.type;
	const isSelected = computed(() => !!fragmentSelector.value);
	const allowCreate = computed(() => isSelected.value && !!fragmentSelector.value.create);
	const allowUpdate = computed(() => isSelected.value && !!fragmentSelector.value.update);
	const selectedObjectCount = computed(() => recursiveSelectedObjectCount(fragmentSelector.value));
	const selectedPropertyCount = computed(() => recursiveSelectedPropertyCount(fragmentSelector.value));

	function changeSelection() {
		if (isSelected.value) {
			fragmentSelector.value = null;
		} else {
			if (["object", "array"].includes(type)) {
				selectAllChildren(schema);
			} else {
				fragmentSelector.value = {
					type
				};
			}
		}
	}

	function changeAllowCreate() {
		if (!fragmentSelector.value) return;
		// If the selection is a primitive type, we need to add the name to the selection
		fragmentSelector.value = {
			...fragmentSelector.value,
			create: !allowCreate.value,
			children: {
				...fragmentSelector.value.children,
				name: fragmentSelector.value.children.name || (!allowCreate.value ? { type: "string" } : undefined)
			}
		};
	}

	function changeAllowUpdate() {
		if (!fragmentSelector.value) return;
		fragmentSelector.value = { ...fragmentSelector.value, update: !allowUpdate.value };
	}

	/**
	 *  Change the selection of a child in the current selection.
	 *  If the selection is not null, this will add/replace the entry for the child in the selection set
	 *  If the selection is null, this will remove the entry of the child from the selection set.
	 */
	function changeChildSelection(childName: string, type: JsonSchemaType, selection: FragmentSelector | null) {
		const children = { ...fragmentSelector.value?.children };
		const create = fragmentSelector.value?.create || false;
		const update = fragmentSelector.value?.update || false;

		if (selection) {
			// Add the child and its selection to the parent's selected children list
			children[childName] = selection;
		} else {
			// Remove the child from the selection
			delete children[childName];
		}

		fragmentSelector.value = {
			type,
			create,
			update,
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
		fragmentSelector.value = {
			type,
			children
		};
	}

	/**
	 * Select the current schema object and all its properties / child objects + their properties and so on
	 */
	function selectAllChildren(schema: JsonSchema) {
		const children = recursiveSelectAllChildren(schema);
		fragmentSelector.value = {
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

	/**
	 * Recursively counts the number of objects / arrays that are selected in the current selection
	 */
	function recursiveSelectedObjectCount(selection: FragmentSelector) {
		if (!selection) return 0;

		let count = 1;
		for (const key of Object.keys(selection.children)) {
			const childSchema = selection.children[key];

			if (["object", "array"].includes(childSchema.type)) {
				count += recursiveSelectedObjectCount(childSchema);
			}
		}
		return count;
	}

	/**
	 * Recursively counts the number of properties that are selected in the current selection
	 */
	function recursiveSelectedPropertyCount(selection: FragmentSelector) {
		if (!selection) return 0;

		let count = 0;
		for (const key of Object.keys(selection.children)) {
			const childSchema = selection.children[key];

			if (["object", "array"].includes(childSchema.type)) {
				count += recursiveSelectedPropertyCount(childSchema);
			} else {
				count++;
			}
		}
		return count;
	}

	return {
		changeSelection,
		changeChildSelection,
		changeAllowCreate,
		changeAllowUpdate,
		selectAllChildren,
		selectAllProperties,
		recursiveSelectedObjectCount,
		recursiveSelectedPropertyCount,
		selectedObjectCount,
		selectedPropertyCount,
		isSelected,
		allowCreate,
		allowUpdate
	};
}
