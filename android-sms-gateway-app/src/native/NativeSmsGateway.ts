import type {CodegenTypes, TurboModule} from 'react-native';
import {TurboModuleRegistry} from 'react-native';

export type SimInfo = {
  subscriptionId: number;
  slotIndex: number;
  carrierName: string | null;
  displayName: string | null;
  phoneNumber: string | null;
  countryIso: string | null;
  isEmbedded: boolean;
};

export type SendSmsRequest = {
  jobId: string;
  phoneNumber: string;
  message: string;
  // -1 = the phone's default SMS subscription.
  subscriptionId: number;
  requestDeliveryReport: boolean;
};

export type SendSmsAccepted = {
  jobId: string;
  sendToken: string;
  segmentCount: number;
};

export type SmsEvent = {
  jobId: string;
  sendToken: string;
  // 'sending' | 'sent' | 'failed' | 'delivered'
  status: string;
  errorCode: string | null;
  resultCode: number;
  segmentIndex: number;
  segmentCount: number;
  timestamp: number;
};

export type StoredSmsResult = {
  jobId: string;
  sendToken: string;
  // 'sending' | 'sent' | 'failed'
  status: string;
  errorCode: string | null;
  resultCode: number;
  segmentCount: number;
  segmentsSent: number;
  startedAt: number;
  completedAt: number;
  deliveredAt: number;
};

export type SmsCapabilityStatus = {
  hasTelephony: boolean;
  sendSmsPermission: boolean;
  phoneStatePermission: boolean;
  notificationPermission: boolean;
  activeSimCount: number;
  defaultSmsSubscriptionId: number;
  airplaneMode: boolean;
};

export type DeviceSnapshot = {
  batteryLevel: number;
  charging: boolean;
  networkType: string;
  androidVersion: string;
  sdkInt: number;
  manufacturer: string;
  model: string;
  appVersion: string;
  appBuild: number;
  debugBuild: boolean;
};

export type GatewayServiceState = {
  running: boolean;
  desiredRunning: boolean;
  startOnBoot: boolean;
  intervalSeconds: number;
  lastTickAt: number;
  reason: string;
};

export interface Spec extends TurboModule {
  getAvailableSims(): Promise<SimInfo[]>;
  getSmsCapabilityStatus(): Promise<SmsCapabilityStatus>;
  sendSms(request: SendSmsRequest): Promise<SendSmsAccepted>;
  getStoredResults(): Promise<StoredSmsResult[]>;
  clearStoredResult(jobId: string): void;
  getDeviceSnapshot(): Promise<DeviceSnapshot>;
  generateUuid(): string;

  startGateway(intervalSeconds: number): Promise<boolean>;
  stopGateway(): Promise<boolean>;
  getGatewayServiceState(): Promise<GatewayServiceState>;
  updateGatewayNotification(title: string, text: string): void;
  setGatewayInterval(seconds: number): void;
  setStartOnBoot(enabled: boolean): void;
  scheduleMaintenance(): void;
  cancelMaintenance(): void;

  isIgnoringBatteryOptimizations(): Promise<boolean>;
  requestIgnoreBatteryOptimizations(): void;
  openAppSettings(): void;

  readonly onSmsEvent: CodegenTypes.EventEmitter<SmsEvent>;
  readonly onGatewayServiceEvent: CodegenTypes.EventEmitter<GatewayServiceState>;
}

export default TurboModuleRegistry.getEnforcing<Spec>('SmsGateway');
