import axios, {type AxiosError} from 'axios';

export type ApiErrorKind =
  | 'network' // no connection, DNS, TLS, timeout
  | 'auth' // 401 UNAUTHENTICATED / INVALID_PAIRING_CODE
  | 'disabled' // 403 DEVICE_DISABLED
  | 'not_assigned' // 409 MESSAGE_NOT_ASSIGNED
  | 'conflict' // other 409 (e.g. DEVICE_ALREADY_REGISTERED)
  | 'rate_limited' // 429
  | 'server' // 5xx
  | 'client' // other 4xx
  | 'invalid_response'; // not the LMS gateway API

/** Carries no request config, so headers (and the token) can never leak through logging. */
export class ApiError extends Error {
  constructor(
    public readonly kind: ApiErrorKind,
    public readonly code: string,
    message: string,
    public readonly status: number | null = null,
  ) {
    super(message);
    this.name = 'ApiError';
  }

  get isTransient(): boolean {
    return this.kind === 'network' || this.kind === 'server' || this.kind === 'rate_limited';
  }
}

export function toApiError(e: unknown): ApiError {
  if (e instanceof ApiError) {
    return e;
  }
  if (axios.isAxiosError(e)) {
    return fromAxios(e);
  }
  return new ApiError('network', 'UNKNOWN', 'Unexpected error talking to the LMS.');
}

function fromAxios(e: AxiosError): ApiError {
  const res = e.response;
  if (!res) {
    if (e.code === 'ECONNABORTED' || e.code === 'ETIMEDOUT') {
      return new ApiError('network', 'TIMEOUT', 'The LMS server did not respond in time.');
    }
    return new ApiError('network', 'NETWORK', 'LMS server is currently unreachable.');
  }
  const body = res.data as {error?: {code?: string; message?: string}} | undefined;
  const code = typeof body?.error?.code === 'string' ? body.error.code : `HTTP_${res.status}`;
  const message =
    typeof body?.error?.message === 'string' ? body.error.message.slice(0, 200) : `The LMS answered HTTP ${res.status}.`;
  const s = res.status;
  if (s === 401) {
    return new ApiError('auth', code, message, s);
  }
  if (s === 403) {
    return new ApiError(code === 'DEVICE_DISABLED' ? 'disabled' : 'auth', code, message, s);
  }
  if (s === 409) {
    return new ApiError(code === 'MESSAGE_NOT_ASSIGNED' ? 'not_assigned' : 'conflict', code, message, s);
  }
  if (s === 429) {
    return new ApiError('rate_limited', code, 'The LMS asked the gateway to slow down.', s);
  }
  if (s >= 500) {
    return new ApiError('server', code, 'The LMS server had a problem. Will retry.', s);
  }
  if (!body?.error) {
    return new ApiError('invalid_response', code, 'This address did not answer like the LMS SMS gateway API.', s);
  }
  return new ApiError('client', code, message, s);
}
