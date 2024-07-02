<template>
	<QBtn class="py-1 px-2" @click="onToggle">
		<div class="flex items-center flex-nowrap whitespace-nowrap">
			<slot :is-showing="isShowing">
				<component :is="isShowing ? (hideIcon || DefaultHideIcon) : (showIcon || DefaultShowIcon)" :class="iconClass" />
				<div v-if="label" :class="labelClass">{{ (isShowing ? hideLabel : showLabel) || label }}</div>
			</slot>
		</div>
		<QTooltip v-if="tooltip">{{ tooltip }}</QTooltip>
	</QBtn>
</template>
<script lang="ts" setup>
import { FaSolidEye as DefaultShowIcon, FaSolidEyeSlash as DefaultHideIcon } from "danx-icon";
import { getItem, setItem } from "quasar-ui-danx";
import { nextTick } from "vue";

export interface Props {
	name?: string;
	showLabel?: string;
	hideLabel?: string;
	showIcon?: object | string;
	hideIcon?: object | string;
	iconClass?: string;
	labelClass?: string;
	label?: string;
	tooltip?: string;
}

const isShowing = defineModel<boolean>();
const props = withDefaults(defineProps<Props>(), {
	name: "",
	showLabel: "",
	hideLabel: "",
	showIcon: null,
	hideIcon: null,
	iconClass: "w-4 h-6",
	labelClass: "ml-2",
	label: "",
	tooltip: ""
});

const SETTINGS_KEY = "show-hide-button";
const settings = getItem(SETTINGS_KEY, {});

if (props.name) {
	if (settings[props.name] !== undefined) {
		isShowing.value = settings[props.name];
	}
}

function onToggle() {
	isShowing.value = !isShowing.value;

	if (props.name) {
		// NOTE: use nextTick to ensure the value is updated before saving (if the parent does not pass a value for modelValue, this can cause a desync)
		nextTick(() => {
			settings[props.name] = isShowing.value;
			setItem(SETTINGS_KEY, { ...getItem(SETTINGS_KEY, {}), [props.name]: isShowing.value });
		});
	}
}

</script>
