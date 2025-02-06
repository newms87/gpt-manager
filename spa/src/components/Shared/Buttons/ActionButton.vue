<template>
	<QBtn
		:loading="isSaving"
		class="shadow-none"
		:class="disabled ? 'text-slate-800 bg-slate-500 opacity-50' : colorClass"
		:disable="disabled"
		@click="onAction"
	>
		<div class="flex items-center flex-nowrap">
			<component
				:is="icon || typeOptions.icon"
				class="transition-all"
				:class="iconClass + ' ' + typeOptions.iconClass"
			/>
			<slot>
				<div v-if="label" class="ml-2">{{ label }}</div>
			</slot>
		</div>
		<QTooltip v-if="tooltip" class="whitespace-nowrap">{{ tooltip }}</QTooltip>
	</QBtn>
</template>
<script setup lang="ts">
import {
	FaSolidArrowsRotate as RefreshIcon,
	FaSolidCopy as CopyIcon,
	FaSolidPause as PauseIcon,
	FaSolidPencil as EditIcon,
	FaSolidPlay as PlayIcon,
	FaSolidPlus as CreateIcon,
	FaSolidStop as StopIcon,
	FaSolidTrash as TrashIcon
} from "danx-icon";
import { ActionTarget, ResourceAction } from "quasar-ui-danx";
import { computed } from "vue";

export interface ActionButtonProps {
	type?: "trash" | "trash-red" | "create" | "edit" | "copy" | "play" | "stop" | "pause" | "refresh";
	color?: "red" | "blue" | "sky" | "green" | "green-invert" | "lime" | "white" | "gray";
	icon?: object | string;
	iconClass?: string;
	label?: string;
	tooltip?: string;
	saving?: boolean;
	action?: ResourceAction;
	target?: ActionTarget;
	input?: object;
	disabled?: boolean;
}

const emit = defineEmits(["success", "error", "always"]);
const props = withDefaults(defineProps<ActionButtonProps>(), {
	type: null,
	color: null,
	icon: null,
	iconClass: "",
	label: "",
	tooltip: "",
	action: null,
	target: null,
	input: null
});

const colorClass = computed(() => {
	switch (props.color) {
		case "red":
			return "text-red-900 bg-red-300 hover:bg-red-400";
		case "lime":
			return "text-lime-900 bg-lime-300 hover:bg-lime-400";
		case "green":
			return "text-green-900 bg-green-300 hover:bg-green-400";
		case "green-invert":
			return "text-lime-800 bg-green-200 hover:bg-lime-800 hover:text-green-200";
		case "blue":
			return "text-blue-900 bg-blue-300 hover:bg-blue-400";
		case "sky":
			return "text-sky-900 bg-sky-300 hover:bg-sky-400";
		case "white":
			return "text-white bg-gray-800 hover:bg-gray-200";
		case "gray":
			return "text-slate-200 bg-slate-800 hover:bg-slate-900";
		default:
			return "text-slate-200 hover:bg-slate-800";
	}
});
const typeOptions = computed(() => {
	switch (props.type) {
		case "trash":
			return {
				icon: TrashIcon,
				iconClass: "w-3"
			};
		case "create":
			return {
				icon: CreateIcon,
				iconClass: "w-3"
			};
		case "edit":
			return {
				icon: EditIcon,
				iconClass: "w-3"
			};
		case "copy":
			return {
				icon: CopyIcon,
				iconClass: "w-3"
			};
		case "play":
			return {
				icon: PlayIcon,
				iconClass: "w-3"
			};
		case "stop":
			return {
				icon: StopIcon,
				iconClass: "w-3"
			};
		case "pause":
			return {
				icon: PauseIcon,
				iconClass: "w-3"
			};
		case "refresh":
			return {
				icon: RefreshIcon,
				iconClass: "w-4"
			};
		default:
			return {
				icon: EditIcon,
				iconClass: "w-3"
			};
	}
});

const isSaving = computed(() => {
	if (props.saving) return true;
	if (props.action) {
		return props.action.isApplying;
	}
	if (props.target) {
		if (Array.isArray(props.target)) {
			return props.target.some((t) => t.isSaving);
		}
		return props.target.isSaving;
	}
	return false;
});

function onAction() {
	if (props.disabled) return;
	if (props.action) {
		props.action.trigger(props.target, props.input).then(async (response) => {
			emit("success", typeof response.json === "function" ? await response.json() : response);
		}).catch((e) => {
			console.error(`Action emitted an error: ${props.action.name}`, e, props.target);
			emit("error", e);
		}).finally(() => {
			emit("always");
		});
	}
}
</script>
