import { ActionTargetItem, downloadFile, request } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL;

export const Agents = {
    list(pager) {
        return request.post(API_URL + "agents/list", pager);
    },
    summary(filter) {
        return request.post(API_URL + "agents/summary", { filter });
    },
    details(agent) {
        return request.get(API_URL + `agents/${agent.id}/details`);
    },
    filterFieldOptions() {
        return request.get(API_URL + "agents/filter-field-options");
    },
    applyAction(action: string, target: ActionTargetItem, data: object) {
        return request.post(API_URL + `agents/${target.id}/apply-action`, { action, data });
    },
    batchAction(action: string, targets: ActionTargetItem[], data: object) {
        return request.post(API_URL + `agents/batch-action`, { action, id: targets.map(r => r.id), data });
    },
    download(filter) {
        return downloadFile(API_URL + `agents/export`, "agents.csv", { filter });
    }
};
