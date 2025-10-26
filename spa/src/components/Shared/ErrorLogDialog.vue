<template>
    <InfoDialog
        :model-value="!!url"
        title="Error Log"
        max-width="900px"
        color="red"
        @close="$emit('close')"
    >
        <template #default>
            <div v-if="loading" class="flex items-center justify-center py-8">
                <QSpinnerGears class="text-red-500 w-12 h-12" />
                <p class="ml-3 text-slate-400">Loading errors...</p>
            </div>

            <div v-else-if="errors.length === 0" class="text-center py-8">
                <FaSolidCircleCheck class="w-12 h-12 text-green-500 mx-auto mb-3" />
                <p class="text-slate-600">No errors found</p>
            </div>

            <div v-else class="space-y-3 max-h-[600px] overflow-y-auto">
                <div
                    v-for="error in errors"
                    :key="error.id"
                    class="border border-red-200 rounded-lg p-4 bg-red-50"
                >
                    <div class="flex items-start gap-3">
                        <FaSolidTriangleExclamation class="w-5 h-5 text-red-600 flex-shrink-0 mt-1" />
                        <div class="flex-1 min-w-0">
                            <!-- Time Pill + Error Message -->
                            <div class="flex items-start gap-2 mb-2">
                                <LabelPillWidget
                                    :label="fDateTime(error.created_at)"
                                    color="slate"
                                    size="xs"
                                />
                                <div class="font-semibold text-red-900 flex-1">
                                    {{ error.message }}
                                </div>
                            </div>

                            <!-- Action Buttons Row -->
                            <div class="flex gap-2 mt-4">
                                <ActionButton
                                    v-if="error.audit_request_id && authUser?.can?.viewAuditing"
                                    type="view"
                                    label="View Audit Request"
                                    size="xs"
                                    color="sky"
                                    @click="showAuditRequest(error.audit_request_id)"
                                />
                            </div>

                            <!-- Additional Data (Expandable) -->
                            <div v-if="error.data && Object.keys(error.data).length > 0" class="mt-2">
                                <ActionButton
                                    :type="expandedData[error.id] ? 'chevron-down' : 'chevron-right'"
                                    label="Additional Data"
                                    size="xs"
                                    color="slate"
                                    @click="toggleData(error.id)"
                                />
                                <div
                                    v-if="expandedData[error.id]"
                                    class="mt-2 bg-slate-100 p-3 rounded text-xs"
                                >
                                    <pre>{{ JSON.stringify(error.data, null, 2) }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </InfoDialog>

    <AuditRequestPanelsDialog
        v-if="activeAuditRequestId && auditRequest"
        :audit-request="auditRequest"
        @close="onHideAuditRequest"
    />
</template>

<script setup lang="ts">
import { AuditRequest, ErrorLogEntry } from "@/components/Modules/Audits/audit-requests";
import AuditRequestPanelsDialog from "@/components/Modules/Audits/AuditRequestPanelsDialog.vue";
import { useAuditRequestPanels } from "@/components/Modules/Audits/composables/useAuditRequestPanels";
import { dxAudit } from "@/components/Modules/Audits/config";
import { authUser } from "@/helpers/auth";
import { FaSolidCircleCheck, FaSolidTriangleExclamation } from "danx-icon";
import { ActionButton, fDateTime, InfoDialog, LabelPillWidget, request } from "quasar-ui-danx";
import { onMounted, reactive, ref } from "vue";

const props = defineProps<{
    url: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const loading = ref(false);
const errors = ref<ErrorLogEntry[]>([]);
const expandedData = reactive<Record<number, boolean>>({});

const { activeAuditRequestId, showAuditRequest: showAuditRequestPanel, hideAuditRequest } = useAuditRequestPanels();

// Store the loaded audit request object
const auditRequest = ref<AuditRequest | undefined>();

const showAuditRequest = async (auditRequestId: string) => {
    showAuditRequestPanel(auditRequestId);
    // Load the audit request from the server and get the stored object
    auditRequest.value = await dxAudit.routes.details({ id: auditRequestId } as AuditRequest);
};

const onHideAuditRequest = () => {
    hideAuditRequest();
    auditRequest.value = undefined;
};

const toggleData = (errorId: number) => {
    expandedData[errorId] = !expandedData[errorId];
};

const loadErrors = async () => {
    if (!props.url) return;

    loading.value = true;
    try {
        const response = await request.get(props.url);
        errors.value = response || [];
    } catch (error) {
        console.error("Failed to load errors:", error);
        errors.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(loadErrors);
</script>
