<template>
	<LabelValueBlock :label="resolvedLabel">
		<template #label>
			<div class="flex items-center flex-nowrap">
				{{ resolvedLabel }}
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
		<TeamObjectAttributeSourcesMenu v-if="attribute" :attribute="attribute" />
	</LabelValueBlock>
</template>
<script setup lang="ts">
import { TeamObjectAttributeBlockProps } from "@/components/Modules/TeamObjects/team-objects";
import TeamObjectAttributeSourcesMenu from "@/components/Modules/Tortguard/TeamObjectAttributeSourcesMenu";
import {
	FaSolidAngleDown as LowConfidenceIcon,
	FaSolidAnglesDown as NoConfidenceIcon,
	FaSolidAnglesUp as HighConfidenceIcon,
	FaSolidAngleUp as MediumConfidenceIcon
} from "danx-icon";
import { fBoolean, fDate, fDateTime, fNumber, fShortCurrency, LabelValueBlock } from "quasar-ui-danx";
import { computed } from "vue";

const props = defineProps<TeamObjectAttributeBlockProps>();

const resolvedLabel = computed(() => props.label || props.attribute?.name);

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

		case "date-time":
			return fDateTime(value as string);

		case "list":
			return (value as string[]).join(", ");

		default:
			return value;
	}
});
</script>
