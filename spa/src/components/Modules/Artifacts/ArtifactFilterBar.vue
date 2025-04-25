<template>
	<div class="relative">
		<button
			class="px-3 py-2 rounded-md text-sm bg-sky-900 text-white hover:bg-sky-800 transition-colors flex items-center gap-2"
			aria-haspopup="true"
			:aria-expanded="isFilterMenuOpen ? 'true' : 'false'"
			@click="isFilterMenuOpen = !isFilterMenuOpen"
		>
      <span class="flex items-center justify-center">
        <FaSolidFilter class="h-4 w-4" />
      </span>
			<span
				v-if="activeFilterCount > 0"
				class="ml-1 px-2 py-0.5 bg-white text-sky-900 rounded-full text-xs font-medium"
			>
        {{ activeFilterCount }}
      </span>
		</button>

		<!-- Filter popup menu -->
		<div
			v-if="isFilterMenuOpen"
			class="absolute z-10 mt-2 w-72 bg-white rounded-md shadow-lg p-4 space-y-4"
			@click.outside="isFilterMenuOpen = false"
		>
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
					<!-- Text Content Filter -->
					<button
						:class="[
              'w-full px-3 py-2 rounded-md text-sm border flex items-center justify-between',
              isFilterActive('text_content') ? 'bg-green-900 text-white border-green-700' : 'bg-white text-gray-700 border-gray-300'
            ]"
						@click="toggleExistsFilter('text_content')"
					>
						<div class="flex items-center gap-2">
							<TextIcon class="h-4 w-4" />
							<span>Text Content</span>
						</div>
						<FaSolidCheck class="h-5 w-5" :class="{ 'opacity-0': !isFilterActive('text_content') }" />
					</button>

					<!-- Files Filter -->
					<button
						:class="[
              'w-full px-3 py-2 rounded-md text-sm border flex items-center justify-between',
              isFilterActive('storedFiles.id') ? 'bg-amber-900 text-white border-amber-700' : 'bg-white text-gray-700 border-gray-300'
            ]"
						@click="toggleExistsFilter('storedFiles.id')"
					>
						<div class="flex items-center gap-2">
							<FilesIcon class="h-4 w-4" />
							<span>Files</span>
						</div>
						<FaSolidCheck class="h-5 w-5" :class="{ 'opacity-0': !isFilterActive('storedFiles.id') }" />
					</button>

					<!-- JSON Content Filter -->
					<button
						:class="[
              'w-full px-3 py-2 rounded-md text-sm border flex items-center justify-between',
              isFilterActive('json_content') ? 'bg-purple-700 text-white border-purple-600' : 'bg-white text-gray-700 border-gray-300'
            ]"
						@click="toggleExistsFilter('json_content')"
					>
						<div class="flex items-center gap-2">
							<JsonIcon class="h-4 w-4" />
							<span>JSON Content</span>
						</div>
						<FaSolidCheck class="h-5 w-5" :class="{ 'opacity-0': !isFilterActive('json_content') }" />
					</button>

					<!-- Meta Filter -->
					<button
						:class="[
              'w-full px-3 py-2 rounded-md text-sm border flex items-center justify-between',
              isFilterActive('meta') ? 'bg-slate-500 text-white border-slate-400' : 'bg-white text-gray-700 border-gray-300'
            ]"
						@click="toggleExistsFilter('meta')"
					>
						<div class="flex items-center gap-2">
							<MetaIcon class="h-4 w-4" />
							<span>Meta Data</span>
						</div>
						<FaSolidCheck class="h-5 w-5" :class="{ 'opacity-0': !isFilterActive('meta') }" />
					</button>
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
	</div>
</template>

<script setup lang="ts">
import {
	FaSolidBarcode as MetaIcon,
	FaSolidCheck,
	FaSolidDatabase as JsonIcon,
	FaSolidFile as FilesIcon,
	FaSolidFilter,
	FaSolidT as TextIcon
} from "danx-icon";
import { AnyObject } from "quasar-ui-danx";
import { computed, ref, watch } from "vue";

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

// Initialize filters based on modelValue
function initializeFilters() {
	textSearchValue.value = "";

	// If text_content filter has an operator 'contains' value, use it for the text search
	if (props.modelValue?.text_content?.operator === "contains") {
		textSearchValue.value = props.modelValue.text_content.value || "";
	}
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

// Toggle exists filter for field
function toggleExistsFilter(field: string) {
	const newFilters = { ...props.modelValue };

	if (isFilterActive(field)) {
		// If already active, remove the filter
		delete newFilters[field];
	} else {
		// Otherwise add the exists filter
		newFilters[field] = { operator: "exists", value: true };
	}

	emit("update:modelValue", newFilters);
}

// Reset all filters
function resetFilters() {
	emit("update:modelValue", {});
	textSearchValue.value = "";
}
</script>
