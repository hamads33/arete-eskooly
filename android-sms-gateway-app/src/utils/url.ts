export const API_PATH = '/api/sms-gateway/v1';

export type UrlCheck = {ok: true; baseUrl: string; apiUrl: string} | {ok: false; error: string};

/**
 * Accepts either the school URL (https://school.example.com) or the full API base shown in the
 * LMS (https://school.example.com/api/sms-gateway/v1). Plain HTTP is accepted only in debug builds.
 */
export function normalizeServerUrl(input: string, allowHttp: boolean): UrlCheck {
  let raw = input.trim();
  if (!raw) {
    return {ok: false, error: 'Enter the LMS server URL.'};
  }
  if (!/^[a-z]+:\/\//i.test(raw)) {
    raw = `https://${raw}`;
  }
  const match = /^(https?):\/\/([^/?#\s]+)([^?#\s]*)?$/i.exec(raw);
  if (!match) {
    return {ok: false, error: 'That does not look like a web address.'};
  }
  const scheme = match[1].toLowerCase();
  const host = match[2];
  let path = (match[3] ?? '').replace(/\/+$/, '');
  if (scheme === 'http' && !allowHttp) {
    return {ok: false, error: 'HTTPS is required. Use an https:// address for the LMS.'};
  }
  if (!/^[a-z0-9.-]+(:\d{1,5})?$/i.test(host) && !/^\[[0-9a-f:]+\](:\d{1,5})?$/i.test(host)) {
    return {ok: false, error: 'The server host name is not valid.'};
  }
  if (path.toLowerCase().endsWith(API_PATH)) {
    path = path.slice(0, -API_PATH.length);
  }
  const baseUrl = `${scheme}://${host.toLowerCase()}${path}`;
  return {ok: true, baseUrl, apiUrl: `${baseUrl}${API_PATH}`};
}

export function apiUrlFor(baseUrl: string): string {
  return `${baseUrl.replace(/\/+$/, '')}${API_PATH}`;
}
