# WhatsApp Integration

The current live-safe mode is click-to-WhatsApp.

- Pakistan numbers such as `03001234567`, `3001234567`, `+923001234567` and `923001234567` normalize to `923001234567`.
- Valid non-Pakistani international numbers are retained.
- Templates accept text placeholders in `{{placeholder_name}}` form; they cannot execute PHP.
- Communication logs store a message hash, not the message body.
- Defaulter rows can open a prefilled reminder in WhatsApp.

`WhatsAppProviderInterface` is the provider boundary. `NullWhatsAppProvider` returns `not_configured`; the application never reports a successful automated delivery without credentials. Add a concrete provider adapter only after a provider and credentials are supplied.

## Click-to-WhatsApp against parent contacts (7 September 2026)

The admin keeps WhatsApp Web signed in; these buttons open a prefilled chat in a new tab so the
admin presses send themselves. Nothing is transmitted automatically.

| Screen | Button | Prefilled message |
| --- | --- | --- |
| Students list | icon beside each row | Student, class and any outstanding balance |
| Guardians | icon beside the contact | Message addressed to the guardian |
| Fees &rarr; Collect Fees | "Send on WhatsApp" | Invoice number, month and outstanding amount, or a paid-in-full note |
| Fees &rarr; Receipt | "Send receipt on WhatsApp" | Amount received, receipt number and remaining balance |
| Fees &rarr; Defaulters | icon per row | Outstanding fee reminder |

All buttons render through `app/Views/partials/whatsapp-button.php`, which posts to
`POST /admin/whatsapp/open` (CSRF-protected) with `target="_blank"`. That endpoint normalises the
number, records the attempt in `whatsapp_logs` and redirects to `https://wa.me/...`. A contact
with no mobile number renders a dash instead of a dead button.

### Message logging and privacy

`whatsapp_logs` stores the normalised number and a **hash** of the message, never the body.

### Default templates

Migration `2026_09_07_000019_seed_whatsapp_templates.php` seeds ten editable templates per
institute (fee invoice generated, fee due reminder, defaulter reminder, payment received,
attendance absent, attendance late, result published, test result published, homework published,
general notice). Placeholders are substituted by `WhatsAppService::render()`; an unknown token
renders as an empty string rather than leaking the raw `{{token}}`.

### Number normalisation

`03001234567`, `3001234567`, `+923001234567` and `923001234567` all normalise to `923001234567`.
Valid international numbers are preserved (`+44 7700 900123` &rarr; `447700900123`); anything
unparseable is rejected rather than guessed.

### Still blocked

Automated background sending remains disabled and continues to report "not configured" until a
WhatsApp Business provider and credentials are supplied. Click-to-chat above does not depend on it.
