<template>
	<div class="flex items-start flex-nowrap team-object-attribute">
		<TeamObjectAttributeBlock
			:label="label || title || name"
			:format="format"
			:attribute="editableAttribute as TeamObjectAttribute"
		/>
		<div class="-mt-1 ml-1 edit-attribute">
			<QBtn
				:loading="editAction.isApplying && object.isSaving"
				@click="editAction.trigger(object, {name, title, attribute: editableAttribute})"
			>
				<EditIcon class="w-3" />
			</QBtn>
		</div>
	</div>
</template>
<script setup lang="ts">
import { dxTeamObject } from "@/components/Modules/TeamObjects/config";
import { TeamObjectAttribute, TeamObjectAttributeProps } from "@/components/Modules/TeamObjects/team-objects";
import TeamObjectAttributeBlock from "@/components/Modules/TeamObjects/TeamObjectAttributeBlock";
import { FaSolidPencil as EditIcon } from "danx-icon";
import { shallowRef } from "vue";

const props = defineProps<TeamObjectAttributeProps>();

const editAction = dxTeamObject.getAction("edit-attribute");
const editableAttribute = shallowRef(props.attribute as TeamObjectAttribute);

editAction.onSuccess = (result) => {
	editableAttribute.value = result.result;
};
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
