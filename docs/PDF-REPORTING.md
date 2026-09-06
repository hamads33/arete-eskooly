# PDF Reporting

Arete Coaching Center reports use the centralized mPDF service in `app/Services/Pdf`. PDF responses are generated server-side from tenant-scoped database records; the browser never supplies report totals or authoritative values.

## Admin templates

Open **General Settings → Report Templates**. Each report type supports a title, subtitle, introductory text, footer, institute branding toggles, page size, orientation, margins, and brand colors. Preview uses the same renderer as live reports. Template HTML is restricted to a small allowlist and placeholders are report-specific.

Templates are stored per institute in `report_templates`. Reset removes the override and immediately restores the built-in default. Template changes affect presentation only.

## Output behavior

PDF routes return `application/pdf` inline by default. Add `?download=1` to return an attachment. Filenames are normalized to lowercase ASCII-safe segments. Missing or invalid logos are omitted without breaking generation.

Available documents include admission and job letters, salary slips and sheets, fee receipts and invoices, report cards, certificates, student and employee attendance, student lists, admission/contact directories, and account statements.

## Operations

Install dependencies with `composer install`, apply schema changes with `php database/migrate.php`, and run verification with `php tests/run.php`. The renderer needs write access to `storage/tmp/mpdf` and creates that directory when absent.

To generate the visual QA fixture, run `php scripts/generate_pdf_sample.php`. The resulting artifact is `output/pdf/report-engine-sample.pdf`.
