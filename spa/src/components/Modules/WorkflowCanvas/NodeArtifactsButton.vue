<template>
	<LabelPillWidget
		:color="count > 0 ? activeColor : 'gray'"
		size="xs"
		class="node-artifacts-button flex items-center justify-center flex-nowrap flex-shrink-1"
		:class="{'cursor-pointer': !disabled, 'cursor-not-allowed': disabled}"
		@click="onShow"
	>
		{{ count || 0 }}
		<ArtifactIcon class="min-w-4 w-4 ml-2" />
	</LabelPillWidget>
</template>
<script setup lang="ts">
import { FaSolidBoxArchive as ArtifactIcon } from "danx-icon";
import { LabelPillWidget, LabelPillWidgetProps } from "quasar-ui-danx";

const emit = defineEmits(["show"]);
const props = withDefaults(defineProps<{
	count?: number;
	activeColor?: LabelPillWidgetProps["color"];
	disabled?: boolean;
}>(), {
	count: 0,
	activeColor: "sky"
});

function onShow() {
	if (props.disabled) return;
	emit("show");
}
</script>
