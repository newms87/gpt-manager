<template>
	<QBtn class="py-1 px-2 flex items-center flex-nowrap" @click="isShowing = !isShowing">
		<template v-if="isShowing">
			<HideIcon class="w-4 mr-2" />
			{{ hideLabel || label }}
		</template>
		<template v-else>
			<ShowIcon class="w-4 mr-2" />
			{{ viewLabel || label }}
		</template>
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
