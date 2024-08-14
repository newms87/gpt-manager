<template>
	<LabelValueBlock :label="label">
		{{ resolvedValue }}
		<div
			v-if="attribute?.source || attribute?.sourceMessages"
			class="inline-block"
		>
			<LinkIcon class="w-4 cursor-pointer text-sky-500" />
			<QMenu class="p-4 mt-4 bg-slate-600">
				<div>
					<a v-if="attribute.source" :href="attribute.source.url" target="_blank">{{ attribute.source.url }}</a>
				</div>
				<div v-if="attribute.sourceMessages?.length" class="mt-4">
					<ThreadMessageCard
						v-for="message in attribute.sourceMessages" :key="message.id" readonly
						:message="message"
					/>
				</div>
			</QMenu>
		</div>
	</LabelValueBlock>
</template>
<script setup lang="ts">
import ThreadMessageCard from "@/components/Modules/Agents/Threads/ThreadMessageCard";
import { TeamObjectAttribute } from "@/components/Modules/Tortguard/tortguard";
import { FaSolidLink as LinkIcon } from "danx-icon";
import { fBoolean, fNumber, LabelValueBlock } from "quasar-ui-danx";
import { computed } from "vue";

const props = defineProps<{
	label: string;
	attribute?: TeamObjectAttribute;
}>();

const resolvedValue = computed(() => {
	const value = props.attribute?.value;
	if (value === undefined) {
		return "-";
	}

	switch (typeof value) {
		case "boolean":
			return fBoolean(value);

		case "number":
			return fNumber(value);

		default:
			return value;
	}
});
</script>
