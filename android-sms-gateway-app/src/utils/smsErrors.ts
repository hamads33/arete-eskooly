import type {NativeSmsError} from '../native/SmsGateway';

/**
 * Native error -> LMS error_code (see "POST /messages/{id}/failed" in the LMS docs).
 * Retryable on the server: NO_SIGNAL, RADIO_OFF, GENERIC_FAILURE, NETWORK_ERROR, SIM_BUSY,
 * DEVICE_TEMPORARILY_UNAVAILABLE, TIMEOUT. Everything else fails permanently (admin can retry).
 */
const TO_SERVER: Record<string, string> = {
  GENERIC_FAILURE: 'GENERIC_FAILURE',
  RADIO_OFF: 'RADIO_OFF',
  NO_SERVICE: 'NO_SIGNAL',
  NETWORK_ERROR: 'NETWORK_ERROR',
  SMS_LIMIT_EXCEEDED: 'DEVICE_TEMPORARILY_UNAVAILABLE',
  SIM_UNAVAILABLE: 'SIM_NOT_AVAILABLE',
  PERMISSION_DENIED: 'SMS_PERMISSION_DENIED',
  INVALID_NUMBER: 'INVALID_NUMBER',
  INVALID_MESSAGE: 'INVALID_MESSAGE',
  NULL_PDU: 'NULL_PDU',
  FDN_CHECK_FAILED: 'FDN_CHECK_FAILED',
  SHORT_CODE_BLOCKED: 'SHORT_CODE_BLOCKED',
  SEND_EXCEPTION: 'SEND_EXCEPTION',
  // The phone cannot tell whether the SMS left: reported as non-retryable so the LMS never
  // auto-resends a message that may already have been delivered.
  RESULT_UNKNOWN: 'SEND_RESULT_UNKNOWN',
};

export function toServerErrorCode(code: string | null | undefined): string {
  return (code && TO_SERVER[code]) || 'GENERIC_FAILURE';
}

const DESCRIPTIONS: Record<NativeSmsError | 'RESULT_UNKNOWN', string> = {
  GENERIC_FAILURE: 'The phone reported a generic SMS failure.',
  RADIO_OFF: 'The phone radio is off (airplane mode?).',
  NO_SERVICE: 'No mobile network service.',
  NULL_PDU: 'Android could not encode the SMS.',
  SMS_LIMIT_EXCEEDED: 'Android SMS sending limit reached.',
  FDN_CHECK_FAILED: 'Blocked by the SIM fixed-dialing list.',
  SIM_UNAVAILABLE: 'The selected SIM is not available.',
  PERMISSION_DENIED: 'SMS permission is not granted.',
  INVALID_NUMBER: 'The phone number was rejected.',
  INVALID_MESSAGE: 'The message text was rejected.',
  NETWORK_ERROR: 'The mobile network rejected the SMS.',
  SHORT_CODE_BLOCKED: 'Sending to short codes is blocked on this phone.',
  SEND_EXCEPTION: 'Android refused to send the SMS.',
  RESULT_UNKNOWN: 'The phone did not report whether the SMS was sent.',
};

export function describeSmsError(code: string | null | undefined): string {
  return (code && DESCRIPTIONS[code as NativeSmsError]) || `SMS failed (${code ?? 'unknown'}).`;
}
