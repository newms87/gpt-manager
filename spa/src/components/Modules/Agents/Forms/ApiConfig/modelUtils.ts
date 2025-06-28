export interface ModelDetails {
	name?: string;
	input: number;
	output: number;
	context?: number;
	cached_input?: number;
	features?: ModelFeatures;
	rate_limits?: {
		tokens_per_minute: number;
		requests_per_minute: number;
	};
	image?: {
		tokens: number;
		base: number;
		tile: string;
	};
	per_request?: number;
}

export interface ModelFeatures {
	streaming: boolean;
	function_calling: boolean;
	structured_outputs: boolean;
	fine_tuning: boolean;
	distillation: boolean;
	predicted_outputs: boolean;
	image_input: boolean;
	audio_input: boolean;
	reasoning: boolean;
	temperature: boolean;
}

export interface ModelInfo {
	name: string;
	api: string;
	details: ModelDetails;
}
