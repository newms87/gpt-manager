<template>
	<SelectionMenuField
		v-model:selected="selectedTeam"
		size="xs"
		label-class="text-sky-400"
		:selectable="authTeamList.length > 1"
		:options="authTeamList"
	/>
</template>
<script setup lang="ts">
import { authTeam, authTeamList } from "@/helpers";
import { AuthTeam } from "@/types";
import { SelectionMenuField } from "quasar-ui-danx";
import { onMounted } from "vue";

const selectedTeam = defineModel<AuthTeam | null>();

onMounted(() => {
	// If no selected team has been set yet, try to resolve from the authenticated team list
	if (authTeamList.value.length > 0 && !selectedTeam.value) {
		// If there is last login authenticated team (probably stored in local storage), use that team
		if (authTeam.value) {
			// validate the team exists in the list
			const team = authTeamList.value.find(team => team.id === authTeam.value.id);
			if (team) {
				selectedTeam.value = team;
			}
		}

		// Otherwise use the first team in the list
		selectedTeam.value = authTeamList.value[0];
	}
});
</script>
