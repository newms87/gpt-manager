import { usePusher } from "@/helpers/pusher";
import { dxTemplateDefinition } from "@/ui/templates/config";
import type { TemplateDefinition } from "@/ui/templates/types";
import type { AgentThread, AgentThreadMessage } from "@/types";
import { onUnmounted, ref, Ref } from "vue";
import { ActionTargetItem } from "quasar-ui-danx";

interface AgentThreadEvent extends ActionTargetItem {
    id: number;
    is_running?: boolean;
}

interface AgentThreadMessageEvent extends ActionTargetItem {
    id: number;
    thread_id: number;
    role: string;
}

/**
 * Composable for handling real-time collaboration updates via Pusher WebSocket
 * Subscribes to AgentThread updates and refreshes template data when changes occur
 */
export function useTemplateCollaboration(template: Ref<TemplateDefinition | null>) {
    const pusher = usePusher();
    const activeThreadId = ref<number | null>(null);

    // Store callback references for cleanup
    let threadUpdateCallback: ((data: ActionTargetItem) => void) | null = null;
    let messageCreateCallback: ((data: ActionTargetItem) => void) | null = null;

    /**
     * Subscribe to real-time updates for a collaboration thread
     */
    async function subscribeToThread(thread: AgentThread): Promise<void> {
        if (!pusher || !thread?.id || activeThreadId.value === thread.id) {
            return;
        }

        // Unsubscribe from previous thread if any
        if (activeThreadId.value) {
            await unsubscribeFromThread();
        }

        try {
            // Subscribe to thread updates (is_running changes)
            await pusher.subscribeToModel("AgentThread", ["updated"], thread.id);

            // Subscribe to new messages for this thread
            await pusher.subscribeToModel("AgentThreadMessage", ["created"], true);

            activeThreadId.value = thread.id;

            // Handle thread updates (like is_running status changes)
            threadUpdateCallback = async (eventData: ActionTargetItem) => {
                const data = eventData as AgentThreadEvent;
                if (data.id === thread.id && template.value) {
                    // Reload template to get fresh data including updated thread
                    await reloadTemplate();
                }
            };
            pusher.onEvent("AgentThread", "updated", threadUpdateCallback);

            // Handle new messages
            messageCreateCallback = async (eventData: ActionTargetItem) => {
                const data = eventData as AgentThreadMessageEvent;
                if (data.thread_id === thread.id && template.value) {
                    // Reload template to get the new message and any template updates
                    await reloadTemplate();
                }
            };
            pusher.onEvent("AgentThreadMessage", "created", messageCreateCallback);

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
            await pusher.unsubscribeFromModel("AgentThreadMessage", ["created"], true);

            // Remove event listeners
            if (threadUpdateCallback) {
                pusher.offEvent("AgentThread", "updated", threadUpdateCallback);
                threadUpdateCallback = null;
            }
            if (messageCreateCallback) {
                pusher.offEvent("AgentThreadMessage", "created", messageCreateCallback);
                messageCreateCallback = null;
            }

            activeThreadId.value = null;
        } catch (error) {
            console.error("Failed to unsubscribe from thread:", error);
        }
    }

    /**
     * Reload the template with all relationships
     */
    async function reloadTemplate(): Promise<void> {
        if (!template.value?.id) return;

        try {
            const response = await dxTemplateDefinition.routes.list({
                filter: { id: template.value.id },
                fields: {
                    html_content: true,
                    css_content: true,
                    template_variables: true,
                    history: true,
                    collaboration_threads: { messages: true }
                }
            });

            if (response.data && response.data.length > 0) {
                // Update the template reference with fresh data
                Object.assign(template.value, response.data[0]);
            }
        } catch (error) {
            console.error("Failed to reload template:", error);
        }
    }

    // Cleanup on unmount
    onUnmounted(() => {
        unsubscribeFromThread();
    });

    return {
        subscribeToThread,
        unsubscribeFromThread,
        reloadTemplate,
        activeThreadId
    };
}
