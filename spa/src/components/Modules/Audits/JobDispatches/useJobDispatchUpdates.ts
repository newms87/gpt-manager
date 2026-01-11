import { jobDispatchRoutes } from "@/components/Modules/Audits/JobDispatches/jobDispatchRoutes";
import { JobDispatch } from "@/components/Modules/Audits/audit-requests";
import { usePusher } from "@/helpers/pusher";
import { ActionTargetItem } from "quasar-ui-danx";
import { isRef, onMounted, onUnmounted, Ref, toValue } from "vue";

/**
 * Composable for subscribing to real-time updates for a JobDispatch entry.
 *
 * Automatically subscribes on mount if the JobDispatch is in progress (has id, no completed_at).
 * Automatically unsubscribes on unmount.
 * Fetches full details via jobDispatchRoutes.details() when an update is received.
 *
 * @param jobDispatch - The JobDispatch object, a Ref<JobDispatch>, or a getter function returning a JobDispatch
 */
export function useJobDispatchUpdates(jobDispatch: JobDispatch | Ref<JobDispatch> | (() => JobDispatch)): void {
    const pusher = usePusher();

    function getJobDispatch(): JobDispatch {
        if (typeof jobDispatch === "function") {
            return jobDispatch();
        }
        return toValue(jobDispatch);
    }

    async function onJobDispatchUpdated(data: ActionTargetItem) {
        const currentJobDispatch = getJobDispatch();
        if (data.id === currentJobDispatch.id) {
            await jobDispatchRoutes.details(currentJobDispatch);
        }
    }

    onMounted(async () => {
        const currentJobDispatch = getJobDispatch();
        // Only subscribe if the job dispatch is still in progress
        if (currentJobDispatch?.id && !currentJobDispatch.completed_at) {
            await pusher.subscribeToModel("JobDispatch", ["updated"], currentJobDispatch.id);
            pusher.onModelEvent(currentJobDispatch, "updated", onJobDispatchUpdated);
        }
    });

    onUnmounted(async () => {
        const currentJobDispatch = getJobDispatch();
        if (currentJobDispatch?.id) {
            pusher.offModelEvent(currentJobDispatch, "updated", onJobDispatchUpdated);
            await pusher.unsubscribeFromModel("JobDispatch", ["updated"], currentJobDispatch.id);
        }
    });
}
