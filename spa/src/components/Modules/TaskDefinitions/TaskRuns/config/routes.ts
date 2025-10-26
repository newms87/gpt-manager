import { apiUrls } from "@/api";
import { TaskRun, TaskRunRoutes } from "@/types";
import { useActionRoutes } from "quasar-ui-danx";

export const routes = useActionRoutes(apiUrls.tasks.runs, {
	errorsUrl: (taskRun: TaskRun) => `${apiUrls.tasks.runs}/${taskRun.id}/errors`
}) as TaskRunRoutes;
