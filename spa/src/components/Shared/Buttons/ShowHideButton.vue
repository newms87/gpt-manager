<template>
	<QBtn class="py-1 px-2" @click="isShowing = !isShowing">
		<div class="flex items-center flex-nowrap whitespace-nowrap">
			<slot :is-showing="isShowing">
				<template v-if="isShowing">
					<HideIcon class="w-4 mr-2" />
					<div>{{ hideLabel || label }}</div>
				</template>
				<template v-else>
					<ShowIcon class="w-4 mr-2" />
					<div>{{ viewLabel || label }}</div>
				</template>
			</slot>
		</div>
	</QBtn>
</template>
<script lang="ts" setup>
import { FaSolidEye as ShowIcon, FaSolidEyeSlash as HideIcon } from "danx-icon";

export interface Props {
	viewLabel?: string;
	hideLabel?: string;
	label?: string;
}

const isShowing = defineModel<boolean>();
withDefaults(defineProps<Props>(), {
	viewLabel: "",
	hideLabel: "",
	label: "Show/Hide"
});
</script>
