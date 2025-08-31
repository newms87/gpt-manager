import { fCurrency, fDateTime, fNumber, TableColumn } from "quasar-ui-danx";
import { controls } from "./controls";

export const columns: TableColumn[] = [
    {
        name: "id",
        label: "ID",
        align: "left",
        sortable: true,
        shrink: true,
        onClick: (event) => controls.activatePanel(event, "edit")
    },
    {
        name: "event_type",
        label: "Event Type",
        align: "left",
        sortable: true
    },
    {
        name: "api_name",
        label: "API",
        align: "left",
        sortable: true,
        format: (value) => value.replace(/.*\\/, "")
    },
    {
        name: "input_tokens",
        label: "Input Tokens",
        align: "right",
        sortable: true,
        format: fNumber
    },
    {
        name: "output_tokens",
        label: "Output Tokens",
        align: "right",
        sortable: true,
        format: fNumber
    },
    {
        name: "total_tokens",
        label: "Total Tokens",
        align: "right",
        sortable: true,
        format: fNumber
    },
    {
        name: "input_cost",
        label: "Input Cost",
        align: "right",
        sortable: true,
        format: fCurrency
    },
    {
        name: "output_cost",
        label: "Output Cost",
        align: "right",
        sortable: true,
        format: fCurrency
    },
    {
        name: "total_cost",
        label: "Total Cost",
        align: "right",
        sortable: true,
        format: fCurrency
    },
    {
        name: "run_time_ms",
        label: "Runtime (ms)",
        align: "right",
        sortable: true,
        format: fNumber
    },
    {
        name: "created_at",
        label: "Created",
        sortable: true,
        format: fDateTime
    }
];
