import { ref } from "vue";

const activeAuditRequestId = ref<string | null>(null);
const activePanel = ref<string | null>(null);

export function useAuditRequestPanels() {
	function showAuditRequest(id: string, panel: string | null = null) {
		activeAuditRequestId.value = id;
		activePanel.value = panel;
	}

	function hideAuditRequest() {
		activeAuditRequestId.value = null;
		activePanel.value = null;
	}

	return {
		activeAuditRequestId,
		activePanel,
		showAuditRequest,
		hideAuditRequest
	};
}
