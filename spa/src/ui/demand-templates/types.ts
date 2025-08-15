export interface DemandTemplate {
  id: number;
  name: string;
  description?: string;
  category?: string;
  is_active: boolean;
  metadata?: Record<string, any>;
  template_url?: string;
  google_doc_id?: string;
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