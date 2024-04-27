import { activatePanel, refreshAll } from "@/components/Agents/agentsControls";
import { Agents } from "@/routes/agents";
import { ConfirmDialog, useActions } from "quasar-ui-danx";
import { h } from "vue";

const onAction = Agents.applyAction;
const onBatchAction = Agents.batchAction;
const onFinish = refreshAll;

function formatConfirmText(actionText, agents) {
    return Array.isArray(agents) ? `${actionText} ${agents?.length} agents` : `${actionText} ${agents.name}`;
}

const items = [
    {
        name: "delete",
        label: "Delete",
        menu: true,
        batch: true,
        onFinish,
        vnode: ads => h(ConfirmDialog, { confirmText: formatConfirmText("Delete", ads) })
    },
    {
        label: "Edit",
        name: "edit",
        menu: true,
        onAction: async (action, target) => activatePanel(target, "edit")
    }
];

export const { performAction, filterActions, actions } = useActions(items, {
    onAction,
    onBatchAction
});
