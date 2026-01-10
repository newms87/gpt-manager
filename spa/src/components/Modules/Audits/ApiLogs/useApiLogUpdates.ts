import { apiLogRoutes } from "@/components/Modules/Audits/ApiLogs/apiLogRoutes";
import { ApiLog } from "@/components/Modules/Audits/audit-requests";
import { usePusher } from "@/helpers/pusher";
import { ActionTargetItem } from "quasar-ui-danx";
import { isRef, onMounted, onUnmounted, Ref, toValue } from "vue";

/**
 * Composable for subscribing to real-time updates for an ApiLog entry.
 *
 * Automatically subscribes on mount if the ApiLog is in progress (has id, no finished_at).
 * Automatically unsubscribes on unmount.
 * Fetches full details via apiLogRoutes.details() when an update is received.
 *
 * @param apiLog - The ApiLog object, a Ref<ApiLog>, or a getter function returning an ApiLog
 */
export function useApiLogUpdates(apiLog: ApiLog | Ref<ApiLog> | (() => ApiLog)): void {
    const pusher = usePusher();

    function getApiLog(): ApiLog {
        if (typeof apiLog === "function") {
            return apiLog();
        }
        return toValue(apiLog);
    }

    async function onApiLogUpdated(data: ActionTargetItem) {
        const currentApiLog = getApiLog();
        if (data.id === currentApiLog.id) {
            await apiLogRoutes.details(currentApiLog);
        }
    }

    onMounted(async () => {
        const currentApiLog = getApiLog();
        // Only subscribe if the API log is still in progress
        if (currentApiLog?.id && !currentApiLog.finished_at) {
            await pusher.subscribeToModel("ApiLog", ["updated"], currentApiLog.id);
            pusher.onModelEvent(currentApiLog, "updated", onApiLogUpdated);
        }
    });

    onUnmounted(async () => {
        const currentApiLog = getApiLog();
        if (currentApiLog?.id) {
            pusher.offModelEvent(currentApiLog, "updated", onApiLogUpdated);
            await pusher.unsubscribeFromModel("ApiLog", ["updated"], currentApiLog.id);
        }
    });
}
