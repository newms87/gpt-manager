<template>
	<div class="flex-shrink-0 bg-slate-800 p-4 border-b border-slate-700">
		<!-- Filter Banner -->
		<div v-if="isFilteredBySubscription" class="mb-3 p-2 bg-sky-900 border border-sky-700 rounded flex items-center justify-between">
			<div class="flex items-center space-x-2">
				<FilterIcon class="w-4 text-sky-300" />
				<span class="text-sm text-sky-200 font-medium">
					Filtered by Subscription: {{ resourceTypeFilter }}
				</span>
			</div>
			<ActionButton
				type="cancel"
				label="Clear Filters"
				color="sky"
				size="xs"
				@click="$emit('clear-filters')"
			/>
		</div>

		<!-- Filter Controls -->
		<div class="flex items-center space-x-4">
			<div class="flex-1">
				<TextField
					:model-value="searchText"
					placeholder="Search events (searches JSON payload)..."
					size="sm"
					@update:model-value="$emit('update:searchText', $event)"
				>
					<template #prepend>
						<SearchIcon class="w-4 text-slate-400" />
					</template>
				</TextField>
			</div>
			<div class="w-48">
				<SelectField
					:model-value="resourceTypeFilter"
					:options="resourceTypeOptions"
					label="Resource Type"
					size="sm"
					clearable
					@update:model-value="$emit('update:resourceTypeFilter', $event)"
				/>
			</div>
			<div class="w-48">
				<SelectField
					:model-value="eventNameFilter"
					:options="eventNameOptions"
					label="Event Name"
					size="sm"
					clearable
					@update:model-value="$emit('update:eventNameFilter', $event)"
				/>
			</div>
			<ActionButton
				v-if="hasActiveFilters"
				type="cancel"
				label="Reset"
				color="slate"
				size="sm"
				@click="$emit('clear-filters')"
			/>
			<ActionButton
				type="trash"
				label="Clear Logs"
				color="red"
				size="sm"
				@click="$emit('clear-logs')"
			/>
		</div>
		<div class="text-xs text-slate-400 mt-2">
			Showing {{ filteredCount }} of {{ totalCount }} events (max 1000)
		</div>
	</div>
</template>

<script setup lang="ts">
import { FaSolidFilter as FilterIcon, FaSolidMagnifyingGlass as SearchIcon } from "danx-icon";
import { ActionButton, SelectField, TextField } from "quasar-ui-danx";
import { computed } from "vue";

const props = defineProps<{
	searchText: string;
	resourceTypeFilter: string | null;
	eventNameFilter: string | null;
	resourceTypeOptions: string[];
	eventNameOptions: string[];
	filteredCount: number;
	totalCount: number;
	isFilteredBySubscription?: boolean;
}>();

defineEmits<{
	'update:searchText': [value: string];
	'update:resourceTypeFilter': [value: string | null];
	'update:eventNameFilter': [value: string | null];
	'clear-logs': [];
	'clear-filters': [];
}>();

const hasActiveFilters = computed(() => {
	return !!(props.searchText || props.resourceTypeFilter || props.eventNameFilter);
});
</script>
