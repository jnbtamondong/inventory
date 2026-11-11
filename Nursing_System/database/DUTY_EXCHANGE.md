# Exchange of Duty

The module is available through **EXCHANGE OF DUTY** in Nursing and Monthly Scheduling sidebars. It uses the existing authenticated `users.user_id` and `nursing_schedule_months` published rosters. No sample requests or duties are installed.

Schema installation is additive and idempotent: `C:\xampp\php\php.exe database\install_duty_exchange.php`. The page and API also ensure the schema exists. Notifications reuse `nursing_document_notifications`, linked to exchange requests by `nursing_exchange_notifications`.

An assignment reference is `(schedule ID, employee ID, duty date, published version)`, because the existing scheduler stores assignments inside versioned JSON. Only working duties in a **PUBLISHED** roster with no draft revision can be selected. Both employees must be active, in the same department and duty qualification category, listed in the destination published roster, and eligible for its 8/12-hour pattern. Exchanges can cross dates and months. Approved roster absences and recorded attendance leave intervals block conflicting swaps. Minimum rest uses the stricter affected-roster rule; staffing minimum counts are not enforced.

Partner confirmation, Head Nurse, Supervisor Nurse, and Chief Nurse approval are mandatory. Participants cannot approve their own exchanges. Super Admin configures module permissions and independent approver assignments; it cannot replace an approval role. Explicit approver assignments override default role/department routing for that stage. A returned or failed request restarts confirmation and approvals when corrected. Drafts and terminal requests are retained.

Submission and decisions serialize through an InnoDB mutex and lock roster rows in ID order. Cell reservations prevent conflicting pending requests. Every action checks the authenticated actor and optimistic request version. Final validation checks the original roster version and duties again. Roster updates, immutable amendments, revisions, reservations, audit events and notifications commit transactionally. An apply failure rolls roster writes back to a savepoint and records `UPDATE_FAILED` for administrator review.

Approved exchanges update both `draft_json` and `final_json`, retain original snapshots in amendments, and increment versions. Calendar View, List View and hour summaries use the same schedule reader. Amended cells show their exchange reference. Monthly save validation rejects overwrites of amended cells; regeneration is blocked for an amended month. Manual edits to other duties remain available through the existing revision workflow. Existing monthly approval steps are unchanged.

Tests use disposable databases and synthetic accounts/rosters: `C:\xampp\php\php.exe tests\duty-exchange.test.php`. No hospital requests or roster records are modified by tests. Test database credentials may be supplied with `EXCHANGE_TEST_HOST`, `EXCHANGE_TEST_USER`, and `EXCHANGE_TEST_PASSWORD`.
