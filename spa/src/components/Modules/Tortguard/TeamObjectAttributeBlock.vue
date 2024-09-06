<template>
	<LabelValueBlock :label="label || attribute.name">
		<template #label>
			<div class="flex items-center flex-nowrap">
				{{ label || attribute.name }}
				<div class="ml-1">
					<HighConfidenceIcon v-if="attribute?.confidence === 'High'" class="text-green-600 w-3" />
					<MediumConfidenceIcon v-else-if="attribute?.confidence === 'Medium'" class="text-amber-400 w-3" />
					<LowConfidenceIcon v-else-if="attribute?.confidence === 'Low'" class="text-red-400 w-3" />
					<NoConfidenceIcon v-else class="text-slate-500 w-3" />
					<QTooltip>
						{{ attribute?.confidence ? attribute.confidence + " Confidence" : "No Data Found" }}
					</QTooltip>
				</div>
			</div>
		</template>
		<template v-if="Array.isArray(resolvedValue)">
			<ul class="list-disc list-inside">
				<li v-for="value in resolvedValue" :key="value">{{ value }}</li>
			</ul>
		</template>
		<template v-else>
			{{ resolvedValue }}
		</template>
		<div
			v-if="attribute?.source || attribute?.sourceMessages?.length"
			class="inline-block ml-2"
		>
			<LinkIcon class="w-4 cursor-pointer text-sky-500" />
			<QMenu class="p-4 mt-4 bg-slate-600">
				<div class="flex flex-nowrap">
					<div class="flex-grow">
						<a v-if="attribute.source" :href="attribute.source.url" target="_blank">{{ attribute.source.url }}</a>
					</div>
					<div class="ml-4">
						<a :href="attribute.thread_url" target="_blank">
							<ThreadLinkIcon class="w-4" />
						</a>
					</div>
				</div>
				<div v-if="attribute.description" class="my-4 px-6 p-2 bg-slate-900 text-slate-400 rounded-full text-base">
					{{ attribute.description }}
				</div>
				<div v-if="attribute.sourceMessages?.length" class="mt-4 space-y-4">
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
import { TeamObjectAttributeBlockProps } from "@/components/Modules/TeamObjects/team-objects";
import {
	FaBrandsThreads as ThreadLinkIcon,
	FaSolidAngleDown as LowConfidenceIcon,
	FaSolidAnglesDown as NoConfidenceIcon,
	FaSolidAnglesUp as HighConfidenceIcon,
	FaSolidAngleUp as MediumConfidenceIcon,
	FaSolidLink as LinkIcon
} from "danx-icon";
import { fBoolean, fDate, fNumber, fShortCurrency, LabelValueBlock } from "quasar-ui-danx";
import { computed } from "vue";

const props = defineProps<TeamObjectAttributeBlockProps>();

const resolvedValue = computed(() => {
	const value = props.attribute?.value;
	if (value === undefined) {
		return "-";
	}

	switch (props.format) {
		case "boolean":
			return fBoolean(value);

		case "number":
			return fNumber(+value);

		case "shortCurrency":
			return fShortCurrency(+value);

		case "date":
			return fDate(value as string);

		case "list":
			return (value as string[]).join(", ");

		default:
			return value;
	}
});
</script>
