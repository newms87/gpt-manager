import type { AgentThread } from "@/types";
import type { SchemaAssociation, FragmentSelector } from "@/types/prompts";
import type { ActionTargetItem, UploadedFile } from "quasar-ui-danx";

/**
 * Template definition type - determines how the template is rendered
 */
export type TemplateType = "google_docs" | "html";

/**
 * Variable mapping type determining how a variable gets its value
 */
export type VariableMappingType = "ai" | "artifact" | "team_object";

/**
 * Template variable definition
 * Maps placeholders in templates to data sources
 */
export interface TemplateVariable extends ActionTargetItem {
  id: number;
  template_definition_id?: number;
  name: string;
  description?: string;
  mapping_type: VariableMappingType;
  // Artifact mapping configuration
  artifact_categories?: string[];
  artifact_fragment_selector?: FragmentSelector;
  artifact_field?: string;
  artifact_format?: string;
  // Team object mapping configuration
  team_object_schema_association_id?: number;
  schema_association_id?: number;
  schema_association?: SchemaAssociation;
  team_object_field?: string;
  // Used when setting schema association via fragment
  schema_definition_id?: number;
  schema_fragment_id?: number;
  // AI mapping configuration
  ai_instructions?: string;
  ai_prompt?: string;
  ai_context_fields?: string[];
  // Default/fallback value
  default_value?: string;
  // Multi-value handling
  multi_value_strategy?: 'join' | 'first' | 'unique' | 'max' | 'min' | 'avg' | 'sum';
  multi_value_separator?: string;
  // Value formatting
  value_format_type?: 'text' | 'integer' | 'decimal' | 'currency' | 'percentage' | 'date';
  decimal_places?: number;
  currency_code?: string;
  // Position for ordering
  position?: number;
  created_at: string;
  updated_at: string;
  deleted_at?: string;
}

/**
 * Template version history entry
 */
export interface TemplateDefinitionHistory extends ActionTargetItem {
  id: number;
  template_definition_id: number;
  user_id?: number;
  user_name?: string;
  html_content: string;
  css_content?: string;
  change_summary?: string;
  created_at: string;
  user?: { name: string };
}

/**
 * Job dispatch status for building operations
 */
export interface BuildingJobDispatch {
  id: number;
  status: "Pending" | "Running" | "Complete" | "Exception" | "Failed";
  name?: string;
  count?: number;
  ran_at?: string;
  completed_at?: string;
  timeout_at?: string;
  data?: Record<string, any>;
}

/**
 * Main template definition interface
 * Used by both TemplateDefinitions UI and the HTML Template Builder components
 */
export interface TemplateDefinition extends ActionTargetItem {
  id: number;
  team_id?: number;
  name: string;
  description?: string;
  category?: string;
  type: TemplateType;
  is_active: boolean;
  metadata?: Record<string, any>;
  // Google Docs specific fields
  template_url?: string;
  google_doc_id?: string;
  // HTML template specific fields
  html_content?: string;
  css_content?: string;
  stored_file_id?: number;
  stored_file?: UploadedFile;
  preview_stored_file_id?: number;
  preview_stored_file?: UploadedFile;
  // Building status fields
  building_job_dispatch_id?: number;
  pending_build_context?: string[];
  building_job_dispatch?: BuildingJobDispatch;
  // Common fields
  template_variables?: TemplateVariable[];
  template_variables_count?: number;
  history?: TemplateDefinitionHistory[];
  history_count?: number;
  collaboration_threads?: AgentThread[];
  job_dispatches?: BuildingJobDispatch[];
  job_dispatch_count?: number | null;
  template_variable_count?: number;
  user?: {
    id: number;
    name: string;
    email: string;
  };
  created_at: string;
  updated_at: string;
  deleted_at?: string;
}

/**
 * @deprecated Use TemplateDefinition instead
 */
export type DemandTemplate = TemplateDefinition;

/**
 * Props for the HtmlTemplateBuilder component
 */
export interface HtmlTemplateBuilderProps {
  template: TemplateDefinition;
  thread?: AgentThread | null;
}

/**
 * Props for the HtmlTemplatePreview component
 */
export interface HtmlTemplatePreviewProps {
  html: string;
  css?: string;
  variables?: Record<string, string>;
}

/**
 * Props for the TemplateVariableEditor component
 */
export interface TemplateVariableEditorProps {
  variables: TemplateVariable[];
  templateId: number;
  schemaAssociations?: SchemaAssociation[];
}

/**
 * Props for the TemplateCard component
 */
export interface TemplateCardProps {
  template: TemplateDefinition;
}

/**
 * Event payload when template is updated
 */
export interface TemplateUpdatePayload {
  html_content?: string;
  css_content?: string;
  name?: string;
  description?: string;
  category?: string;
  is_active?: boolean;
}

/**
 * Collaboration message with template updates
 */
export interface TemplateCollaborationMessage {
  message: string;
  files?: File[];
  screenshot?: File;
}