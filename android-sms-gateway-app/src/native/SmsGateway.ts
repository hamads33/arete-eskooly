import NativeSmsGateway from './NativeSmsGateway';

export type {
  SimInfo,
  SendSmsRequest,
  SendSmsAccepted,
  SmsEvent,
  StoredSmsResult,
  SmsCapabilityStatus,
  DeviceSnapshot,
  GatewayServiceState,
} from './NativeSmsGateway';

/** Clean error codes the native module rejects with or reports in smsFailed events. */
export type NativeSmsError =
  | 'GENERIC_FAILURE'
  | 'RADIO_OFF'
  | 'NO_SERVICE'
  | 'NULL_PDU'
  | 'SMS_LIMIT_EXCEEDED'
  | 'FDN_CHECK_FAILED'
  | 'SIM_UNAVAILABLE'
  | 'PERMISSION_DENIED'
  | 'INVALID_NUMBER'
  | 'INVALID_MESSAGE'
  | 'NETWORK_ERROR'
  | 'SHORT_CODE_BLOCKED'
  | 'SEND_EXCEPTION';

export default NativeSmsGateway;
