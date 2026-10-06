import axios, {type AxiosInstance, type AxiosRequestConfig} from 'axios';
import type {
  BulkResultItem,
  BulkStatusResponse,
  DeviceInfoRequest,
  DevicePayload,
  FailedRequest,
  FailedResponse,
  HeartbeatRequest,
  HeartbeatResponse,
  MessagesResponse,
  RegisterRequest,
  RegisterResponse,
  SentRequest,
  ServerConfig,
} from '../types/api';
import {sleep} from '../utils/time';
import {ApiError, toApiError} from './errors';

const TIMEOUT_MS = 20_000;
const RETRY_DELAYS_MS = [1_000, 3_000];

type TokenProvider = () => Promise<string | null>;
type AuthFailureListener = (e: ApiError) => void;

function assertSuccess<T>(data: unknown): T {
  if (!data || typeof data !== 'object' || (data as {success?: unknown}).success !== true) {
    throw new ApiError('invalid_response', 'INVALID_RESPONSE', 'This address did not answer like the LMS SMS gateway API.');
  }
  return data as T;
}

function createHttp(apiUrl: string): AxiosInstance {
  return axios.create({
    baseURL: apiUrl,
    timeout: TIMEOUT_MS,
    headers: {Accept: 'application/json', 'Content-Type': 'application/json'},
    validateStatus: s => s >= 200 && s < 300,
  });
}

/**
 * The LMS SMS gateway API v1. Only idempotent calls are retried; GET /messages reserves
 * messages and POST /processing moves state, so they are never replayed automatically.
 * Nothing here ever sends an SMS.
 */
export class GatewayApi {
  private readonly http: AxiosInstance;

  constructor(
    public readonly apiUrl: string,
    private readonly token: TokenProvider,
    private readonly onAuthFailure?: AuthFailureListener,
  ) {
    this.http = createHttp(apiUrl);
    this.http.interceptors.request.use(async config => {
      const t = await this.token();
      if (t) {
        // Only Authorization: Android's HTTP stack drops it on a cross-host redirect, which it
        // would not do for a custom header such as X-Device-Token.
        config.headers.set('Authorization', `Bearer ${t}`);
      }
      return config;
    });
  }

  private async call<T>(config: AxiosRequestConfig, idempotent: boolean): Promise<T> {
    const attempts = idempotent ? RETRY_DELAYS_MS.length + 1 : 1;
    let last: ApiError | null = null;
    for (let i = 0; i < attempts; i++) {
      try {
        const res = await this.http.request(config);
        return assertSuccess<T>(res.data);
      } catch (e) {
        last = toApiError(e);
        if (last.kind === 'auth' || last.kind === 'disabled') {
          this.onAuthFailure?.(last);
          throw last;
        }
        const retryable = last.kind === 'network' || last.kind === 'server';
        if (!retryable || i === attempts - 1) {
          throw last;
        }
        await sleep(RETRY_DELAYS_MS[i]);
      }
    }
    throw last ?? new ApiError('network', 'UNKNOWN', 'Request failed.');
  }

  heartbeat(body: HeartbeatRequest) {
    return this.call<HeartbeatResponse>({method: 'POST', url: '/heartbeat', data: body}, true);
  }

  deviceInfo(body: DeviceInfoRequest) {
    return this.call<{success: true; device: DevicePayload}>({method: 'POST', url: '/device-info', data: body}, true);
  }

  async getConfig(): Promise<ServerConfig> {
    const res = await this.call<{success: true; config: ServerConfig}>({method: 'GET', url: '/config'}, true);
    return res.config;
  }

  async fetchMessages(limit: number) {
    const res = await this.call<MessagesResponse>({method: 'GET', url: '/messages', params: {limit}}, false);
    return Array.isArray(res.messages) ? res.messages : [];
  }

  markProcessing(id: number) {
    return this.call<{success: true; id: number; status: string}>(
      {method: 'POST', url: `/messages/${id}/processing`},
      false,
    );
  }

  markSent(id: number, body: SentRequest) {
    return this.call<{success: true; id: number; status: string}>(
      {method: 'POST', url: `/messages/${id}/sent`, data: body},
      true,
    );
  }

  markFailed(id: number, body: FailedRequest) {
    return this.call<FailedResponse>({method: 'POST', url: `/messages/${id}/failed`, data: body}, false);
  }

  bulkStatus(results: BulkResultItem[]) {
    return this.call<BulkStatusResponse>({method: 'POST', url: '/messages/bulk-status', data: {results}}, false);
  }
}

/** POST /register — the only unauthenticated call. Never retried (the pairing code is single use). */
export async function registerDevice(apiUrl: string, body: RegisterRequest): Promise<RegisterResponse> {
  try {
    const res = await createHttp(apiUrl).post('/register', body);
    return assertSuccess<RegisterResponse>(res.data);
  } catch (e) {
    throw toApiError(e);
  }
}

/**
 * Checks that the URL is the LMS gateway API without a token: GET /config must answer with the
 * API's own 401 UNAUTHENTICATED JSON (or 200 when a token is supplied).
 */
export async function testConnection(apiUrl: string, token?: string | null): Promise<{authenticated: boolean}> {
  const http = createHttp(apiUrl);
  try {
    const res = await http.get('/config', token ? {headers: {Authorization: `Bearer ${token}`}} : {});
    assertSuccess(res.data);
    return {authenticated: true};
  } catch (e) {
    const err = toApiError(e);
    if (err.kind === 'auth' && err.code === 'UNAUTHENTICATED' && !token) {
      return {authenticated: false};
    }
    throw err;
  }
}
