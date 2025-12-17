<template>
    <InfoDialog
        v-if="visible"
        class="ui-mode"
        title="Event Metadata"
        color="blue"
        :hide-cancel="true"
        :hide-ok="true"
        content-class="w-[80vw] h-[80vh] max-w-none"
        @close="$emit('close')"
    >
        <div class="metadata-modal-container h-full flex flex-col">
            <div class="bg-blue-50 rounded-lg p-4 mb-4 flex-shrink-0">
                <h4 class="text-lg font-semibold text-gray-900 mb-2">
                    Event #{{ event?.id }} - {{ event?.event_type }}
                </h4>
                <div class="text-sm text-gray-600">
                    API: {{ event?.api_name?.replace(/.*\\/, "") }}
                </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden flex-1 flex flex-col min-h-0">
                <div class="bg-gray-50 px-4 py-3 border-b border-gray-200 flex-shrink-0">
                    <h4 class="text-base font-medium text-gray-900">
                        Metadata
                    </h4>
                </div>

                <div class="p-4 flex-1 min-h-0 flex flex-col">
                    <CodeViewer
                        v-if="event?.metadata"
                        :model-value="event.metadata"
                        format="json"
                    />
                    <div v-else class="p-8 text-center text-gray-500">
                        No metadata available for this event
                    </div>
                </div>
            </div>
        </div>
    </InfoDialog>
</template>

<script setup lang="ts">
import { UsageEvent } from "@/types";
import { CodeViewer, InfoDialog } from "quasar-ui-danx";

interface Props {
    event?: UsageEvent | null;
    visible?: boolean;
}

defineProps<Props>();

interface Emits {
    (e: 'close'): void;
}

defineEmits<Emits>();
</script>