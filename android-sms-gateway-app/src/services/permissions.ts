import {PermissionsAndroid, type Permission} from 'react-native';
import SmsGateway from '../native/SmsGateway';

export type PermissionResult = 'granted' | 'denied' | 'blocked';

export type PermissionKey = 'sms' | 'phoneState';

// Notification permission is deliberately not requested: without it Android 13+ runs the
// gateway service without showing its notification in the shade.
const PERMISSIONS: Record<PermissionKey, Permission> = {
  sms: PermissionsAndroid.PERMISSIONS.SEND_SMS,
  phoneState: PermissionsAndroid.PERMISSIONS.READ_PHONE_STATE,
};

export async function checkPermission(key: PermissionKey): Promise<boolean> {
  return PermissionsAndroid.check(PERMISSIONS[key]);
}

export async function requestPermission(key: PermissionKey): Promise<PermissionResult> {
  const r = await PermissionsAndroid.request(PERMISSIONS[key]);
  if (r === PermissionsAndroid.RESULTS.GRANTED) {
    return 'granted';
  }
  return r === PermissionsAndroid.RESULTS.NEVER_ASK_AGAIN ? 'blocked' : 'denied';
}

/** Both at once, one system dialog after the other. */
export async function requestRequired(): Promise<PermissionResult> {
  const res = await PermissionsAndroid.requestMultiple([PERMISSIONS.sms, PERMISSIONS.phoneState]);
  const values = Object.values(res);
  if (values.every(v => v === PermissionsAndroid.RESULTS.GRANTED)) {
    return 'granted';
  }
  return values.some(v => v === PermissionsAndroid.RESULTS.NEVER_ASK_AGAIN) ? 'blocked' : 'denied';
}

export async function permissionSummary(): Promise<Record<PermissionKey, boolean>> {
  const [sms, phoneState] = await Promise.all([checkPermission('sms'), checkPermission('phoneState')]);
  return {sms, phoneState};
}

export async function hasRequiredPermissions(): Promise<boolean> {
  const s = await permissionSummary();
  return s.sms && s.phoneState;
}

export function openAppSettings(): void {
  SmsGateway.openAppSettings();
}
