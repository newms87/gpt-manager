<template>
	<TextField
		v-model="searchText"
		:placeholder="placeholder"
		input-class="!py-1"
	>
		<template #prepend>
			<SearchIcon class="w-4" />
		</template>
	</TextField>
</template>

<script setup lang="ts">
import { FaSolidMagnifyingGlass as SearchIcon } from "danx-icon";
import { TextField } from "quasar-ui-danx";
// Define props with defaults
withDefaults(defineProps<{
	placeholder?: string;
	clearable?: boolean;
}>(), {
	placeholder: "Search...",
	clearable: true
});

// Define model with string type
const searchText = defineModel<string>();
</script>
