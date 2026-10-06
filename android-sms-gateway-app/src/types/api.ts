/** Shapes of the LMS gateway API v1 (docs/android-sms-gateway.md in the LMS repo). */

export type ApiErrorBody = {
  success: false;
  error: {code: string; message: string};
};

export type ServerConfig = {
  api_version: string;
  sms_enabled: boolean;
  queue_processing_enabled: boolean;
  batch_size: number;
  heartbeat_interval_seconds: number;
  poll_interval_seconds: number;
  reservation_timeout_minutes: number;
  default_sim_slot: number | null;
  quiet_hours: {start: string; end: string} | null;
  retryable_error_codes: string[];
  server_time: string;
};

export type DevicePayload = {
  id: number;
  device_uuid: string;
  device_name: string;
  phone_number: string | null;
  sim_slot: number | null;
};

export type RegisterRequest = {
  pairing_code: string;
  device_uuid: string;
  manufacturer?: string;
  model?: string;
  android_version?: string;
  app_version?: string;
  phone_number?: string;
  sim_slot?: number;
  sim_name?: string;
};

export type RegisterResponse = {
  success: true;
  token: string;
  token_type: 'Bearer';
  device: DevicePayload;
  config: ServerConfig;
};

export type HeartbeatRequest = {
  battery_level?: number;
  network?: string;
  sim_slot?: number;
  phone_number?: string;
  app_version?: string;
};

export type DeviceInfoRequest = HeartbeatRequest & {
  manufacturer?: string;
  model?: string;
  android_version?: string;
  sim_name?: string;
};

export type HeartbeatResponse = {
  success: true;
  server_time: string;
  queued_messages: number;
  held_by_device: number;
  next_heartbeat_seconds: number;
};

export type ServerMessage = {
  id: number;
  phone_number: string;
  message: string;
  priority: 'high' | 'normal' | 'low' | string;
  sim_slot: number | null;
};

export type MessagesResponse = {success: true; messages: ServerMessage[]};

export type SentRequest = {
  sent_at?: string;
  sim_slot?: number;
  android_message_reference?: string;
};

export type FailedRequest = {error_code: string; error_message?: string};

export type FailedResponse = {
  success: true;
  id: number;
  status: 'queued' | 'failed';
  will_retry: boolean;
};

export type BulkResultItem =
  | ({id: number; status: 'sent'} & SentRequest)
  | ({id: number; status: 'failed'} & FailedRequest)
  | {id: number; status: 'processing'};

export type BulkStatusResponse = {
  success: true;
  results: Array<
    | {id: number; success: true; status: string}
    | {id: number; success: false; error: {code: string; message: string}}
  >;
};
