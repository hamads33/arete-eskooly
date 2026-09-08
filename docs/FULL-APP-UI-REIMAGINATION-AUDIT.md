# Full-App UI Reimagination Audit

Audit date: 8 September 2026

Benchmark: the existing login/signup composition and approved admin dashboard. Statuses: POLISHED meets the product quality bar; ACCEPTABLE uses the shared system without a blocking inconsistency; BLOCKED has no independent implemented screen.

| Route/Page | Current Problems | Target Experience | Shared Component Needed | Priority | Status |
|---|---|---|---|---|---|
| / | Redirect only; no independent homepage | Preserve safe redirect | None | Low | BLOCKED |
| /login, /signup | No material issue | Premium split auth experience | Auth shell and branded fields | High | POLISHED |
| /403, /404, /500 | Isolated from the app shell | Restrained branded recovery | Error card | Medium | ACCEPTABLE |
| /admin/dashboard | Minor shell alignment differences | Executive overview using real data | KPI, activity, chart cards | High | POLISHED |
| /admin/settings/account, /institute-profile | Long configuration forms | Grouped identity and account settings | Settings panels and form sections | High | POLISHED |
| /admin/settings/fee-particulars, /fee-structure, /fee-invoice-accounts, /discount-types | Dense editable financial settings | Compact fee configuration workspace | Currency controls, editable table, status | High | POLISHED |
| /admin/settings/rules-regulations, /marks-grading | Long policy and range editors | Structured, validated editors | Editor sections and editable table | High | POLISHED |
| /admin/settings/theme-language | Small form in oversized surface | Compact preferences panel | Settings card | Low | ACCEPTABLE |
| /admin/settings/academic-profiles, /academic-structure, /academic-placement | Multiple related academic forms | Clear session, section, stream and placement hierarchy | Split layout, status and filters | High | ACCEPTABLE |
| /admin/settings/report-templates and /{type}/preview | Could resemble raw settings | Real document editor with type rail and preview | Settings rail and preview action | High | POLISHED |
| /admin/classes, /create, /{id}/edit | CRUD density and raw actions | Searchable academic directory | Identity rows and icon actions | High | POLISHED |
| /admin/subjects, /assign | Repetitive table/form composition | Subject catalogue and assignment workspace | Split layout and badges | High | POLISHED |
| /admin/students, /{id}, /{id}/edit | Records lacked identity hierarchy | Student directory and profile | Search, identity and detail grid | High | POLISHED |
| /admin/students/create | Overwhelming admission form | Guided grouped admission | Step sections, sticky actions, errors | High | POLISHED |
| /admin/students/families, /status, /promotions | Generic operational tables | Task-oriented roster tools | Filters, table and status | High | ACCEPTABLE |
| /admin/students/logins | Credentials mixed with record data | Secure credential workspace | Guarded credential actions | High | POLISHED |
| /admin/students/id-cards, /admission-letter, /report-card, /basic-list, /import/example | Utility screens lacked hierarchy | Document selection and export tools | Document cards and export bar | Medium | ACCEPTABLE |
| /admin/employees, /create, /{id}/edit, /logins, /job-letter | Generic staff CRUD | Staff directory, grouped forms and documents | Identity rows and form sections | High | ACCEPTABLE |
| /admin/teacher-assignments | Dense scope configuration | Clear class/subject authorization | Scoped form and table | High | POLISHED |
| /admin/guardians | Parent records presented as CRUD | Family/contact management | Identity rows and status actions | High | ACCEPTABLE |
| /admin/attendance/students, /employees | Dense daily marking | Task-first marking surface | Date/class filter and editable table | High | POLISHED |
| /admin/attendance/student-report, /employee-report, /classwise | Wide reporting tables | Report hierarchy and contained data | Filters, metrics and scroller | High | POLISHED |
| /admin/homework, /notes | Content can read as database rows | Subject/class educational workflow | Content metadata rows | High | ACCEPTABLE |
| /admin/class-tests, /marks, /results, /{id}/edit | Dense assessment entry | Assessment identity and marks workflow | Marks table and ABS status | High | ACCEPTABLE |
| /admin/exams/create, /setup, /marks, /results | Fragmented multi-stage flow | Consistent setup-to-results workflow | Form sections and marks table | High | ACCEPTABLE |
| /admin/fees/collect | Search and balances competed visually | Financial collection workflow | Student finder, summary and CTA | High | POLISHED |
| /admin/fees/report, /defaulters, /delete | Generic financial tables | Totals, risk status and guarded actions | Financial metrics and table | High | POLISHED |
| /admin/fees/receipt, /receipt/{id}, /automation | Actions/settings lacked hierarchy | Receipt summary and billing policy | Summary banner and sections | High | ACCEPTABLE |
| /admin/salary/pay, /receipt, /receipt/{id} | Generic financial workflow | Payroll-grade summary and payment form | Financial metrics and receipt actions | High | ACCEPTABLE |
| /admin/accounts, /income/create, /expense/create, /statement | Generic ledger/forms | Compact accounting workflows | Currency alignment and filters | High | ACCEPTABLE |
| /admin/communication/messaging | Card rows did not feel like messaging | Inbox, conversation pane, bubbles, composer | Messaging layout | High | POLISHED |
| /admin/whatsapp | Provider-colored actions risked noise | Contextual outreach tools | Compact action and template panel | Medium | POLISHED |
| /admin/communication/sms-gateway, /live-class | Preview modules | Restrained honest capability state | Locked/setup state | Low | ACCEPTABLE |
| /admin/live-classes, /create, /{id}/edit | Needed action/state hierarchy | Upcoming/live/past class workspace | Live badges and Meet actions | High | POLISHED |
| /admin/integrations/google/connect, /callback | Third-party handoff | Clear connection/reconnect state | Integration status | High | ACCEPTABLE |
| /admin/reports/admissions, /contacts, /families, /student-register | Very wide datasets | Report center with contained scrolling | Masthead, filters and export | High | POLISHED |
| /admin/reports/student-attendance, /employee-attendance, /attendance-shortage | Dense attendance reports | Period and status analysis | Metrics, filters and status | High | POLISHED |
| /admin/reports/test-history, /academic, /progress | Mixed education reporting | Student/exam context | Report masthead and table | High | POLISHED |
| /admin/reports/fee-collection, /accounts, /salary | Numeric hierarchy was weak | Finance-oriented report center | Metrics and aligned currency | High | POLISHED |
| All /admin/reports/*/pdf routes | Print output differs from screens | Branded official documents | PDF masthead/footer/template | High | POLISHED |
| /admin/certificates and certificate PDF | Generic generation/list | Official document center | Form sections and document actions | Medium | ACCEPTABLE |
| /admin/roles, /{id}/edit | Permissions are intrinsically dense | Scannable role workspace | Permission fieldsets and actions | High | POLISHED |
| /admin/behaviour/traits, /rate, /skills, /observations and reports | Specialist data entry/reporting | Clear affective/psychomotor workflow | Rating matrix and report frame | Medium | ACCEPTABLE |
| /portal/student | Needed personal priorities | Student-specific dashboard | Identity and real metrics | High | POLISHED |
| /portal/student/profile, /attendance, /fees, /fees/{id}, /results | Portal data was table-heavy | Personal summaries and contained detail | Detail grid, metrics and statuses | High | POLISHED |
| /portal/student/homework, /notes | Educational content density | Subject-led content views | Content metadata rows | High | ACCEPTABLE |
| /portal/student/documents and document PDF routes | Download list lacked affordance | Official document center | Document rows and download action | High | POLISHED |
| /portal/student/live-classes, /{id}/join | Schedule needed urgency | Action-oriented secure class schedule | Live cards and join CTA | High | POLISHED |
| /portal/employee | Generic staff dashboard | Role-specific staff overview | Identity and real metrics | High | POLISHED |
| /portal/employee/profile, /attendance, /salary | Raw self-service views | Grouped profile and summaries | Detail grid, metrics and table | High | POLISHED |
| /portal/employee/classes, /homework, /documents and PDFs | Mixed work/document lists | Purposeful content and documents | Document cards and content rows | High | ACCEPTABLE |
| /portal/employee/teaching, /attendance, /homework, /notes | Needed teacher emphasis | Teaching command center and scoped tasks | Scope bar and task forms | High | POLISHED |
| /portal/employee/teaching/tests, /{id}/marks, /live-classes | Dense assessment/scheduling | Teacher marks and live-class workspace | Marks table and schedule cards | High | POLISHED |
| /portal/parent, /children/{id}, /profile | Needed immediate child context | Parent identity, switcher and child overview | Identity, child cards and metrics | High | POLISHED |
| /portal/messages, /portal/messages/{id} | Tables made messaging feel like CRUD | Responsive inbox/chat with bubbles | Portal messaging layout | High | POLISHED |
| /portal/account | Generic settings form | Focused account settings | Compact form and save footer | Medium | POLISHED |

## Final QA

- All 155 implemented GET routes were inventoried; parameterized routes are grouped when they share one renderer.
- Shared shell, form, table, status, finance, report, profile, messaging, document, modal, empty-state and responsive rules cover authenticated pages.
- Wide tables are contained locally; messaging, forms and actions recompose without body overflow.
- No fake records, notifications, statistics, trends or capabilities were introduced.
