import { TaskProcess } from "@/types/task-definitions";
import { AnyObject, StoredFile } from "quasar-ui-danx";

export interface Artifact {
    id: number;
    original_artifact_id?: number;
    task_process_id?: number;
    name: string;
    position: number;
    model: string;
    text_content?: string;
    json_content?: AnyObject;
    files: StoredFile[];
    meta?: AnyObject;
    taskProcess?: TaskProcess;
    created_at: string;
    child_artifacts_count?: number;
}
