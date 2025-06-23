export interface WhatsAppConnection {
	id: number;
	team_id: number;
	name: string;
	phone_number: string;
	api_provider: 'twilio' | 'whatsapp_business';
	account_sid?: string;
	auth_token?: string;
	access_token?: string;
	webhook_url?: string;
	api_config?: Record<string, any>;
	is_active: boolean;
	status: 'pending' | 'connected' | 'disconnected' | 'failed';
	last_sync_at?: string;
	verified_at?: string;
	message_count?: number;
	created_at: string;
	updated_at: string;
}

export interface WhatsAppMessage {
	id: number;
	whatsapp_connection_id: number;
	from_number: string;
	to_number: string;
	message_type: 'text' | 'image' | 'document' | 'audio' | 'video';
	content?: string;
	media_url?: string;
	media_type?: string;
	metadata?: Record<string, any>;
	direction: 'inbound' | 'outbound';
	status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
	external_id?: string;
	error_message?: string;
	sent_at?: string;
	delivered_at?: string;
	read_at?: string;
	created_at: string;
	updated_at: string;
	whatsapp_connection?: WhatsAppConnection;
}

export interface WhatsAppProvider {
	name: string;
	label: string;
	requiresAccountSid: boolean;
	requiresAuthToken: boolean;
	requiresAccessToken: boolean;
}