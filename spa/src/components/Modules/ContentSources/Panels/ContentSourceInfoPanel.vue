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
		<NumberField
			v-model="input.polling_interval"
			label="Polling Interval (in minutes)"
			placeholder="Enter Interval..."
			required
			:min="1"
			:max="60*60*24*365"
			prepend-label
			class="mt-4"
			@update:model-value="onUpdate"
		/>

		<div class="mt-8">
			<TextField
				v-model="input.last_checkpoint"
				label="Last Checkpoint"
				placeholder="N/A"
				@update:model-value="onUpdate"
			/>
		</div>
		<div class="mt-8 flex items-center space-x-2">
			<div class="bg-slate-700 p-3 rounded flex-grow">Last Fetch: {{ fDateTime(contentSource.fetched_at) }}</div>
			<QBtn
				class="bg-sky-800 text-slate-300 w-48 py-2.5"
				:disable="fetchAction.isApplying"
				:loading="fetchAction.isApplying"
				@click="fetchAction.trigger(contentSource)"
			>
				Run Now
			</QBtn>
		</div>
	</div>
</template>
<script setup lang="ts">
import { getAction } from "@/components/Modules/ContentSources/contentSourceActions";
import { ContentSource } from "@/types";
import { fDateTime, NumberField, TextField } from "quasar-ui-danx";
import { ref } from "vue";

const props = defineProps<{
	contentSource: ContentSource,
}>();

const updateAction = getAction("update-debounced");
const fetchAction = getAction("fetch");
const input = ref({
	name: props.contentSource.name,
	url: props.contentSource.url,
	last_checkpoint: props.contentSource.last_checkpoint,
	polling_interval: props.contentSource.polling_interval
});

function onUpdate() {
	updateAction.trigger(props.contentSource, input.value);
}
</script>
