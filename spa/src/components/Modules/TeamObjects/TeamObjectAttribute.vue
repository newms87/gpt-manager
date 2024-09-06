<template>
	<div :data-testid="name" class="flex items-start flex-nowrap team-object-attribute">
		<TeamObjectAttributeBlock
			:label="label || title || name"
			:format="format"
			:attribute="attribute"
		/>
		<div class="-mt-1 ml-1 edit-attribute">
			<QBtn
				class="show-on-hover"
				:loading="editAction.isApplying && object.isSaving"
				@click="editAction.trigger(object, {name, title, ...attribute})"
			>
				<EditIcon class="w-3" />
			</QBtn>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTeamObject } from "@/components/Modules/TeamObjects/config";
import { TeamObjectAttributeProps } from "@/components/Modules/TeamObjects/team-objects";
import TeamObjectAttributeBlock from "@/components/Modules/Tortguard/TeamObjectAttributeBlock";
import { FaSolidPencil as EditIcon } from "danx-icon";

defineProps<TeamObjectAttributeProps>();

const editAction = dxTeamObject.getAction("edit-attribute");
</script>

<style scoped lang="scss">
.team-object-attribute {
	.edit-attribute {
		opacity: 0;
		transition: opacity 0.3s;
	}

	&:hover {
		.edit-attribute {
			opacity: 1;
		}
	}
}
</style>
