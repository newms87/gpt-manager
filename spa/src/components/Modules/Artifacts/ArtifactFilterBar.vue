<template>
	<div class="relative">
		<!-- Filter toggle button -->
		<ShowHideButton
			v-model="isFilterMenuOpen"
			class="bg-sky-900"
			:show-icon="FilterIcon"
			size="sm"
			tooltip="Toggle Filters"
		>
			<template #default>
				<span v-if="activeFilterCount > 0" class="ml-1 px-2 py-0.5 bg-white text-sky-900 rounded-full text-xs font-medium">
					{{ activeFilterCount }}
				</span>
			</template>
		</ShowHideButton>

		<!-- Filter popup menu -->
		<QMenu
			v-model="isFilterMenuOpen"
			auto-close
		>
			<div class="p-4 w-72 bg-white rounded-md shadow-lg space-y-4">
				<!-- Text search -->
				<div class="space-y-2">
					<label for="text-search" class="block text-sm font-medium text-gray-700">Search text</label>
					<input
						id="text-search"
						v-model="textSearchValue"
						type="text"
						placeholder="Search artifacts..."
						class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
						@input="updateTextFilter"
					/>
				</div>

				<!-- Filter toggles -->
				<div class="space-y-2">
					<h3 class="text-sm font-medium text-gray-700">Show only artifacts with:</h3>
					<div class="space-y-2">
						<!-- Use ShowHideButton for each filter type -->
						<ShowHideButton
							v-model="filterStates.text_content"
							class="w-full justify-between"
							:class="filterStates.text_content ? 'bg-green-900 text-white' : 'bg-white text-gray-700 border border-gray-300'"
							:show-icon="TextIcon"
							@update:modelValue="toggleFilter('text_content', $event)"
						>
							<template #default>
								<span>Text Content</span>
							</template>
						</ShowHideButton>

						<ShowHideButton
							v-model="filterStates.storedFiles_id"
							class="w-full justify-between"
							:class="filterStates.storedFiles_id ? 'bg-amber-900 text-white' : 'bg-white text-gray-700 border border-gray-300'"
							:show-icon="FilesIcon"
							@update:modelValue="toggleFilter('storedFiles.id', $event)"
						>
							<template #default>
								<span>Files</span>
							</template>
						</ShowHideButton>

						<ShowHideButton
							v-model="filterStates.json_content"
							class="w-full justify-between"
							:class="filterStates.json_content ? 'bg-purple-700 text-white' : 'bg-white text-gray-700 border border-gray-300'"
							:show-icon="JsonIcon"
							@update:modelValue="toggleFilter('json_content', $event)"
						>
							<template #default>
								<span>JSON Content</span>
							</template>
						</ShowHideButton>

						<ShowHideButton
							v-model="filterStates.meta"
							class="w-full justify-between"
							:class="filterStates.meta ? 'bg-slate-500 text-white' : 'bg-white text-gray-700 border border-gray-300'"
							:show-icon="MetaIcon"
							@update:modelValue="toggleFilter('meta', $event)"
						>
							<template #default>
								<span>Meta Data</span>
							</template>
						</ShowHideButton>
					</div>
				</div>

				<!-- Reset filters button -->
				<div class="pt-2 border-t border-gray-200">
					<button
						class="w-full px-3 py-2 rounded-md text-sm bg-gray-200 text-gray-800 hover:bg-gray-300 transition-colors"
						@click="resetFilters"
					>
						Reset All Filters
					</button>
				</div>
			</div>
		</QMenu>
	</div>
</template>

<script setup lang="ts">
import {
	FaSolidBarcode as MetaIcon,
	FaSolidDatabase as JsonIcon,
	FaSolidFile as FilesIcon,
	FaSolidFilter as FilterIcon,
	FaSolidT as TextIcon
} from "danx-icon";
import { AnyObject, ShowHideButton } from "quasar-ui-danx";
import { computed, reactive, ref, watch } from "vue";

const props = defineProps<{
	modelValue: AnyObject
}>();

const emit = defineEmits<{
	"update:modelValue": [value: AnyObject]
}>();

// Text search input field
const textSearchValue = ref("");

// Local state for filter menu
const isFilterMenuOpen = ref(false);

// Reactive filter states to track UI toggle states
const filterStates = reactive({
	text_content: false,
	storedFiles_id: false,
	json_content: false,
	meta: false
});

// Initialize filters based on modelValue
function initializeFilters() {
	// Reset states
	textSearchValue.value = "";
	Object.keys(filterStates).forEach(key => {
		filterStates[key] = false;
	});

	// Set text search if exists
	if (props.modelValue?.text_content?.operator === "contains") {
		textSearchValue.value = props.modelValue.text_content.value || "";
	}

	// Set filter toggle states
	if (isFilterActive("text_content")) filterStates.text_content = true;
	if (isFilterActive("storedFiles.id")) filterStates.storedFiles_id = true;
	if (isFilterActive("json_content")) filterStates.json_content = true;
	if (isFilterActive("meta")) filterStates.meta = true;
}

// Watch for external filter changes
watch(() => props.modelValue, initializeFilters, { immediate: true, deep: true });

// Count active filters
const activeFilterCount = computed(() => {
	let count = 0;

	// Count text search if present
	if (props.modelValue?.text_content?.operator === "contains" && props.modelValue.text_content.value) {
		count++;
	}

	// Count exists filters
	if (isFilterActive("text_content")) count++;
	if (isFilterActive("storedFiles.id")) count++;
	if (isFilterActive("json_content")) count++;
	if (isFilterActive("meta")) count++;

	return count;
});

// Check if a specific filter is active
function isFilterActive(field: string): boolean {
	return props.modelValue?.[field]?.operator === "exists" && props.modelValue[field].value === true;
}

// Update text search filter
function updateTextFilter() {
	const newFilters = { ...props.modelValue };

	if (textSearchValue.value) {
		newFilters.text_content = {
			operator: "contains",
			value: textSearchValue.value
		};
	} else {
		// Remove the contains operator if text is empty
		if (newFilters.text_content?.operator === "contains") {
			delete newFilters.text_content;
		}
	}

	emit("update:modelValue", newFilters);
}

// Toggle filter for a specific field
function toggleFilter(field: string, value: boolean) {
	const newFilters = { ...props.modelValue };
	const fieldKey = field.replace(".", "_"); // Handle storedFiles.id -> storedFiles_id for reactive state

	if (value) {
		// Add the exists filter
		newFilters[field] = { operator: "exists", value: true };
	} else {
		// Remove the filter
		delete newFilters[field];
	}

	// Update UI state (already handled by v-model)
	filterStates[fieldKey] = value;
	
	emit("update:modelValue", newFilters);
}

// Reset all filters
function resetFilters() {
	Object.keys(filterStates).forEach(key => {
		filterStates[key] = false;
	});
	
	emit("update:modelValue", {});
	textSearchValue.value = "";
	isFilterMenuOpen.value = false;
}
</script>
