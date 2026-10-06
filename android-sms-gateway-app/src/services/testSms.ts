import SmsGateway from '../native/SmsGateway';
import {maskPhone} from '../utils/mask';
import {describeSmsError} from '../utils/smsErrors';
import {log} from './logger';
import {clearStoredResult, sendAndAwait} from './smsBridge';

export type TestResult = {ok: true; segments: number} | {ok: false; message: string};

/** A local-only SMS through the same native path. Never touches the LMS queue or job table. */
export async function sendTestSms(phoneNumber: string, message: string, subscriptionId: number): Promise<TestResult> {
  const jobId = `test-${SmsGateway.generateUuid()}`;
  const phone = phoneNumber.replace(/[\s-]/g, '');
  await log.info('Test SMS sending', `To ${maskPhone(phone)}`);
  const outcome = await sendAndAwait(
    {jobId, phoneNumber: phone, message, subscriptionId, requestDeliveryReport: false},
    2 * 60_000,
  );
  if (outcome.status !== 'unknown') {
    clearStoredResult(jobId);
  }
  if (outcome.status === 'sent') {
    await log.info('Test SMS sent', `To ${maskPhone(phone)}${outcome.segmentCount > 1 ? ` (${outcome.segmentCount} parts)` : ''}`);
    return {ok: true, segments: outcome.segmentCount};
  }
  const message_ =
    outcome.status === 'unknown' ? describeSmsError('RESULT_UNKNOWN') : describeSmsError(outcome.errorCode);
  await log.warn('Test SMS failed', message_);
  return {ok: false, message: message_};
}
