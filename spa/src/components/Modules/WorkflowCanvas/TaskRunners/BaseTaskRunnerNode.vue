<template>
	<div class="flex items-center justify-center flex-nowrap">
		<slot>
			<BaseNodeIcon class="w-[4.4rem]" />
		</slot>
	</div>
</template>
<script setup lang="ts">
import { FaSolidSquareShareNodes as BaseNodeIcon } from "danx-icon";
import { AnyObject } from "quasar-ui-danx";

defineProps<{
	config?: AnyObject;
}>();
</script>
