<template>
	<QBtn
		:loading="saving"
		:disable="saving"
		class="shadow-none"
		:class="$props.class"
	>
		<TrashIcon class="w-3" />
	</QBtn>
</template>
<script setup lang="ts">
import { FaSolidTrash as TrashIcon } from "danx-icon";

defineProps<{
	saving?: boolean;
	class?: "hover:bg-red-900"
}>();
</script>
