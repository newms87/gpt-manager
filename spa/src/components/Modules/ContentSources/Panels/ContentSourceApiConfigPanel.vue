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
			:model-value="input.config"
			format="json"
			@update:model-value="onUpdateConfig"
		/>
		<QBtn
			v-if="dirtyConfig"
			:loading="updateAction.isApplying"
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
import { NumberField, SelectField } from "quasar-ui-danx";
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
		list_uri: cfg?.list_uri || "",
		per_page: cfg?.per_page || 1000,
		use_offset: cfg?.use_offset || false,
		rate_limits: cfg?.rate_limits || [],
		headers: cfg?.headers || {},
		timestamp_format: cfg?.timestamp_format || "Y-m-d H:i:s",
		min_timestamp: cfg?.min_timestamp || "2020-01-01",
		fields: {
			total: cfg?.fields?.total || "total",
			per_page: cfg?.fields?.per_page || "per_page",
			page: cfg?.fields?.page || "page",
			offset: cfg?.fields?.offset || "offset",
			timestamp: cfg?.fields?.timestamp || "timestamp",
			items: cfg?.fields?.items || "items",
			item_id: cfg?.fields?.item_id || "id",
			item_name: cfg?.fields?.item_name || "name",
			item_date: cfg?.fields?.item_date || "date"
		}
	};
}

// a temporary placeholder for the config values changed, until the user has chosen to save them
const dirtyConfig = ref<object | null>(null);

function onUpdate() {
	updateAction.trigger(props.contentSource, input.value);
}

async function onUpdateConfig(value) {
	// Only set the input value if it is valid JSON, ignore non-recognized fields
	if (value) {
		dirtyConfig.value = mapConfig(value);
	}
}

async function saveConfig() {
	input.value.config = dirtyConfig.value;
	await updateAction.trigger(props.contentSource, input.value);
	dirtyConfig.value = null;
}
</script>
