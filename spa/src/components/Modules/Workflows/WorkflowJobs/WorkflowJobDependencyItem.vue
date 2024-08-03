<template>
	<div class="rounded-lg bg-sky-950 text-slate-300 overflow-hidden">
		<div class="flex items-center flex-nowrap">
			<div class="text-base font-bold flex-grow px-2 text-no-wrap text-ellipsis">{{ dependency.depends_on_name }}</div>
			<ShowHideButton
				v-model="isEditing"
				label="Configure"
				:hide-icon="HideConfigureIcon"
				:show-icon="ShowConfigureIcon"
			/>
			<ActionButton :saving="saving" type="trash" class="p-4" @click="$emit('remove')" />
		</div>
		<div v-if="isEditing" class="mb-4">
			<QSeparator class="bg-slate-500 mb-4" />
			<div class="px-4">
				<ListTransition>
					<div class="flex items-center flex-nowrap">
						<div class="mr-2 w-16">Fields:</div>
						<div class="flex-grow">
							<QToggle
								v-model="forceSchema"
								label="Force Schema"
								@update:model-value="$emit('update', {...dependency, force_schema: forceSchema})"
							/>
						</div>
					</div>
					<div v-if="forceSchema" class="flex items-center flex-nowrap">
						<div class="mr-2 w-16">Include:</div>
						<div class="flex-grow">
							<SelectField
								v-model="includeFields"
								class="mt-4"
								:options="dependency.depends_on_fields"
								clearable
								multiple
								placeholder="(All Data)"
								@update="$emit('update', {...dependency, include_fields: includeFields})"
							/>
						</div>
					</div>
					<div class="flex items-center flex-nowrap mt-4">
						<div class="mr-2 w-16">Group By:</div>
						<SelectField
							v-model="groupBy"
							class="flex-grow"
							:options="dependency.depends_on_fields"
							clearable
							multiple
							placeholder="(No Grouping)"
							@update="$emit('update', {...dependency, group_by: groupBy})"
						/>
					</div>
					<div class="flex items-center flex-nowrap mt-4">
						<div class="mr-2 w-16">Order By:</div>
						<SelectField
							v-model="orderBy"
							class="flex-grow"
							:options="dependency.depends_on_fields"
							clearable
							placeholder="(Default)"
							@update="onUpdateOrderBy"
						/>
						<SelectField
							v-model="orderDirection"
							class="w-24 ml-4"
							:disable="!orderBy"
							:options="['asc', 'desc']"
							placeholder="(Default)"
							@update="onUpdateOrderBy"
						/>
					</div>
				</ListTransition>
			</div>
		</div>
	</div>
</template>
<script setup lang="ts">
import { ShowHideButton } from "@/components/Shared";
import ActionButton from "@/components/Shared/Buttons/ActionButton";
import { WorkflowJobDependency } from "@/types/workflows";
import { FaSolidScrewdriverWrench as HideConfigureIcon, FaSolidWrench as ShowConfigureIcon } from "danx-icon";
import { ListTransition, SelectField } from "quasar-ui-danx";
import { ref } from "vue";

const emit = defineEmits(["update", "remove"]);
const props = defineProps<{
	dependency: WorkflowJobDependency;
	saving?: boolean;
}>();

const isEditing = ref(false);
const forceSchema = ref(props.dependency.force_schema);
const includeFields = ref(props.dependency.include_fields || []);
const groupBy = ref(props.dependency.group_by || []);
const orderBy = ref(props.dependency.order_by ? props.dependency.order_by.name : "");
const orderDirection = ref(props.dependency.order_by ? props.dependency.order_by.direction : "asc");

function onUpdateOrderBy() {
	emit("update", {
		...props.dependency,
		order_by: orderBy.value ? { name: orderBy.value, direction: orderDirection.value } : null
	});
}
</script>
