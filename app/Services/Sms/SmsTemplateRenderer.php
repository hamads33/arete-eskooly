<?php
declare(strict_types=1);
namespace App\Services\Sms;

use DomainException;

/**
 * Renders {{variable}} placeholders from real LMS data.
 *
 * Only catalogued variables are allowed, and every placeholder in a message must resolve to a
 * non-empty value: an SMS never leaves with a raw "{{...}}" or a silent blank in it.
 */
final class SmsTemplateRenderer
{
    /** Variable => description. Anything not listed here is rejected. */
    public const VARIABLES = [
        'school_name' => 'Institute name',
        'school_phone' => 'Institute phone',
        'recipient_name' => 'Name of the person receiving the SMS',
        'student_name' => 'Student name',
        'parent_name' => 'Parent / guardian name',
        'class_name' => 'Student class',
        'registration_no' => 'Student registration number',
        'date' => 'Relevant date (e.g. attendance date)',
        'attendance_status' => 'Attendance status (Absent, Late...)',
        'fee_month' => 'Fee month',
        'fee_amount' => 'Outstanding fee amount, with currency',
        'balance' => 'Total remaining balance of the student (all unpaid months), with currency',
        'due_date' => 'Fee due date',
        'invoice_no' => 'Fee invoice number',
        'amount_paid' => 'Amount of a received payment, with currency',
        'receipt_no' => 'Payment receipt number',
        'exam_name' => 'Exam name',
        'marks' => 'Marks obtained / total',
        'percentage' => 'Exam percentage',
        'grade' => 'Exam grade',
        'announcement_title' => 'Announcement title',
    ];

    /** @return list<string> placeholder names used in $body, in order of first use */
    public static function placeholders(string $body): array
    {
        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $body, $m);
        return array_values(array_unique($m[1]));
    }

    /** @throws DomainException when $body uses a variable outside the catalogue */
    public static function validate(string $body): void
    {
        $unknown = array_diff(self::placeholders($body), array_keys(self::VARIABLES));
        if ($unknown) {
            throw new DomainException('Unknown variable' . (count($unknown) > 1 ? 's' : '') . ': {{' . implode('}}, {{', $unknown) . '}}.');
        }
        if (preg_match('/\{\{(?![^{}]*\}\})|(?<!\{)\{(?!\{)[a-z_]+\}/', $body)) {
            throw new DomainException('A placeholder is not closed properly. Use the form {{variable_name}}.');
        }
    }

    /**
     * @param array<string,scalar|null> $values
     * @throws DomainException naming every variable that has no value for this recipient
     */
    public static function render(string $body, array $values): string
    {
        self::validate($body);
        $missing = [];
        foreach (self::placeholders($body) as $name) {
            if (!isset($values[$name]) || trim((string) $values[$name]) === '') {
                $missing[] = $name;
            }
        }
        if ($missing) {
            throw new DomainException('No value for {{' . implode('}}, {{', $missing) . '}} for this recipient.');
        }
        // Money values carry their currency ("Pkr 5,000"); when the template already writes one right
        // before the placeholder ("Rs. {{amount_paid}}"), print the number only.
        $out = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', static function (array $m) use ($values, $body): string {
            $value = trim((string) $values[$m[1][0]]);
            if (in_array($m[1][0], ['amount_paid', 'fee_amount', 'balance'], true) && preg_match('/(?:rs|pkr|rupees?)\.?\s*:?\s*$/i', substr($body, max(0, $m[0][1] - 12), min(12, $m[0][1])))) {
                $value = (string) preg_replace('/^[^\d\-]+/', '', $value);
            }
            return $value;
        }, $body, -1, $count, PREG_OFFSET_CAPTURE) ?? '';
        return trim($out);
    }
}
