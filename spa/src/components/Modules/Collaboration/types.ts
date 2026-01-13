import { AgentThread, AgentThreadMessage } from "@/types";
import { ActionTargetItem, UploadedFile } from "quasar-ui-danx";

/**
 * Extended thread interface for collaboration contexts
 * Includes optional polymorphic relationship fields
 */
export interface CollaborationThread extends AgentThread {
	collaboratable_type?: string;
	collaboratable_id?: number;
}

/**
 * Screenshot request embedded in message data
 * Used when the LLM needs to see the current state of the preview
 */
export interface ScreenshotRequest {
	id: string;
	status: "pending" | "in_progress" | "completed" | "failed";
	reason?: string;
	completed_at?: string;
	stored_file_id?: number;
}

/**
 * Version history entry for template content
 */
export interface TemplateDefinitionHistory extends ActionTargetItem {
	id: number;
	template_definition_id: number;
	user_id?: number;
	user_name?: string;
	content: string;
	created_at: string;
}

/**
 * Props for the main CollaborationPanel component
 */
export interface CollaborationPanelProps {
	thread: CollaborationThread;
	title: string;
	loading?: boolean;
}

/**
 * Props for the CollaborationChat component
 */
export interface CollaborationChatProps {
	thread: CollaborationThread;
	loading?: boolean;
	readonly?: boolean;
}

/**
 * Props for the CollaborationMessageCard component
 */
export interface CollaborationMessageCardProps {
	message: AgentThreadMessage;
	readonly?: boolean;
}

/**
 * Props for the CollaborationFileUpload component
 */
export interface CollaborationFileUploadProps {
	accept?: string;
	multiple?: boolean;
}

/**
 * Props for the CollaborationVersionHistory component
 */
export interface CollaborationVersionHistoryProps {
	history: TemplateDefinitionHistory[];
	currentVersion?: string;
}

/**
 * Props for the CollaborationScreenshotCapture component
 */
export interface CollaborationScreenshotCaptureProps {
	targetRef: HTMLElement | null;
	requestId?: string | null;
	autoCapture?: boolean;
}

/**
 * Event payload when a message is sent
 */
export interface SendMessagePayload {
	message: string;
	files?: File[] | UploadedFile[];
}
