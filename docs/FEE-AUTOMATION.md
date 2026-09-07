# Fee Automation

## Policy

Configure the generation day, admission-month policy, due day, voucher copies and payment methods at `/admin/fees/automation`.

Admission policies:

- `current_month`: create the current invoice after a successful admission.
- `next_month`: do not bill the admission month; the scheduled monthly run handles the next month.

## Manual generation

The **Generate now** action calls the same idempotent service as the scheduled command. Existing student/month invoices are skipped.

## Scheduled generation

Run once daily from cron or Task Scheduler:

```text
php scripts/generate_monthly_fees.php 2026-09
```

The command checks institute billing settings and records runs in `fee_generation_runs`. Re-running does not duplicate invoices.

## Carry-forward and collection

Invoices persist tuition, scoped particulars, individual fixed/percentage discounts and prior balance. Collections support partial and multiple payments. Payments are reversed with a reason; invoices with history cannot be hard-deleted. Unpaid invoices can be voided with an audit entry.
