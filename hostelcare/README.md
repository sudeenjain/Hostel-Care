# HostelCare

A multi-hostel complaint platform: PHP 8 + PDO, MySQL or SQLite, no framework, no external PHP libraries.
Built for the major project "Design Thinking: Hostel Complaint Management".

**The system ships empty.** No sample data, no demo users, no fake statistics.

## How it works

1. A **warden** opens the site, chooses "I run a hostel" and submits the hostel and personal details.
2. The warden gets an email to **confirm the address**. Only then does the request reach the platform administrator.
3. The **administrator** gets an email showing the request with only **Accept** and **Reject** (also available in the Requests page).
4. On accept, the warden gets an email with a **one-time link**. It opens a page to choose a password and enters the hostel's own dashboard.
5. The warden decides how complaints are collected: blocks, complaint categories (with target hours), extra questions (dropdown, short text, long text) and a directory of nearby service providers (name, trade, mobile).
6. The warden presses **Publish**. A **QR code** appears: print it, download it as PNG.
7. **Students scan the QR code.** They land on that hostel's page only (no other hostel is visible), sign in with their hostel ID or create an account, and fill that hostel's complaint form.

Wardens never see other hostels. The platform administrator sees hostel names, status and totals, never complaint content.

## Quick start (zero setup, SQLite)

Requirements: PHP 8.1+ with `pdo_sqlite`, `fileinfo`, `mbstring`, `ctype`, `openssl` (the installer lists anything missing).

    cd hostelcare
    php -S localhost:8000 -t public

Open http://localhost:8000. The installer creates the platform administrator and stores the site address.

### Important: the site address

Emailed links and QR codes contain the address saved at install time (`HC_PUBLIC_URL`, never taken from the request). If it is `localhost`, links only work on your own computer. For real use, host the site on an address students can reach (a college server, or any PHP hosting) and set:

    HC_PUBLIC_URL=https://care.yourcollege.edu

The QR page warns you when the address is local.

## Email (free, with Gmail)

Emails are required for the whole approval flow. With a Gmail account:

1. Turn on 2-Step Verification, then create an **App password** (Google Account > Security > App passwords).
2. Start the site with:

       HC_MAIL_ENABLED=1
       HC_MAIL_USER=yourname@gmail.com
       HC_MAIL_PASS=<the 16-character app password>
       HC_MAIL_FROM_NAME="HostelCare"

   (defaults: `smtp.gmail.com`, port 587, STARTTLS; any SMTP server works with `HC_MAIL_HOST`, `HC_MAIL_PORT`, `HC_MAIL_SECURE=tls|ssl`.)
3. Admin > Email > **Send a test email to me**.

Gmail allows roughly 500 messages a day, which is plenty for onboarding. Emails are saved first and retried up to three times; the text of sent emails is erased from the database because it contains one-time links.

**No mail server yet?** For development only, set `HC_MAIL_PREVIEW=1`; Admin > Email then shows the text of waiting emails so you can follow the links yourself. Never leave it on for a real site, because anyone with the administrator login could then read setup links.

## Production setup (MySQL)

1. `CREATE DATABASE hostelcare CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. Environment variables: `HC_DB_DRIVER=mysql HC_DB_HOST HC_DB_NAME HC_DB_USER HC_DB_PASS`, plus `HC_PUBLIC_URL` and the mail settings above.
3. Point the document root at `public/`; keep `config/`, `includes/`, `database/`, `storage/` outside it. Make `storage/` writable.
4. Open the site once; the installer creates the tables and the administrator. Serve over HTTPS.

If the app lives in a sub-folder set `HC_BASE_PATH`.

## Roles

| Role | Can do |
|---|---|
| Platform administrator | Approve or reject hostel requests, see hostel status and totals, suspend or reactivate a hostel, monitor email and SMS, add administrators. Cannot open any complaint |
| Owner warden | Everything in their own hostel: setup, categories, form questions, service providers, people, QR code, queue, analytics, export, confidential complaints |
| Assistant warden | Handle the queue of their hostel. Confidential complaints appear masked |
| Student | Scan the QR code, register, raise complaints with photos, track, comment, confirm and rate, reopen |

## Workflow

Submitted -> Acknowledged -> Assigned -> In progress -> Resolved -> Closed (or Reopened). The server rejects any other transition. Resolving needs a note. Resolved complaints close automatically after `rules.auto_close_days`; students may reopen within `rules.reopen_days`. Each category has target hours; an open complaint past its due time is overdue and listed first.

## Tests

    php tests/run_tests.php       # 104 service checks: onboarding, approval, tokens, publishing, tenant isolation
    php tests/run_mail_tests.php  # 17 email checks against a mock SMTP server
    php tests/run_sms_tests.php   # 28 SMS checks against a mock gateway
    bash tests/http_smoke.sh      # 100 end-to-end checks: the whole flow through the PHP built-in server

None touches your real database and none sends a real email or SMS. The QR code was also decoded from the rendered page with OpenCV and matched the hostel link.

## Security notes

Prepared statements, output escaping, CSRF tokens on every POST, password hashing, login throttling, session regeneration, validated image uploads outside the web root. Every query for hostel data is scoped by `hostel_id`. One-time links are 256-bit random tokens stored only as SHA-256 hashes, expire, and work once; opening an emailed link never changes anything by itself (a button POST does), so email scanners cannot approve a request. Links use the configured address, never the Host header. CSV formula neutralisation.

## Project layout

    config/      settings (environment overrides)
    database/    schema.mysql.sql, schema.sqlite.sql
    includes/    db, auth, csrf, hostels (onboarding), forms (custom questions), complaints, mailer, sms, notifications, layout
    public/      index, login, h.php (QR landing), register.php, hostel/ (request, confirm, setup), student/, warden/, admin/
    storage/     SQLite file and uploaded photos (never web-accessible)
    tests/       service, mail, SMS and HTTP tests with mock servers
    docs/        research templates, Jira backlog CSV, SMS setup, report builder and report (.docx)

## SMS (optional, free)

Off by default. Uses the open-source "SMS Gateway for Android" app on a phone you own. Users opt in under Profile; messages contain only ticket number and status. One gateway serves the whole platform, so on a shared platform every hostel's SMS leaves from the same SIM; per-hostel gateways are future work. Steps in `docs/SMS_SETUP.md`.

## Troubleshooting

- "could not find driver": enable `pdo_sqlite` in `php.ini` (remove the `;`), restart PHP, or use MySQL.
- **This version needs a fresh database.** The data model changed from one hostel to many. Delete `storage/db/hostelcare.sqlite` (or use a new MySQL database) and run the installer.
- Emails not arriving: Admin > Email shows each message's status and last error. Check the app password, and the spam folder.
- Students cannot open the QR link: the site address is local; see "the site address" above.

## Scheduled closing

Auto-closing runs when a dashboard or queue loads; no cron job needed.
