export type GatewayConnectionState =
  | 'DISCONNECTED'
  | 'CONNECTING'
  | 'CONNECTED'
  | 'AUTH_ERROR'
  | 'DEVICE_DISABLED'
  | 'SERVER_OFFLINE';

export type GatewayRunningState = 'STOPPED' | 'STARTING' | 'RUNNING' | 'STOPPING';

export type SimState = 'AVAILABLE' | 'MISSING' | 'PERMISSION_REQUIRED' | 'NOT_SELECTED';

/** Why sending is on hold even though the gateway runs (the gateway keeps acknowledging). */
export type PauseReason =
  | 'SIM_UNAVAILABLE'
  | 'PERMISSION_REQUIRED'
  | 'SMS_LIMIT_EXCEEDED'
  | 'SERVER_DISABLED'
  | 'DAILY_LIMIT'
  | null;

export type JobStatus =
  | 'RECEIVED'
  | 'PROCESSING'
  | 'SENDING'
  | 'SENT_LOCAL'
  | 'ACK_PENDING'
  | 'COMPLETED'
  | 'ACK_REJECTED'
  | 'FAILED_LOCAL'
  | 'FAILURE_ACK_PENDING'
  | 'FAILED_COMPLETE'
  | 'CANCELLED';

/**
 * Once a job reaches one of these, the SMS physically left the phone: it must never be handed
 * to SmsManager again, whatever the server says.
 */
export const NEVER_RESEND: ReadonlySet<JobStatus> = new Set<JobStatus>([
  'SENDING',
  'SENT_LOCAL',
  'ACK_PENDING',
  'COMPLETED',
  'ACK_REJECTED',
]);

export type GatewayJob = {
  id: number;
  serverMessageId: number;
  phoneNumber: string;
  message: string;
  priority: string;
  simSlot: number | null;
  subscriptionId: number | null;
  simSlotUsed: number | null;
  status: JobStatus;
  attempt: number;
  sendToken: string | null;
  segmentCount: number | null;
  receivedAt: number;
  processingAt: number | null;
  sentAt: number | null;
  deliveredAt: number | null;
  completedAt: number | null;
  failureCode: string | null;
  failureMessage: string | null;
  serverAcknowledged: boolean;
  ackRetryCount: number;
  nextAckAt: number | null;
  updatedAt: number;
};

export type LogLevel = 'debug' | 'info' | 'warn' | 'error';

export type GatewayLog = {
  id: number;
  timestamp: number;
  level: LogLevel;
  event: string;
  serverMessageId: number | null;
  details: string | null;
};

export type SelectedSim = {
  subscriptionId: number;
  slotIndex: number;
  carrierName: string | null;
  displayName: string | null;
};

export type LocalSettings = {
  syncIntervalSeconds: number;
  smsDelaySeconds: number;
  logRetentionDays: number;
  startOnBoot: boolean;
  requestDeliveryReports: boolean;
  dailyLimit: number;
};

export const DEFAULT_SETTINGS: LocalSettings = {
  syncIntervalSeconds: 30,
  smsDelaySeconds: 3,
  logRetentionDays: 14,
  startOnBoot: true,
  requestDeliveryReports: false,
  dailyLimit: 0,
};

export type Pairing = {
  baseUrl: string;
  deviceName: string | null;
  deviceId: number | null;
  pairedAt: number;
};

export type GatewayCounters = {
  sentToday: number;
  failedToday: number;
  pendingQueue: number;
  pendingAcks: number;
  lastSmsSentAt: number | null;
};
