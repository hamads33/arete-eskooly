/** +923001234567 -> +92300****567. Logs and UI lists never show a full recipient number. */
export function maskPhone(phone: string | null | undefined): string {
  if (!phone) {
    return '—';
  }
  const p = phone.trim();
  if (p.length <= 7) {
    return p.replace(/\d(?=\d{2})/g, '*');
  }
  return `${p.slice(0, 6)}****${p.slice(-3)}`;
}

/** smsgw_5e80…b869f -> smsgw_5e80…69f. For "which token is this" hints only. */
export function maskToken(token: string | null | undefined): string {
  if (!token) {
    return '—';
  }
  return token.length > 14 ? `${token.slice(0, 10)}…${token.slice(-3)}` : '••••';
}

/** First few words, for logs that must hint at content without storing it. */
export function previewMessage(message: string, max = 24): string {
  return message.length > max ? `${message.slice(0, max)}…` : message;
}
