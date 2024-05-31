<template>
	<div class="p-6">
		<TextField v-model="input.name" label="Name" required :max-length="40" @update:model-value="onUpdate" />
		<TextField
			v-model="input.url"
			label="URL"
			required
			:max-length="2048"
			class="mt-4"
			@update:model-value="onUpdate"
		/>
		<TextField
			v-model="input.polling_interval"
			label="Polling Interval (in minutes)"
			required
			type="number"
			:min="1"
			:max="60*60*24*365"
			class="mt-4"
			@update:model-value="onUpdate"
		/>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/ContentSources/contentSourceActions";
import { ContentSource } from "@/types";
import { TextField } from "quasar-ui-danx";
import { ref } from "vue";

const props = defineProps<{
	contentSource: ContentSource,
}>();

const updateAction = getAction("update-debounced");
const input = ref({
	name: props.contentSource.name,
	url: props.contentSource.url,
	polling_interval: props.contentSource.polling_interval
});

function onUpdate() {
	updateAction.trigger(props.contentSource, input.value);
}
</script>
