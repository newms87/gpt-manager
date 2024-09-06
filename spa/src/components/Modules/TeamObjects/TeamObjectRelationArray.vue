<template>
	<div :data-testid="name">
		<ShowHideButton
			v-model="isShowing"
			:label="(title || name) + ' ' + relations.length"
			class="py-2 px-6 bg-sky-900"
		/>
	</div>
</template>
<script setup lang="ts">
import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { ShowHideButton } from "quasar-ui-danx";
import { ref } from "vue";

defineProps<{ name: string, title?: string, relations: TeamObject[] }>();

const isShowing = ref(false);
</script>
