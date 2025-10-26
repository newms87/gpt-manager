import { TaskRun, TaskRunRoutes } from "@/types";
import { useActionRoutes } from "quasar-ui-danx";

const API_URL = import.meta.env.VITE_API_URL + "/task-runs";

export const routes = useActionRoutes(API_URL, {
	errorsUrl: (taskRun: TaskRun) => `${API_URL}/${taskRun.id}/errors`
}) as TaskRunRoutes;
