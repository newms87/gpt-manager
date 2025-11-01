<template>
	<!-- If this is a job entry, render the job component instead of parsing message parts -->
	<JobLogEntry
		v-if="logLine.jobEntry"
		:job-entry="logLine.jobEntry"
	/>
	<span
		v-else
		class="inline-flex items-center gap-1 flex-wrap"
	>
		<template v-for="(part, index) in messageParts" :key="index">
			<EmbeddedObjectLink
				v-if="part.type === 'object'"
				:object="part.object"
			/>
			<SquareBracketEntity
				v-else-if="part.type === 'squareBracket'"
				:entity="part.entity"
				@filter="$emit('filter', $event)"
			/>
			<LockOperationIcon
				v-else-if="part.type === 'lockOperation'"
				:operation="part.operation"
			/>
			<span v-else class="whitespace-pre-wrap">{{ part.text }}</span>
		</template>
	</span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import EmbeddedObjectLink from './EmbeddedObjectLink.vue';
import SquareBracketEntity from './SquareBracketEntity.vue';
import LockOperationIcon from './LockOperationIcon.vue';
import JobLogEntry from './JobLogEntry.vue';
import type { ParsedLogLine, EmbeddedObject, SquareBracketEntity as SBEntity, LockOperation } from './useLogParser';

const props = defineProps<{
	logLine: ParsedLogLine;
}>();

defineEmits<{
	filter: [content: string];
}>();

interface MessagePart {
	type: 'text' | 'object' | 'squareBracket' | 'lockOperation';
	text?: string;
	object?: EmbeddedObject;
	entity?: SBEntity;
	operation?: LockOperation;
}

interface SpecialElement {
	type: 'object' | 'squareBracket' | 'lockOperation';
	startIndex: number;
	endIndex: number;
	data: EmbeddedObject | SBEntity | LockOperation;
}

const messageParts = computed<MessagePart[]>(() => {
	const { message, embeddedObjects, squareBracketEntities, lockOperations } = props.logLine;

	// Combine all special elements into a single sorted array
	const specialElements: SpecialElement[] = [
		...embeddedObjects.map(obj => ({
			type: 'object' as const,
			startIndex: obj.startIndex,
			endIndex: obj.endIndex,
			data: obj
		})),
		...squareBracketEntities.map(entity => ({
			type: 'squareBracket' as const,
			startIndex: entity.startIndex,
			endIndex: entity.endIndex,
			data: entity
		})),
		...lockOperations.map(op => ({
			type: 'lockOperation' as const,
			startIndex: op.startIndex,
			endIndex: op.endIndex + 1, // Include the colon
			data: op
		}))
	];

	// Sort by start index
	specialElements.sort((a, b) => a.startIndex - b.startIndex);

	if (specialElements.length === 0) {
		return [{ type: 'text', text: message }];
	}

	const parts: MessagePart[] = [];
	let currentIndex = 0;

	specialElements.forEach((element) => {
		// Add text before the element
		if (element.startIndex > currentIndex) {
			const textBefore = message.substring(currentIndex, element.startIndex);
			if (textBefore) {
				parts.push({ type: 'text', text: textBefore });
			}
		}

		// Add the special element
		switch (element.type) {
			case 'object':
				parts.push({ type: 'object', object: element.data as EmbeddedObject });
				break;
			case 'squareBracket':
				parts.push({ type: 'squareBracket', entity: element.data as SBEntity });
				break;
			case 'lockOperation':
				parts.push({ type: 'lockOperation', operation: element.data as LockOperation });
				break;
		}

		currentIndex = element.endIndex;
	});

	// Add any remaining text after the last element
	if (currentIndex < message.length) {
		const textAfter = message.substring(currentIndex);
		if (textAfter) {
			parts.push({ type: 'text', text: textAfter });
		}
	}

	return parts;
});
</script>
