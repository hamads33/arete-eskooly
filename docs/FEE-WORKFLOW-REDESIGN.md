# Fee collection and voucher workflow

The fee screens use the eSkooly student search and voucher selection flow with Arete's existing fee calculations and payment services.

- Collect Fees starts with live student search. Class and pending fee filters remain available, and regular GET search works without JavaScript.
- Selecting a student opens invoice particulars and a payment summary together. The month can change without losing the student. Full balance and partial payment previews never post a payment automatically.
- Existing invoice generation, individual tuition overrides, discounts, carried balances, arrears, configured payment methods, receipts, WhatsApp, voucher downloads, payment editing, reversal, and automation remain available.
- Generate Fees Invoice now opens `/admin/fees/vouchers`. All Students includes every active student without a pagination limit. Class Wise includes every active student in that class. Student Wise also allows an inactive student through a direct selection URL.
- POST `/admin/fees/vouchers/pdf` generates missing invoices using `FeeService::ensureInvoice` and returns every selected nonvoided invoice in one PDF. Existing invoice values, payments and due dates are retained. New invoices can receive a due date. Payment accounts are institute scoped.
- Voucher copies follow the existing automation setting by default. Student and Institute copies are stacked in portrait; three copies are arranged in landscape. Single invoice links use the same voucher template.
- Preview selection is read only. Changing the selection disables PDF generation until that selection is previewed. Generation requires the existing fee management permission and CSRF token.
- Existing PDF template colors, logo, body, footer and signature configuration are used. No database migration is required.
- The PWA worker version is `20261003-fees-1`; the new fee assets are precached. Private pages remain network only, and the existing quiet worker activation lifecycle is retained. Deploying these files updates the website and installed PWA; closing and reopening the app lets a waiting worker activate.

Validation: `php tests/run.php` passed 747 checks, including the new 101-student generation test, repeat generation preserving payment balances, due dates, voided invoice handling, bank scope, CSRF, PDF headers, and fee permission checks. Existing finance edit, reversal and PDF checks passed. Collection and voucher layouts were inspected at 360px, 390px, desktop and tablet widths. Single, two-copy and three-copy PDF output was rendered and visually reviewed.
