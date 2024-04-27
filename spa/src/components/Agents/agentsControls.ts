import { Agents } from "@/routes/agents";
import { useListControls } from "quasar-ui-danx";

export const {
    // State
    pagedItems,
    filter,
    globalFilter,
    filterFieldOptions,
    showFilters,
    isLoadingList,
    isLoadingSummary,
    quasarPagination,
    selectedRows,
    summary,
    activeItem,
    activePanel,

    // Actions
    initialize,
    refreshAll,
    getNextItem,
    activatePanel,
    applyFilterFromUrl,
    setItemInList
} = useListControls("agents", { routes: Agents });
