import { TaskRun, TaskRunRoutes } from "@/types";
import { request, useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL + "/task-runs";

export const routes = useActionRoutes(API_URL, {
	subscribeToProcesses(taskRun: TaskRun) {
		return request.get(API_URL + `/${taskRun.id}/subscribe-to-processes`);
	},
	errorsUrl: (taskRun: TaskRun) => `${API_URL}/${taskRun.id}/errors`
}) as TaskRunRoutes;
