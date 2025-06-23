export interface WhatsAppConnection {
    id: number;
    name: string;
    phone_number: string;
    display_phone_number: string;
    api_provider: 'twilio' | 'whatsapp_business';
    is_active: boolean;
    status: 'connected' | 'disconnected' | 'pending' | 'error';
    webhook_url?: string;
    last_sync_at?: string;
    verified_at?: string;
    created_at: string;
    updated_at: string;
    message_count: number;
    has_credentials: boolean;
    is_connected: boolean;
    api_config?: {
        phone_number_id?: string;
        [key: string]: any;
    };
}

export interface WhatsAppMessage {
    id: number;
    external_id?: string;
    from_number: string;
    to_number: string;
    formatted_from_number: string;
    formatted_to_number: string;
    direction: 'inbound' | 'outbound';
    message: string;
    media_urls?: string[];
    status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
    metadata?: {
        profile_name?: string;
        message_type?: string;
        [key: string]: any;
    };
    sent_at?: string;
    delivered_at?: string;
    read_at?: string;
    failed_at?: string;
    error_message?: string;
    created_at: string;
    updated_at: string;
    is_inbound: boolean;
    is_outbound: boolean;
    has_media: boolean;
    whatsapp_connection?: WhatsAppConnection;
}

export interface WhatsAppTaskRunnerConfig {
    action: 'send_message' | 'sync_messages' | 'process_messages';
    connection_id?: number;
    phone_number?: string;
    message_template?: string;
    filter_phone_number?: string;
    include_inbound?: boolean;
    include_outbound?: boolean;
}