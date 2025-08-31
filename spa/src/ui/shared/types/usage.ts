export interface UsageSummary {
  total_cost: number | null;
  total_input_tokens: number | null;
  total_output_tokens: number | null;
  input_cost: number | null;
  output_cost: number | null;
  event_count: number;
}

export interface UsageEvent {
  id: number;
  created_at: string;
  event_type: string;
  api_provider: string;
  model?: string;
  input_tokens: number | null;
  output_tokens: number | null;
  total_cost: number | null;
  input_cost: number | null;
  output_cost: number | null;
  metadata?: Record<string, any>;
}