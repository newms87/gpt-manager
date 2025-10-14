import type { SchemaAssociation, FragmentSelector } from "@/types/prompts";

export interface TemplateVariable {
  id?: number;
  demand_template_id?: number;
  name: string;
  description?: string;
  mapping_type: 'ai' | 'artifact' | 'team_object';
  artifact_categories?: string[];
  artifact_fragment_selector?: FragmentSelector;
  team_object_schema_association_id?: number;
  schema_association?: SchemaAssociation;
  ai_instructions?: string;
  multi_value_strategy?: 'join' | 'first' | 'unique';
  multi_value_separator?: string;
  created_at?: string;
  updated_at?: string;
}

export interface DemandTemplate {
  id: number;
  name: string;
  description?: string;
  category?: string;
  is_active: boolean;
  metadata?: Record<string, any>;
  template_url?: string;
  google_doc_id?: string;
  template_variables?: TemplateVariable[];
  stored_file?: {
    id: string;
    url: string;
    filename: string;
    mime: string;
  };
  user?: {
    id: number;
    name: string;
    email: string;
  };
  created_at: string;
  updated_at: string;
  deleted_at?: string;
}