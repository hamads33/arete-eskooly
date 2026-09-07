# Pakistan Academic Configuration

Use **General Settings → Academic Configuration** to create sessions and academic rule profiles.

- Institute modes: internal school, coaching center, FBISE, provincial BISE, or custom board.
- Sessions accept institute labels such as `2026`, `2026-27`, or `Session 2026-2027`.
- Profiles store effective dates, applicable levels, minimum attendance, pass threshold and absent calculation policy.
- Selecting FBISE seeds the editable 2026 A++ through U bands for that profile only. It does not alter internal or other-board grading.
- Results first resolve the exam's rule profile, then the institute default profile, then the legacy grading table.
- Historical results should retain their selected `rule_profile_id`; administrators should create a new effective profile instead of rewriting an old profile.

The schema also includes institute-scoped class sections, academic streams, subject types, holidays and expanded Pakistani identity/board fields. Dedicated section/stream administration remains listed as partial in the status matrix.
