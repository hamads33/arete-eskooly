import {maskPhone, maskToken} from '../src/utils/mask';
import {toServerErrorCode} from '../src/utils/smsErrors';
import {normalizeServerUrl} from '../src/utils/url';

describe('normalizeServerUrl', () => {
  test('adds https and the API path', () => {
    expect(normalizeServerUrl('school.example.com', false)).toEqual({
      ok: true,
      baseUrl: 'https://school.example.com',
      apiUrl: 'https://school.example.com/api/sms-gateway/v1',
    });
  });

  test('accepts the full API base shown in the LMS', () => {
    const r = normalizeServerUrl('https://School.Example.com/lms/api/sms-gateway/v1/', false);
    expect(r).toMatchObject({ok: true, baseUrl: 'https://school.example.com/lms'});
  });

  test('rejects http outside debug builds', () => {
    expect(normalizeServerUrl('http://10.0.0.5:8000', false).ok).toBe(false);
    expect(normalizeServerUrl('http://10.0.0.5:8000', true)).toMatchObject({ok: true, apiUrl: 'http://10.0.0.5:8000/api/sms-gateway/v1'});
  });

  test('rejects junk', () => {
    expect(normalizeServerUrl('', false).ok).toBe(false);
    expect(normalizeServerUrl('https://exa mple.com', false).ok).toBe(false);
  });
});

test('phone numbers are masked in logs', () => {
  expect(maskPhone('+923001234567')).toBe('+92300****567');
});

test('tokens are never shown in full', () => {
  const t = 'smsgw_5e80aa11bb22cc33dd44ee55ff66b869f';
  expect(maskToken(t)).not.toContain('aa11bb22');
});

test('native errors map to the LMS error vocabulary', () => {
  expect(toServerErrorCode('NO_SERVICE')).toBe('NO_SIGNAL');
  expect(toServerErrorCode('RADIO_OFF')).toBe('RADIO_OFF');
  expect(toServerErrorCode('SMS_LIMIT_EXCEEDED')).toBe('DEVICE_TEMPORARILY_UNAVAILABLE');
  expect(toServerErrorCode('PERMISSION_DENIED')).toBe('SMS_PERMISSION_DENIED');
  expect(toServerErrorCode('SIM_UNAVAILABLE')).toBe('SIM_NOT_AVAILABLE');
  expect(toServerErrorCode('RESULT_UNKNOWN')).toBe('SEND_RESULT_UNKNOWN');
});
