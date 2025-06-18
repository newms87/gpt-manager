<template>
	<QDialog v-model="isVisible" class="z-50">
		<QCard class="w-96 bg-slate-800 text-white">
			<QCardSection class="bg-slate-900">
				<div class="text-h6">Merge Team Object</div>
				<div class="text-slate-400 mt-1">
					Select a target object to merge "{{ sourceObject?.name }}" INTO
				</div>
				<div class="text-xs text-orange-400 mt-2">
					⚠️ "{{ sourceObject?.name }}" will be destroyed after merging
				</div>
			</QCardSection>

			<QCardSection class="space-y-3">
				<div v-if="mergeableObjects.length === 0" class="text-slate-400 text-center py-4">
					No other team objects of this type available to merge
				</div>
				<div v-else>
					<div class="text-sm text-slate-400 mb-2">Available target objects:</div>
					<div class="space-y-2 max-h-60 overflow-y-auto">
						<QBtn
							v-for="target in mergeableObjects"
							:key="target.id"
							class="w-full text-left justify-start bg-slate-700 hover:bg-slate-600"
							:disable="isLoading"
							:loading="isLoading"
							@click="handleMerge(target)"
						>
							<div class="p-2">
								<div class="font-semibold">{{ target.name }}</div>
								<div class="text-xs text-slate-400">ID: {{ target.id }}</div>
								<div v-if="target.description" class="text-xs text-slate-300 mt-1">
									{{ target.description.substring(0, 100) }}{{ target.description.length > 100 ? '...' : '' }}
								</div>
							</div>
						</QBtn>
					</div>
				</div>
			</QCardSection>

			<QCardActions align="right" class="bg-slate-900">
				<QBtn flat color="grey" :disable="isLoading" @click="close">Cancel</QBtn>
			</QCardActions>
		</QCard>
	</QDialog>
</template>

<script setup lang="ts">
import { dxTeamObject } from "@/components/Modules/TeamObjects";
import { TeamObject } from "@/components/Modules/TeamObjects/team-objects";
import { computed, ref } from "vue";

const props = defineProps<{
	modelValue: boolean;
	sourceObject: TeamObject | null;
	availableObjects: TeamObject[];
}>();

const emit = defineEmits<{
	'update:modelValue': [value: boolean];
	'merge': [sourceObject: TeamObject, targetObject: TeamObject];
}>();

const isLoading = ref(false);

const isVisible = computed({
	get: () => props.modelValue,
	set: (value) => emit('update:modelValue', value)
});

const mergeableObjects = computed(() => {
	if (!props.sourceObject || !props.availableObjects) return [];
	return props.availableObjects.filter(obj => obj.id !== props.sourceObject?.id);
});

function close() {
	isVisible.value = false;
}

async function handleMerge(targetObject: TeamObject) {
	if (!props.sourceObject || isLoading.value) return;
	
	isLoading.value = true;
	
	try {
		emit('merge', props.sourceObject, targetObject);
	} finally {
		isLoading.value = false;
	}
}
</script>