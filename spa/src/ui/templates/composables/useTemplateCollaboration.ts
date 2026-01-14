import { dxAgentThread } from "@/components/Modules/Agents/Threads/config";
import { usePusher } from "@/helpers/pusher";
import { dxTemplateDefinition } from "@/ui/templates/config";
import type { TemplateDefinition } from "@/ui/templates/types";
import type { AgentThread } from "@/types";
import { onUnmounted, ref, Ref } from "vue";
import { ActionTargetItem } from "quasar-ui-danx";

interface AgentThreadEvent extends ActionTargetItem {
    id: number;
    is_running?: boolean;
}

/** Callback type for thread update events */
type ThreadUpdateCallback = (thread: AgentThread) => void;

// Reload cooldown in milliseconds - prevents duplicate requests when action response and Pusher events arrive close together
const RELOAD_COOLDOWN_MS = 500;

/**
 * Composable for handling real-time collaboration updates via Pusher WebSocket
 * Subscribes to AgentThread and TemplateDefinition updates and refreshes template data when changes occur
 */
export function useTemplateCollaboration(template: Ref<TemplateDefinition | null>) {
    const pusher = usePusher();
    const activeThreadId = ref<number | null>(null);
    const activeTemplateId = ref<number | null>(null);
    const activeJobDispatchId = ref<number | null>(null);

    // Track last reload time to prevent duplicate requests
    let lastReloadTime = 0;
    let lastThreadReloadTime = 0;

    // Store callback references for cleanup
    let threadPusherCallback: ((data: ActionTargetItem) => void) | null = null;
    let templateUpdateCallback: ((data: ActionTargetItem) => void) | null = null;
    let jobDispatchUpdateCallback: ((data: ActionTargetItem) => void) | null = null;

    // External callback for thread updates
    let onThreadUpdateCallback: ThreadUpdateCallback | null = null;

    /**
     * Reload just the thread's chat_messages from the agent-threads endpoint
     * Much more efficient than reloading the entire template
     */
    async function reloadThread(threadId: number): Promise<AgentThread | null> {
        // Skip if called within cooldown period of the last reload
        const now = Date.now();
        if (now - lastThreadReloadTime < RELOAD_COOLDOWN_MS) {
            return null;
        }
        lastThreadReloadTime = now;

        try {
            // Fetch just the thread with chat_messages using the agent-threads endpoint
            const result = await dxAgentThread.routes.details(
                { id: threadId } as AgentThread,
                { chat_messages: true }
            );
            return result as AgentThread;
        } catch (error) {
            console.error("Failed to reload thread:", error);
            return null;
        }
    }

    /**
     * Subscribe to real-time updates for a collaboration thread
     * @param thread - The thread to subscribe to
     * @param onUpdate - Optional callback when thread is updated (receives the refreshed thread)
     */
    async function subscribeToThread(thread: AgentThread, onUpdate?: ThreadUpdateCallback): Promise<void> {
        if (!pusher || !thread?.id || activeThreadId.value === thread.id) {
            return;
        }

        // Unsubscribe from previous thread if any
        if (activeThreadId.value) {
            await unsubscribeFromThread();
        }

        // Store the update callback
        onThreadUpdateCallback = onUpdate || null;

        try {
            // Subscribe to thread updates (is_running changes, new messages)
            // Note: AgentThreadMessage.saved touches its parent AgentThread, so this covers message creation too
            await pusher.subscribeToModel("AgentThread", ["updated"], thread.id);

            activeThreadId.value = thread.id;

            // Handle thread updates (is_running changes, new messages added)
            threadPusherCallback = async (eventData: ActionTargetItem) => {
                const data = eventData as AgentThreadEvent;
                if (data.id === thread.id) {
                    // Reload just the thread, not the entire template
                    const updatedThread = await reloadThread(thread.id);
                    if (updatedThread && onThreadUpdateCallback) {
                        onThreadUpdateCallback(updatedThread);
                    }
                }
            };
            pusher.onEvent("AgentThread", "updated", threadPusherCallback);

        } catch (error) {
            console.error("Failed to subscribe to collaboration thread:", error);
        }
    }

    /**
     * Unsubscribe from the current thread
     */
    async function unsubscribeFromThread(): Promise<void> {
        if (!pusher || !activeThreadId.value) {
            return;
        }

        try {
            await pusher.unsubscribeFromModel("AgentThread", ["updated"], activeThreadId.value);

            // Remove event listener
            if (threadPusherCallback) {
                pusher.offEvent("AgentThread", "updated", threadPusherCallback);
                threadPusherCallback = null;
            }

            // Clear the update callback
            onThreadUpdateCallback = null;

            activeThreadId.value = null;
        } catch (error) {
            console.error("Failed to unsubscribe from thread:", error);
        }
    }

    /**
     * Reload the template with all relationships
     * Includes cooldown to prevent duplicate requests when action response and Pusher events arrive close together
     */
    async function reloadTemplate(): Promise<void> {
        if (!template.value?.id) return;

        // Skip if called within cooldown period of the last reload
        const now = Date.now();
        if (now - lastReloadTime < RELOAD_COOLDOWN_MS) {
            return;
        }
        lastReloadTime = now;

        try {
            const result = await dxTemplateDefinition.routes.details(template.value, {
                collaboration_threads: { chat_messages: true },
                building_job_dispatch: true,
                pending_build_context: true
            });

            if (result) {
                // Update the template reference with fresh data
                Object.assign(template.value, result);
            }
        } catch (error) {
            console.error("Failed to reload template:", error);
        }
    }

    /**
     * Subscribe to real-time updates for a template definition
     * Receives updates when the template's building status changes
     */
    async function subscribeToTemplate(templateDef: TemplateDefinition): Promise<void> {
        if (!pusher || !templateDef?.id || activeTemplateId.value === templateDef.id) {
            return;
        }

        // Unsubscribe from previous template if any
        if (activeTemplateId.value) {
            await unsubscribeFromTemplate();
        }

        try {
            await pusher.subscribeToModel("TemplateDefinition", ["updated"], templateDef.id);
            activeTemplateId.value = templateDef.id;

            templateUpdateCallback = async (eventData: ActionTargetItem) => {
                const data = eventData as { id: number };
                if (data.id === templateDef.id && template.value) {
                    await reloadTemplate();
                }
            };
            pusher.onEvent("TemplateDefinition", "updated", templateUpdateCallback);
        } catch (error) {
            console.error("Failed to subscribe to template:", error);
        }
    }

    /**
     * Unsubscribe from the current template
     */
    async function unsubscribeFromTemplate(): Promise<void> {
        if (!pusher || !activeTemplateId.value) return;

        try {
            await pusher.unsubscribeFromModel("TemplateDefinition", ["updated"], activeTemplateId.value);

            // Remove event listener
            if (templateUpdateCallback) {
                pusher.offEvent("TemplateDefinition", "updated", templateUpdateCallback);
                templateUpdateCallback = null;
            }

            activeTemplateId.value = null;
        } catch (error) {
            console.error("Failed to unsubscribe from template:", error);
        }
    }

    /**
     * Subscribe to real-time updates for a job dispatch (building status)
     */
    async function subscribeToJobDispatch(jobDispatchId: number): Promise<void> {
        if (!pusher || !jobDispatchId || activeJobDispatchId.value === jobDispatchId) {
            return;
        }

        // Unsubscribe from previous job dispatch if any
        if (activeJobDispatchId.value) {
            await unsubscribeFromJobDispatch();
        }

        try {
            await pusher.subscribeToModel("JobDispatch", ["updated"], jobDispatchId);
            activeJobDispatchId.value = jobDispatchId;

            jobDispatchUpdateCallback = async (eventData: ActionTargetItem) => {
                const data = eventData as { id: number };
                if (data.id === jobDispatchId && template.value) {
                    await reloadTemplate();
                }
            };
            pusher.onEvent("JobDispatch", "updated", jobDispatchUpdateCallback);
        } catch (error) {
            console.error("Failed to subscribe to job dispatch:", error);
        }
    }

    /**
     * Unsubscribe from the current job dispatch
     */
    async function unsubscribeFromJobDispatch(): Promise<void> {
        if (!pusher || !activeJobDispatchId.value) return;

        try {
            await pusher.unsubscribeFromModel("JobDispatch", ["updated"], activeJobDispatchId.value);

            if (jobDispatchUpdateCallback) {
                pusher.offEvent("JobDispatch", "updated", jobDispatchUpdateCallback);
                jobDispatchUpdateCallback = null;
            }

            activeJobDispatchId.value = null;
        } catch (error) {
            console.error("Failed to unsubscribe from job dispatch:", error);
        }
    }

    // Cleanup on unmount
    onUnmounted(() => {
        unsubscribeFromThread();
        unsubscribeFromTemplate();
        unsubscribeFromJobDispatch();
    });

    return {
        subscribeToThread,
        unsubscribeFromThread,
        subscribeToTemplate,
        unsubscribeFromTemplate,
        subscribeToJobDispatch,
        unsubscribeFromJobDispatch,
        reloadTemplate,
        reloadThread,
        activeThreadId,
        activeTemplateId,
        activeJobDispatchId
    };
}
