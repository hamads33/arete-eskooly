import {logRepository} from '../storage/logRepository';
import type {LogLevel} from '../types/gateway';

/**
 * Gateway log. Callers pass masked phone numbers and never pass tokens or full message bodies.
 * Nothing is written to the console, so nothing sensitive reaches logcat or crash breadcrumbs.
 */
function write(level: LogLevel, event: string, details?: string | null, serverMessageId?: number | null) {
  return logRepository.add(level, event, details ?? null, serverMessageId ?? null);
}

export const log = {
  debug: (event: string, details?: string | null, id?: number | null) => write('debug', event, details, id),
  info: (event: string, details?: string | null, id?: number | null) => write('info', event, details, id),
  warn: (event: string, details?: string | null, id?: number | null) => write('warn', event, details, id),
  error: (event: string, details?: string | null, id?: number | null) => write('error', event, details, id),
};
