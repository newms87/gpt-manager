<template>
	<div class="p-6 flex flex-col items-stretch flex-nowrap">
		<SelectField v-model="input.config.method" :options="methodOptions" @update="onUpdate" />
		<NumberField
			v-model="input.config.per_page"
			:min="1"
			:max="999999"
			label="Per Page"
			class="mt-4"
			prepend-label
			@update="onUpdate"
		/>
		<MarkdownEditor
			class="mt-4"
			sync-model-changes
			:model-value="fMarkdownJSON(input.config)"
			@update:model-value="onUpdateConfig"
		/>
		<QBtn
			v-if="dirtyConfig"
			:loading="updateAction.isApplying"
			:disable="updateAction.isApplying"
			class="w-full bg-sky-800"
			@click="saveConfig"
		>Save Config
		</QBtn>
	</div>
</template>
<script setup lang="ts">
import MarkdownEditor from "@/components/MardownEditor/MarkdownEditor";
import { getAction } from "@/components/Modules/ContentSources/contentSourceActions";
import { ContentSource } from "@/types";
import { fMarkdownJSON, NumberField, SelectField } from "quasar-ui-danx";
import { ref, watch } from "vue";

const props = defineProps<{
	contentSource: ContentSource,
}>();

const updateAction = getAction("update");

const input = ref({
	config: mapConfig(props.contentSource.config)
});

watch(() => props.contentSource.config, (value) => {
	input.value.config = mapConfig(value);
});

const methodOptions = [
	{ label: "GET", value: "GET" },
	{ label: "POST", value: "POST" },
	{ label: "PUT", value: "PUT" },
	{ label: "PATCH", value: "PATCH" }
];

function mapConfig(cfg) {
	return {
		method: cfg?.method || "GET",
		per_page: cfg?.per_page || 1000,
		rateLimits: cfg?.rateLimits || [],
		headers: cfg?.headers || {},
		fields: {
			total: cfg?.fields?.total || "total",
			per_page: cfg?.fields?.per_page || "per_page",
			page: cfg?.fields?.page || "page",
			timestamp: cfg?.fields?.timestamp || "timestamp"
		}
	};
}

// a temporary placeholder for the config values changed, until the user has chosen to save them
const dirtyConfig = ref<object | null>(null);

function onUpdate() {
	updateAction.trigger(props.contentSource, input.value);
}

async function onUpdateConfig(value) {
	try {
		value = JSON.parse(value.replace("```json\n", "").replace("\n```", ""));

		// Only set the input value if it is valid JSON, ignore non-recognized fields
		if (value) {
			dirtyConfig.value = mapConfig(value);
		}
	} catch (e) {
		console.log("invalid JSON", e);
		// Fail silently
	}
}

async function saveConfig() {
	input.value.config = dirtyConfig.value;
	await updateAction.trigger(props.contentSource, input.value);
	dirtyConfig.value = null;
}
</script>
