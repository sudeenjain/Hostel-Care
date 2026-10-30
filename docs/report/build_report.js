// Builds the major project report. Contains NO invented research data:
// every research result is a clearly marked fill-in box for the student's own findings.
// Run from the project root:  node docs/report/build_report.js
const fs = require("fs");
const path = require("path");
const {
  Document, Packer, Paragraph, TextRun, HeadingLevel, AlignmentType, Table, TableRow, TableCell,
  WidthType, ShadingType, BorderStyle, LevelFormat, TableOfContents, Footer, PageNumber,
} = require("docx");

const FONT = "Times New Roman";
const ROOT = path.resolve(__dirname, "../..");

const P = (t, o = {}) => new Paragraph({
  alignment: o.align || AlignmentType.JUSTIFIED, spacing: { after: 140, line: 320 },
  children: [new TextRun({ text: t, bold: o.bold, italics: o.italics, size: o.size || 24, font: FONT })],
});
const H1 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun({ text: t, font: FONT })] });
const H2 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun({ text: t, font: FONT })] });
const H3 = (t) => new Paragraph({ heading: HeadingLevel.HEADING_3, children: [new TextRun({ text: t, font: FONT })] });
const B = (t) => new Paragraph({ numbering: { reference: "bul", level: 0 }, spacing: { after: 80, line: 300 }, children: [new TextRun({ text: t, size: 24, font: FONT })] });
const N = (t) => new Paragraph({ numbering: { reference: "num", level: 0 }, spacing: { after: 80, line: 300 }, children: [new TextRun({ text: t, size: 24, font: FONT })] });
const gap = () => new Paragraph({ spacing: { after: 120 }, children: [] });
const caption = (t) => new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 80, after: 200 }, children: [new TextRun({ text: t, italics: true, size: 21, font: FONT })] });
// Yellow fill-in box: the student must replace this with real findings.
const FILL = (t) => new Paragraph({
  spacing: { before: 80, after: 160 }, shading: { type: ShadingType.CLEAR, fill: "FFF4D6" },
  border: { left: { style: BorderStyle.SINGLE, size: 18, color: "D98E04", space: 8 } },
  children: [new TextRun({ text: "FILL IN WITH YOUR OWN FINDINGS: " + t, italics: true, size: 22, font: FONT })],
});

const bd = { style: BorderStyle.SINGLE, size: 4, color: "999999" };
const borders = { top: bd, bottom: bd, left: bd, right: bd };
function table(widths, header, rows) {
  const total = widths.reduce((a, b) => a + b, 0);
  const cell = (txt, w, isH) => new TableCell({
    width: { size: w, type: WidthType.DXA }, borders,
    shading: isH ? { type: ShadingType.CLEAR, fill: "1F3864" } : undefined,
    margins: { top: 60, bottom: 60, left: 100, right: 100 },
    children: [new Paragraph({ children: [new TextRun({ text: String(txt), bold: isH, color: isH ? "FFFFFF" : "000000", size: 20, font: FONT })] })],
  });
  return new Table({
    width: { size: total, type: WidthType.DXA }, columnWidths: widths,
    rows: [
      new TableRow({ tableHeader: true, children: header.map((h, i) => cell(h, widths[i], true)) }),
      ...rows.map((r) => new TableRow({ children: r.map((c, i) => cell(c, widths[i], false)) })),
    ],
  });
}
const blank = (n) => Array.from({ length: n }, () => "");

// ---- read real facts from the project so the report matches the code ----
const read = (p) => fs.readFileSync(path.join(ROOT, p), "utf8");
const schema = read("database/schema.mysql.sql");
const tables = [...schema.matchAll(/CREATE TABLE (\w+) \(([\s\S]*?)\n\) ENGINE/g)].map((m) => ({
  name: m[1],
  cols: m[2].split("\n").map((l) => l.trim()).filter((l) => /^[a-z_]+ /.test(l) && !/^(INDEX|CONSTRAINT|PRIMARY|UNIQUE|FOREIGN)/i.test(l)).map((l) => l.split(" ")[0]),
}));
const backlog = read("docs/jira/hostelcare_backlog.csv").trim().split("\n").slice(1).map((l) => {
  const m = l.match(/("([^"]|"")*"|[^,]*)(,|$)/g).map((s) => s.replace(/,$/, "").replace(/^"|"$/g, ""));
  return { id: m[0], parent: m[1], type: m[2], summary: m[3], desc: m[4], pri: m[5] };
});
const epics = backlog.filter((b) => b.type === "Epic");
const stories = backlog.filter((b) => b.type === "Story");
const testCount = (read("tests/run_tests.php").match(/^check\(/gm) || []).length;
const smsTestCount = (read("tests/run_sms_tests.php").match(/^check\(/gm) || []).length;
const mailTestCount = (read("tests/run_mail_tests.php").match(/^check\(/gm) || []).length;
const httpCount = (read("tests/http_smoke.sh").match(/expect_(code|in|not_in) "|&& ok "[A-Za-z]/g) || []).length;

const c = [];
const add = (...x) => c.push(...x);

// ---------------- Title ----------------
add(
  new Paragraph({ spacing: { before: 1200 }, children: [] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 200 }, children: [new TextRun({ text: "MAJOR PROJECT REPORT", bold: true, size: 30, font: FONT })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 200, after: 200 }, children: [new TextRun({ text: "HostelCare: A Design Thinking Approach to Hostel Complaint Management", bold: true, size: 40, font: FONT, color: "1F3864" })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 600 }, children: [new TextRun({ text: "Design Thinking, Jira Scrum Management and a Working Multi-hostel PHP/MySQL Platform", italics: true, size: 26, font: FONT })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 60 }, children: [new TextRun({ text: "Submitted by", size: 24, font: FONT })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 60 }, children: [new TextRun({ text: "[Student Name]   |   [Register Number]", bold: true, size: 26, font: FONT })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 400 }, children: [new TextRun({ text: "[Degree / Branch]   |   [Academic Year]", size: 24, font: FONT })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { after: 60 }, children: [new TextRun({ text: "Under the guidance of [Guide Name], [Designation]", size: 24, font: FONT })] }),
  new Paragraph({ alignment: AlignmentType.CENTER, spacing: { before: 400 }, children: [new TextRun({ text: "[Institution Name]", bold: true, size: 28, font: FONT })] }),
);

add(
  new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun({ text: "How to use this report", font: FONT })] }),
  P("This report is a complete structure for the project. Everything about the system that was built (design, database, workflow, security, automated tests) is written from the actual source code and is accurate. Everything that must come from real people (interviews, survey results, usability scores, Jira screenshots) is left as a yellow FILL IN box. No research results have been invented."),
  P("Before submission: replace every yellow box with your real findings, delete this page, fill the bracketed placeholders on the title page, and right-click the table of contents to update it."),
);

add(
  new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun({ text: "Declaration", font: FONT })] }),
  P("I declare that this report titled \"HostelCare: A Design Thinking Approach to Hostel Complaint Management\" is a record of original work carried out by me under the guidance of [Guide Name]. It has not been submitted elsewhere for any degree or diploma. All research data presented was collected by me."),
  gap(), P("Place: [Place]                                                           Signature: ____________"), P("Date: [Date]                                                               [Student Name]"),
  new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun({ text: "Abstract", font: FONT })] }),
  P("Hostel residents depend on timely repair and support, yet complaints in many hostels travel through registers, phone calls and messaging groups where they are easily lost and cannot be tracked. This project applies the five stages of Design Thinking (Empathize, Define, Ideate, Prototype, Test) to understand the problem from the viewpoint of students, wardens and administrators, and builds HostelCare, a working multi-hostel web platform. A hostel warden registers the hostel, the platform administrator approves it by email, and the warden then designs how complaints are collected and shares a QR code. Students scan the code and reach only their own hostel."),
  P("The system is implemented in PHP with PDO, supports MySQL and SQLite, and provides role-based access for students, wardens and a platform administrator, with every hostel's data isolated from every other. It includes email-confirmed hostel registration, administrator approval by email, one-time setup links, a per-hostel complaint form with dropdown and text questions, a directory of nearby service providers and a printable QR code for students. It includes a validated complaint workflow with a full history, category-based target times with automatic overdue detection, confidential complaints, photo evidence with server-side validation, in-app notifications, a staff assignment process, a student rating step, analytics calculated only from stored records, and CSV export. Project planning used Jira Scrum with " + epics.length + " epics and " + stories.length + " user stories. Students and staff who opt in also receive SMS status updates through a free open-source Android gateway running on the hostel's own phone, with retries, a daily limit and an administrator log. The implementation is verified by " + testCount + " automated service tests, " + smsTestCount + " SMS tests against a mock gateway, " + mailTestCount + " email tests against a mock SMTP server and a " + httpCount + "-step end-to-end HTTP test that walks the whole flow from hostel request to a student's complaint and checks that hostels cannot see each other."),
  P("Research findings and usability results reported in Chapters 4, 5 and 9 are the author's own measurements. Keywords: Design Thinking, Hostel Management, Complaint Management, Jira, Scrum, PHP, Usability.", { italics: true }),
  new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun({ text: "Table of Contents", font: FONT })] }),
  new TableOfContents("Contents", { hyperlink: true, headingStyleRange: "1-2" }),
);

// ---------------- Ch1 ----------------
add(
  H1("Chapter 1: Introduction"),
  H2("1.1 Background"),
  P("A hostel is a student's home for several years. When a tap leaks, the Wi-Fi fails or the food is unsafe, residents need a fast and trustworthy way to report it and to see that someone is acting. Where complaints are handled informally, there is no receipt, no status and no escalation, and management has no record from which to learn."),
  H2("1.2 Problem Statement"),
  P("Hostel complaints are handled through informal, untraceable channels. This causes delays, lost or repeated requests, low trust, and no data for administrators to detect recurring problems or measure service."),
  FILL("one paragraph describing the hostel you studied: its size, number of blocks, how many residents, and how complaints are handled there today (from your own observation)."),
  H2("1.3 Objectives"),
  B("Understand the pain points of students, wardens and administrators through primary research."),
  B("Define needs through personas, problem statements and How Might We questions."),
  B("Plan and track the work in Jira using Scrum."),
  B("Build a working, secure complaint management system."),
  B("Verify the system with automated tests and evaluate usability with real users."),
  H2("1.4 Scope"),
  P("Included: web application for three roles, complaint lifecycle, notifications inside the application, analytics, export. Not included: SMS or push notifications, integration with the institution's ERP, native mobile apps, and payment. These are listed as future work."),
  H2("1.5 Report Organisation"),
  P("Chapter 2 reviews existing approaches, Chapter 3 states the methodology, Chapters 4 to 6 cover Empathize, Define and Ideate, Chapter 7 covers Jira, Chapters 8 and 9 cover the built prototype and its testing, and Chapter 10 concludes."),
);

// ---------------- Ch2 ----------------
add(
  H1("Chapter 2: Review of Existing Approaches"),
  table([2300, 3300, 3426], ["Method", "Strengths", "Weaknesses"], [
    ["Paper register", "No technology needed", "No tracking, easy to lose, no analytics, no privacy"],
    ["Verbal request to warden", "Fast for emergencies", "No record, depends on memory"],
    ["Messaging groups", "Familiar and quick", "Messages buried, public exposure, no status or assignment"],
    ["Email to office", "Written record", "Slow, not categorised or prioritised"],
    ["General helpdesk tools", "Full tracking", "Built for IT support, heavy for hostel staff"],
  ]),
  caption("Table 2.1: Common approaches (from general knowledge; confirm for your hostel in Chapter 4)"),
  H2("2.1 Gap"),
  P("Informal methods are unreliable; general tools are too complex. A simple, role-based system that students can use in a minute, wardens can manage from one queue, and administrators can analyse, fills the gap."),
  FILL("add 3 to 5 real references you actually read (papers, hostel management systems, or helpdesk tools) and one sentence on what each does or lacks. Cite in your institution's format."),
);

// ---------------- Ch3 ----------------
add(
  H1("Chapter 3: Methodology"),
  H2("3.1 Design Thinking Stages"),
  table([1700, 3400, 3926], ["Stage", "Purpose", "Output in this project"], [
    ["Empathize", "Understand users through observation and interviews", "Interview notes, survey data, empathy maps"],
    ["Define", "Frame the real problem", "Personas, problem statements, HMW questions"],
    ["Ideate", "Generate and select solutions", "Idea list, prioritisation, user flows, workflow design"],
    ["Prototype", "Build something testable", "Working HostelCare application"],
    ["Test", "Validate with users", "Task tests, SUS score, issue list"],
  ]),
  caption("Table 3.1: Design Thinking stages and outputs"),
  H2("3.2 Tools"),
  table([2200, 6826], ["Tool", "Use"], [
    ["Design Thinking", "Method to discover, frame and validate the solution"],
    ["Jira (Scrum)", "Backlog, sprints, board, burndown"],
    ["PHP 8 with PDO", "Server-side application"],
    ["MySQL / SQLite", "Data storage; SQLite for zero-setup demo, MySQL for production"],
    ["HTML, CSS, JavaScript", "Responsive interface"],
    ["Google Forms", "Resident survey"],
    ["Figma (optional)", "Screens and flows for presentation"],
  ]),
  caption("Table 3.2: Tools"),
  H2("3.3 Ethics"),
  P("Interviews began with a spoken consent statement. Participants were identified only by codes (P1, W1, A1). The survey was anonymous. No real complaint data from the live hostel is included in this report."),
);

// ---------------- Ch4 Empathize ----------------
add(
  H1("Chapter 4: Stage 1 - Empathize"),
  H2("4.1 Research Plan"),
  table([2300, 2400, 4326], ["Method", "Target", "Purpose"], [
    ["Interviews", "At least 5 students, all wardens, 1 administrator", "How complaints are raised and handled today"],
    ["Survey", "As many residents as can be reached", "How common each problem is"],
    ["Observation", "Warden office for several hours", "Real workflow and record keeping"],
  ]),
  caption("Table 4.1: Research plan (interview guide and survey are in the docs/research folder)"),
  H2("4.2 Participants Actually Interviewed"),
  table([1500, 2500, 2000, 3026], ["Code", "Role / year / block", "Date", "Duration"], [["P1", "", "", ""], ["P2", "", "", ""], ["P3", "", "", ""], ["P4", "", "", ""], ["P5", "", "", ""], ["W1", "", "", ""], ["A1", "", "", ""]]),
  caption("Table 4.2: Interview participants"),
  FILL("complete Table 4.2 with the real participants."),
  H2("4.3 Interview Findings"),
  table([1500, 7526], ["Code", "Exact quote or observation (from your note sheet)"], [["P1", ""], ["P2", ""], ["P3", ""], ["W1", ""], ["A1", ""]]),
  caption("Table 4.3: Interview findings"),
  FILL("paste real quotes word for word. Do not paraphrase to sound better."),
  H2("4.4 Survey Results"),
  P("Number sent: [__]. Number of responses: [__]. Response rate: [__]%."),
  table([4400, 1300, 3326], ["Question", "Count / n", "Percent"], [
    ["Faced a hostel problem in the last month", "", ""], ["Received acknowledgement", "", ""], ["Knew the status of the most recent complaint", "", ""],
    ["Resolved within 3 days", "", ""], ["Chose not to report because nothing would happen", "", ""], ["Would use a phone-friendly system", "", ""],
  ]),
  caption("Table 4.4: Survey results (copy from the Google Forms summary)"),
  FILL("enter the real counts. Add a chart of problem categories exported from Google Forms."),
  H2("4.5 Observation Notes"),
  FILL("what you saw in the warden's office: how complaints arrive, where they are written, how staff are told, what gets forgotten."),
  H2("4.6 Empathy Map"),
  table([2000, 7026], ["Quadrant", "Student (from your evidence)"], [["Says", ""], ["Thinks", ""], ["Does", ""], ["Feels", ""]]),
  caption("Table 4.5: Student empathy map"),
  table([2000, 7026], ["Quadrant", "Warden (from your evidence)"], [["Says", ""], ["Thinks", ""], ["Does", ""], ["Feels", ""]]),
  caption("Table 4.6: Warden empathy map"),
);

// ---------------- Ch5 Define ----------------
add(
  H1("Chapter 5: Stage 2 - Define"),
  H2("5.1 Personas"),
  P("Build each persona from patterns that appeared in several real interviews. A persona is a composite; label it as such."),
  table([2200, 6826], ["Attribute", "Student persona"], [["Name (invented, composite)", ""], ["Year / block", ""], ["Goals", ""], ["Frustrations (cite P-codes)", ""]]),
  gap(),
  table([2200, 6826], ["Attribute", "Warden persona"], [["Name (invented, composite)", ""], ["Responsibilities", ""], ["Goals", ""], ["Frustrations (cite W-codes)", ""]]),
  gap(),
  table([2200, 6826], ["Attribute", "Administrator persona"], [["Name (invented, composite)", ""], ["Goals", ""], ["Frustrations (cite A-codes)", ""]]),
  FILL("complete the three persona tables from your evidence."),
  H2("5.2 Problem Statements"),
  P("Template: [Persona] needs [need] because [insight from your research]."),
  FILL("write three problem statements, one per persona, each grounded in a quote or survey figure from Chapter 4."),
  H2("5.3 How Might We Questions"),
  FILL("write 5 to 7 HMW questions that follow from the problem statements."),
  H2("5.4 Requirements"),
  P("The system's requirements below are what HostelCare implements. In the Source column, name the finding from Chapter 4 that justifies each one. If no finding supports a requirement, say so honestly or remove it."),
  table([700, 4326, 2000, 2000], ["ID", "Requirement", "Implemented in", "Supporting finding"], [
    ["R1", "Student registers a complaint with category, location, description and photos", "student/new.php", ""],
    ["R2", "Every complaint receives a unique ticket number", "complaints.php", ""],
    ["R3", "Student sees status and a full timeline", "student/view.php", ""],
    ["R4", "Warden queue sorted by overdue, priority and age", "warden/queue.php", ""],
    ["R5", "Warden assigns staff, updates status, adds notes", "warden/view.php", ""],
    ["R6", "Target time per category with automatic overdue flag", "categories, complaints", ""],
    ["R7", "Student confirms fix and rates resolution; can reopen", "student/view.php", ""],
    ["R8", "Confidential complaints hidden from everyone except the owner warden of that hostel", "complaints.php", ""],
    ["R9", "Analytics per hostel from stored data only", "warden/analytics.php", ""],
    ["R10", "In-app notifications on every change", "notifications.php", ""],
    ["R11", "Optional SMS to the student's mobile number when status changes", "includes/sms.php, profile.php", ""],
  ]),
  caption("Table 5.1: Requirements and traceability"),
);

// ---------------- Ch6 Ideate ----------------
add(
  H1("Chapter 6: Stage 3 - Ideate"),
  H2("6.1 Idea Generation"),
  FILL("list the ideas you and others actually brainstormed (photograph the sticky notes or board and insert it as a figure)."),
  H2("6.2 Prioritisation"),
  table([3600, 1500, 1500, 2426], ["Idea", "Impact", "Effort", "Decision"], [...blank(5).map(() => ["", "", "", ""])]),
  caption("Table 6.1: Impact versus effort (fill with your ideas)"),
  H2("6.3 Complaint Lifecycle Designed"),
  P("The workflow below is enforced by the code. A transition not in the table is rejected by the server, so the history is always valid."),
  table([1900, 3900, 3226], ["Status", "Allowed next statuses", "Who can trigger it"], [
    ["Submitted", "Acknowledged, Assigned, In progress, Resolved", "Created by student; moved by warden"],
    ["Acknowledged", "Assigned, In progress, Resolved", "Warden"],
    ["Assigned", "In progress, Resolved", "Warden"],
    ["In progress", "Resolved", "Warden"],
    ["Resolved", "Closed, Reopened", "Student confirms (Closed) or reopens; automatic close after the configured days"],
    ["Closed", "Reopened", "Student, within the reopen window"],
    ["Reopened", "Acknowledged, Assigned, In progress, Resolved", "Warden"],
  ]),
  caption("Table 6.2: Status workflow (source: includes/complaints.php, status_flow)"),
  H2("6.4 Design Rules Chosen"),
  B("Target hours belong to the category and are set by the hostel's warden; due date = creation time + category hours."),
  B("Resolving requires a note, so the student always learns what was done."),
  B("Resolved complaints close automatically after a configurable number of days with no reply; a student may reopen within a configurable window."),
  B("Confidential complaints are visible to the student and the hostel's owner warden only; assistant wardens see a masked entry and the platform administrator sees nothing."),
  B("Internal notes are visible to staff only."),
  B("Analytics show nothing until real complaints exist, and never estimate."),
);

// ---------------- Ch7 Jira ----------------
add(
  H1("Chapter 7: Project Management with Jira"),
  H2("7.1 Setup"),
  P("A Scrum project with key HCM was created. The backlog in docs/jira/hostelcare_backlog.csv was imported through Jira's CSV import (Issue Id, Parent Id, Issue Type, Summary, Description, Priority, Labels). Workflow: To Do, In Progress, In Review, Done. Estimation: story points."),
  H2("7.2 Epics"),
  table([700, 2800, 5526], ["No.", "Epic", "Goal"], epics.map((e, i) => [String(i + 1), e.summary, e.desc])),
  caption("Table 7.1: Epics (from the imported backlog)"),
  H2("7.3 User Stories"),
  table([600, 5226, 1700, 1500], ["No.", "Story", "Epic", "Priority"], stories.map((s, i) => [String(i + 1), s.summary, (epics.find((e) => e.id === s.parent) || {}).summary || "", s.pri])),
  caption("Table 7.2: User stories (story points and sprint are assigned in Jira during planning)"),
  H2("7.4 Sprints"),
  table([900, 2400, 3500, 1100, 1126], ["Sprint", "Dates", "Goal", "Committed", "Completed"], [["1", "", "", "", ""], ["2", "", "", "", ""], ["3", "", "", "", ""], ["4", "", "", "", ""]]),
  caption("Table 7.3: Sprint record"),
  FILL("enter your real sprint dates, goals and story points from Jira. Insert real screenshots: backlog, sprint board, burndown chart and velocity chart."),
  H2("7.5 Definition of Done"),
  B("Acceptance criteria met and demonstrated in the running application."),
  B("Automated tests pass."),
  B("Reviewed by the guide or a peer."),
  H2("7.6 Retrospective"),
  FILL("one honest paragraph per sprint: what went well, what did not, what you changed."),
);

// ---------------- Ch8 Prototype / system ----------------
add(
  H1("Chapter 8: Stage 4 - Prototype: The HostelCare System"),
  H2("8.1 Architecture"),
  P("HostelCare is a server-rendered PHP 8 application using PDO. All requests enter through pages in public/, which load a shared bootstrap from includes/. The includes folder holds the database layer, authentication, CSRF protection, complaint services, notifications and layout. Business rules live in complaints.php so that the pages stay thin and the rules can be tested without a web server. The storage folder holds the SQLite file and uploaded photos and is outside the public web root."),
  table([2600, 6426], ["Folder / file", "Responsibility"], [
    ["config/config.php", "Settings, with environment variable overrides"],
    ["database/", "Schemas for MySQL and SQLite"],
    ["includes/db.php", "PDO connection, query helpers, installer"],
    ["includes/auth.php", "Login, throttling, roles, user creation"],
    ["includes/csrf.php", "Form token creation and verification"],
    ["includes/complaints.php", "Lifecycle, permissions, uploads, analytics, export"],
    ["includes/hostels.php", "Hostel request, email confirmation, approval, setup link, publishing, QR token"],
    ["includes/forms.php", "Per-hostel complaint form questions (dropdown, text, long text)"],
    ["includes/mailer.php", "Email outbox with retries and a small SMTP client"],
    ["includes/notifications.php", "In-app notifications, SMS and warden alerts"],
    ["public/hostel/", "Hostel registration request, email confirmation, owner setup"],
    ["public/h.php", "Landing page of a scanned QR code"],
    ["public/student/", "Dashboard, new complaint, complaint view"],
    ["public/warden/", "Setup checklist, categories, form builder, service providers, people, QR, queue, analytics, export"],
    ["public/admin/", "Requests, decision link, hostels, email, SMS, administrators"],
    ["tests/", "Service, email, SMS and HTTP end-to-end tests with mock servers"],
  ]),
  caption("Table 8.1: Project structure"),
  H2("8.2 Database Design"),
  P("The schema has " + tables.length + " tables. Foreign keys protect integrity; complaint history is stored in complaint_updates so every status change records who, when, from what and to what."),
  table([2600, 6426], ["Table", "Columns"], tables.map((t) => [t.name, t.cols.join(", ")])),
  caption("Table 8.2: Tables and columns (generated from database/schema.mysql.sql)"),
  H2("8.3 Roles and Permissions"),
  table([1800, 7226], ["Role", "Can do"], [
    ["Student", "Scan the QR code, register, raise complaints with photos, view only own complaints, comment, confirm and rate, reopen within the window"],
    ["Owner warden", "Everything inside their own hostel: setup, categories, form questions, service providers, people, QR code, queue, analytics, export, confidential complaints"],
    ["Assistant warden", "Handle the hostel's queue; confidential complaints appear masked"],
    ["Platform administrator", "Approve or reject hostel requests, see status and totals only, suspend or reactivate hostels, monitor email and SMS; cannot open any complaint"],
  ]),
  caption("Table 8.3: Roles"),
  H2("8.4 Security Measures Implemented"),
  table([2800, 6226], ["Measure", "Implementation"], [
    ["SQL injection", "All queries use prepared statements (PDO, emulation off)"],
    ["Cross-site scripting", "All output escaped through one helper function"],
    ["CSRF", "Per-session token required on every POST; sign-out only by POST"],
    ["Password storage", "password_hash with the default algorithm; plain passwords never stored"],
    ["Brute force", "Login blocked after repeated failures within a time window, even with the right password"],
    ["Session fixation", "Session id regenerated on login"],
    ["File upload", "Real MIME and image check, size and count limits, random stored names, stored outside web root, served only to permitted users"],
    ["Access control", "Role check on every page; ownership and confidentiality check on every complaint and photo"],
    ["CSV injection", "Cells beginning with = + - @ are neutralised in the export"],
    ["Tenant isolation", "Every hostel table carries hostel_id and every query filters on it; checked by automated cross-hostel tests"],
    ["One-time links", "256-bit random tokens stored only as SHA-256 hashes; expire; single use; an emailed link only shows a page and a button POST performs the action, so email scanners cannot approve anything"],
    ["Link poisoning", "Links in emails and QR codes use the configured site address, never the request Host header"],
    ["Email privacy", "Text of sent emails is erased from the database because it contains one-time links"],
  ]),
  caption("Table 8.4: Security measures"),
  H2("8.5 SMS Notifications"),
  P("Students told us how they would like to be informed (see your Chapter 4 findings); the system supports SMS to the mobile number in the user's profile. SMS is optional, switched off by default, and sent only to users who tick the SMS box. To keep the project free, messages are sent through the open-source application SMS Gateway for Android installed on a phone owned by the hostel office, which sends ordinary SMS from its SIM card. HostelCare calls the application's REST interface (POST /3rdparty/v1/messages with basic authentication)."),
  table([2800, 6226], ["Design point", "Implementation"], [
    ["Consent", "Opt-in checkbox at registration and in Profile; unticked by default"],
    ["Privacy", "Text contains only the ticket number and the new status. Titles and descriptions are never sent, so confidential complaints cannot leak by SMS"],
    ["Reliability", "Messages are stored in an outbox table first; temporary failures are retried up to three times; permanent errors (invalid number, wrong password) fail at once with the reason saved"],
    ["Recovery", "A send interrupted half way is detected and retried; messages older than a day expire rather than arrive late"],
    ["Limits", "A configurable daily cap (default 90) protects the SIM from the operator's per-day SMS limit"],
    ["Number handling", "Numbers are validated and stored in international format (+91...)"],
    ["Visibility", "Administrator page shows counts, last messages (numbers masked), errors, a test button and a retry button"],
    ["Testing", "A mock gateway that mimics the app (success, 400, 401, 500, flaky and offline cases) lets all of this be tested without sending a real SMS"],
  ]),
  caption("Table 8.5: SMS channel design"),
  H2("8.6 Hostel Onboarding Flow"),
  P("A hostel moves through a fixed set of states: unverified (request saved, email not yet confirmed), pending (email confirmed, waiting for the administrator), approved (setup link sent), active (published) and rejected or suspended. The server allows only these moves. Requests whose email is never confirmed expire after 48 hours and are removed."),
  table([800, 3000, 5226], ["Step", "Who", "What happens"], [
    ["1", "Warden", "Submits hostel details (name, type, address, blocks, rooms, residents, phone) and personal details (name, designation, email, mobile) and declares authority to register the hostel"],
    ["2", "System", "Emails a confirmation link to the warden. Nothing reaches the administrator yet, so mistyped or fake addresses cost the administrator nothing"],
    ["3", "Warden", "Opens the link and presses Confirm. The request becomes pending"],
    ["4", "System", "Emails the administrator the request details with only Accept and Reject buttons"],
    ["5", "Administrator", "Decides from the email link or from the Requests page; the other route stops working once one decision is made"],
    ["6", "System", "On accept, emails the warden a single-use link valid for seven days; on reject, emails the warden with the note"],
    ["7", "Warden", "Sets a password and enters the hostel's own dashboard as its owner"],
    ["8", "Warden", "Adds blocks and categories (required), custom form questions and service providers (optional), then presses Publish"],
    ["9", "System", "Creates a random QR token and shows the QR code with print and PNG download"],
  ]),
  caption("Table 8.6: Hostel onboarding"),
  H2("8.7 Complaint Form and Service Provider Directory"),
  P("Each warden designs what students are asked. The standard fields (category, title, block, room or place, description, priority, photos, confidential switch) are always present. On top of these the warden can add up to 15 questions of three types: dropdown (two to 30 options), short text and long text, each optional or required, reorderable and hideable. Answers are stored with the complaint and shown to the warden next to the standard details. A submitted dropdown value must be one of the listed options, which the server checks."),
  P("The warden also keeps a directory of nearby service providers, such as plumbers and electricians, with name, trade, mobile number and a note. A complaint can be assigned to a provider so work is tracked, and the number is one tap away on a phone. Providers do not log in."),
  H2("8.8 QR Access for Students"),
  P("The QR code contains the address of the hostel's landing page with a random 128-bit token. The page shows only that hostel's name and a sign-in form; students sign in with their hostel ID or create an account, choosing their block from the warden's list. An unknown or old token returns a plain not-found page, so the page cannot be used to discover other hostels. The warden can regenerate the token, after which printed old codes stop working. The generated image was decoded with an image library and matched the hostel's link exactly."),
  H2("8.9 Keeping Hostels Apart"),
  P("The most serious risk of a shared platform is one hostel reading another's data. Every table that holds hostel data has a hostel_id column; student and warden queries always filter on it; a student can open only their own complaints; a warden can open only complaints of their own hostel; the platform administrator can open none. Hostel IDs are unique only inside a hostel, so two hostels may both have a student CS101. The automated tests try the forbidden actions on purpose (opening another hostel's complaint, assigning another hostel's provider, using another hostel's category, signing in with the wrong hostel context, reading another hostel's export) and check that each is refused."),
  H2("8.10 Installation and First Run"),
  P("The system ships empty. On first visit it redirects to the installer, where the platform administrator is created and the public site address is stored for use in emails and QR codes. The installer locks itself once an account exists. Hostels, categories and form questions are created later by the wardens themselves. No sample complaints, users or statistics are included anywhere in the project."),
  H2("8.11 Screens"),
  FILL("insert screenshots of the running system: home page, hostel request form, administrator request page, warden setup checklist, form builder, service providers, QR page, student sign-in from the QR code, new complaint with the hostel's own questions, warden queue. Take them after entering your own test data in a separate test database."),
  H2("8.12 Design Decisions Linked to Research"),
  table([3600, 5426], ["Finding from Chapter 4", "Design response in HostelCare"], blank(4).map(() => ["", ""])),
  FILL("map at least four real findings to features, for example a finding about not knowing status to the timeline view."),
);

// ---------------- Ch9 Test ----------------
add(
  H1("Chapter 9: Stage 5 - Test"),
  H2("9.1 Automated Verification"),
  P("Four automated suites were written and run. They use temporary databases and mock servers and do not touch real data, send real email or send real SMS."),
  table([2800, 1400, 4826], ["Suite", "Checks", "What it verifies"], [
    ["tests/run_tests.php", String(testCount), "Hostel request validation, email confirmation, hashed one-time tokens, approval and rejection, setup link single use, publishing rules, QR token, custom form questions, per-hostel logins, complaint creation with custom answers, tenant isolation of complaints, queues, providers, analytics and exports, confidential complaints, suspension and expiry"],
    ["tests/run_mail_tests.php", String(mailTestCount), "SMTP login, message format, one-time link arriving intact, erasure of sent text, refused login, refused recipient, retries and giving up when the server is down"],
    ["tests/run_sms_tests.php", String(smsTestCount), "Phone number validation, opt-in rules, privacy of SMS text, delivery to a mock gateway, retry on 500, permanent failure on 400 and 401, offline phone, daily cap, expiry, crash recovery, disabled mode"],
    ["tests/http_smoke.sh", String(httpCount), "Installer lock, the full flow through the web interface from hostel request to approval, setup, configuration, publishing, QR landing, student registration, a complaint with custom answers, resolution, CSV export, cross-hostel access attempts, QR regeneration, suspension"],
  ]),
  caption("Table 9.1: Automated test suites (all checks passed at the time of writing)"),
  P("Re-run before submission: php tests/run_tests.php, php tests/run_mail_tests.php, php tests/run_sms_tests.php and bash tests/http_smoke.sh. Paste the real terminal output as a figure."),
  FILL("if you connected a real phone to the SMS gateway, add a photo of the SMS received on your phone and the administrator SMS page showing it as sent. Only claim real delivery if you actually did this."),
  H2("9.2 Usability Test"),
  P("The usability plan, tasks and recording sheet are in docs/research/usability_test_plan.md. Test with real users on the running system."),
  table([1500, 2200, 1500, 1300, 2526], ["Task", "Participant codes", "Completed without help", "Avg. seconds", "Problems observed"], [["T1", "", "", "", ""], ["T2", "", "", "", ""], ["T3", "", "", "", ""], ["T4", "", "", "", ""], ["T5", "", "", "", ""], ["T6", "", "", "", ""], ["T7", "", "", "", ""], ["T8", "", "", "", ""]]),
  caption("Table 9.2: Task results"),
  FILL("enter the real results from your recording sheet."),
  H2("9.3 System Usability Scale"),
  P("SUS scoring: for odd items subtract 1 from the answer; for even items subtract the answer from 5; add the ten values and multiply by 2.5. The script docs/research/sus_score.py performs this calculation."),
  FILL("participants: [n]. Mean SUS: [score from the script]. Paste the script output. State plainly that the sample is small."),
  H2("9.4 Issues Found and Changes Made"),
  table([4000, 1400, 3626], ["Issue observed", "Users affected", "Change made (only real changes)"], blank(4).map(() => ["", "", ""])),
  FILL("list the real problems you saw and what you changed afterwards."),
  H2("9.5 Limitations"),
  B("The usability sample is small and from one hostel."),
  B("One SMS gateway phone serves every hostel on the platform, so all hostels' SMS leave from one SIM and share its daily limit."),
  B("Emailed links and QR codes work for other people only when the site is hosted on an address they can reach."),
  B("SMS depends on a phone staying on and connected; it is a low-cost solution for one hostel, not a replacement for a commercial gateway at large scale."),
  B("Real email and SMS delivery were verified only if you tested with a real Gmail account and a real phone; automated tests use mock servers."),
  B("Long-term effect on resolution time cannot be measured without a pilot."),
  FILL("add any limitations specific to your study."),
);

// ---------------- Ch10 ----------------
add(
  H1("Chapter 10: Conclusion and Future Scope"),
  H2("10.1 Conclusion"),
  FILL("three to five sentences stating, with your real numbers, whether the objectives in Chapter 1 were met and what testing showed."),
  P("Technically, the project delivered a secure, tested complaint management system whose rules (workflow, permissions, deadlines, privacy) are enforced by the server rather than left to convention, and whose analytics are computed only from recorded events."),
  H2("10.2 Future Scope"),
  B("Pilot in one block and compare resolution time and satisfaction before and after, using the system's own export."),
  B("WhatsApp or Telegram notifications; SMS and an email option already exist and are switched off by default."),
  B("A separate SMS gateway per hostel, and WhatsApp or Telegram notifications."),
  B("QR codes on room doors to pre-fill the location."),
  B("Searching nearby service providers automatically from a map service instead of the warden's own directory."),
  B("Repeat-issue detection by room and category."),
  B("Multilingual interface."),
  B("Integration with the institution's student records."),
);

add(
  new Paragraph({ heading: HeadingLevel.HEADING_1, pageBreakBefore: true, children: [new TextRun({ text: "References", font: FONT })] }),
  P("List only sources you actually read. Suggested starting points:"),
  N("Hasso Plattner Institute of Design at Stanford. An Introduction to Design Thinking Process Guide."),
  N("IDEO.org. The Field Guide to Human-Centered Design."),
  N("Brooke, J. (1996). SUS: A quick and dirty usability scale."),
  N("Schwaber, K. and Sutherland, J. The Scrum Guide."),
  N("Atlassian. Jira Software documentation."),
  N("The PHP Group. PHP Manual: PDO, password_hash, file uploads."),
  N("OWASP. Cheat Sheet Series: CSRF Prevention, File Upload, Password Storage."),
  FILL("add the references you cited in Chapter 2 and format them in your institution's style."),
);

const doc = new Document({
  creator: "Student", title: "HostelCare Major Project Report",
  styles: {
    default: { document: { run: { font: FONT, size: 24 } } },
    paragraphStyles: [
      { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true, run: { size: 34, bold: true, font: FONT, color: "1F3864" }, paragraph: { spacing: { before: 240, after: 240 }, outlineLevel: 0 } },
      { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true, run: { size: 28, bold: true, font: FONT, color: "2E4A7D" }, paragraph: { spacing: { before: 280, after: 140 }, outlineLevel: 1 } },
      { id: "Heading3", name: "Heading 3", basedOn: "Normal", next: "Normal", quickFormat: true, run: { size: 25, bold: true, italics: true, font: FONT }, paragraph: { spacing: { before: 200, after: 100 }, outlineLevel: 2 } },
    ],
  },
  numbering: { config: [
    { reference: "bul", levels: [{ level: 0, format: LevelFormat.BULLET, text: "•", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 720, hanging: 360 } } } }] },
    { reference: "num", levels: [{ level: 0, format: LevelFormat.DECIMAL, text: "%1.", alignment: AlignmentType.LEFT, style: { paragraph: { indent: { left: 720, hanging: 360 } } } }] },
  ] },
  sections: [{
    properties: { page: { size: { width: 11906, height: 16838 }, margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 } } },
    footers: { default: new Footer({ children: [new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ children: ["Page ", PageNumber.CURRENT], size: 20, font: FONT })] })] }) },
    children: c,
  }],
});
const out = path.join(ROOT, "docs/report/HostelCare_Major_Project_Report.docx");
Packer.toBuffer(doc).then((b) => { fs.writeFileSync(out, b); console.log("wrote", out, "| tables:", tables.length, "| epics:", epics.length, "| stories:", stories.length, "| service checks:", testCount); });
