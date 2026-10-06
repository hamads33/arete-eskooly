import * as Keychain from 'react-native-keychain';
import SmsGateway from '../native/SmsGateway';

/**
 * Android Keystore-backed storage (AES-GCM, no user-auth prompt so the background service can
 * read it). The device token never touches AsyncStorage, logs or app state that is rendered.
 */
const TOKEN_SERVICE = 'com.arete.smsgateway.device-token';
const UUID_SERVICE = 'com.arete.smsgateway.device-uuid';

const options = {
  storage: Keychain.STORAGE_TYPE.AES_GCM_NO_AUTH,
  accessible: Keychain.ACCESSIBLE.AFTER_FIRST_UNLOCK_THIS_DEVICE_ONLY,
};

let cachedToken: string | null | undefined;

export async function getDeviceToken(): Promise<string | null> {
  if (cachedToken !== undefined) {
    return cachedToken;
  }
  const entry = await Keychain.getGenericPassword({service: TOKEN_SERVICE});
  cachedToken = entry ? entry.password : null;
  return cachedToken;
}

export async function setDeviceToken(token: string): Promise<void> {
  await Keychain.setGenericPassword('device', token, {...options, service: TOKEN_SERVICE});
  cachedToken = token;
}

export async function clearDeviceToken(): Promise<void> {
  await Keychain.resetGenericPassword({service: TOKEN_SERVICE});
  cachedToken = null;
}

/** Stable app-specific id, generated once. Not IMEI, not a hardware serial. */
export async function getOrCreateDeviceUuid(): Promise<string> {
  const entry = await Keychain.getGenericPassword({service: UUID_SERVICE});
  if (entry && entry.password) {
    return entry.password;
  }
  const uuid = SmsGateway.generateUuid();
  await Keychain.setGenericPassword('uuid', uuid, {...options, service: UUID_SERVICE});
  return uuid;
}

export async function resetDeviceUuid(): Promise<string> {
  await Keychain.resetGenericPassword({service: UUID_SERVICE});
  return getOrCreateDeviceUuid();
}
