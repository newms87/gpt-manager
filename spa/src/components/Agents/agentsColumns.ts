import { filterActions } from "@/components/Agents/agentsActions";
import { activatePanel } from "@/components/Agents/agentsControls";

export const columns = [
    {
        name: "id",
        label: "ID",
        field: "id",
        sortable: true,
        required: true,
        actionMenu: filterActions({ menu: true }),
        onClick: (agent) => activatePanel(agent, "edit")
    },
    {
        name: "name",
        label: "Name",
        field: "name",
        sortable: true,
        required: true
    }
];
