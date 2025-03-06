<template>
	<LabelPillWidget
		:color="count > 0 ? activeColor : 'gray'"
		size="xs"
		class="flex items-center flex-nowrap flex-shrink-1"
		:class="{'cursor-pointer': !disabled, 'cursor-not-allowed': disabled}"
		@click="onShow"
	>
		{{ count || 0 }}
		<ArtifactIcon class="w-4 ml-2" />
		<QMenu v-if="isShowing" :model-value="true" @close="isShowing = false">
			<ArtifactList :artifacts="artifacts" class="p-4 w-[60rem]" />
		</QMenu>
	</LabelPillWidget>
</template>
<script setup lang="ts">
import ArtifactList from "@/components/Modules/Artifacts/ArtifactList";
import { Artifact } from "@/types";
import { FaSolidTruckArrowRight as ArtifactIcon } from "danx-icon";
import { LabelPillWidget, LabelPillWidgetProps } from "quasar-ui-danx";
import { ref } from "vue";

const emit = defineEmits(["show"]);
const props = withDefaults(defineProps<{
	count?: number;
	activeColor?: LabelPillWidgetProps["color"];
	artifacts?: Artifact[];
	disabled?: boolean;
}>(), {
	count: 0,
	activeColor: "sky",
	artifacts: null
});

const isShowing = ref(false);
function onShow() {
	if (props.disabled) return;
	isShowing.value = true;
	emit("show");
}
</script>
