export function startOfToday(): number {
  const d = new Date();
  d.setHours(0, 0, 0, 0);
  return d.getTime();
}

export function sleep(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms));
}

function pad(n: number) {
  return n < 10 ? `0${n}` : String(n);
}

export function formatTime(ts: number | null | undefined): string {
  if (!ts) {
    return '—';
  }
  const d = new Date(ts);
  return `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
}

export function formatDateTime(ts: number | null | undefined): string {
  if (!ts) {
    return '—';
  }
  const d = new Date(ts);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${formatTime(ts)}`;
}

export function formatRelative(ts: number | null | undefined, now = Date.now()): string {
  if (!ts) {
    return 'never';
  }
  const s = Math.max(0, Math.round((now - ts) / 1000));
  if (s < 5) {
    return 'just now';
  }
  if (s < 60) {
    return `${s}s ago`;
  }
  const m = Math.round(s / 60);
  if (m < 60) {
    return `${m} min ago`;
  }
  const h = Math.round(m / 60);
  if (h < 24) {
    return `${h} h ago`;
  }
  return formatDateTime(ts);
}

/** ISO-8601 with the phone's UTC offset, as the LMS expects for sent_at. */
export function isoWithOffset(ts: number): string {
  const d = new Date(ts);
  const off = -d.getTimezoneOffset();
  const sign = off >= 0 ? '+' : '-';
  const abs = Math.abs(off);
  return (
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T` +
    `${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}` +
    `${sign}${pad(Math.floor(abs / 60))}:${pad(abs % 60)}`
  );
}
