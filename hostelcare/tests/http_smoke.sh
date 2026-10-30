#!/usr/bin/env bash
# End-to-end HTTP test of the whole multi-hostel flow against PHP's built-in server and a throwaway SQLite database.
# Run from the project root:  bash tests/http_smoke.sh
set -u
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PORT="${PORT:-8099}"
BASE="http://127.0.0.1:$PORT"
TMP="$(mktemp -d)"
export HC_DB_DRIVER=sqlite HC_SQLITE_PATH="$TMP/test.sqlite" HC_PUBLIC_URL="$BASE" HC_MAIL_ENABLED=0 HC_MAIL_PREVIEW=0
php -S "127.0.0.1:$PORT" -t "$ROOT/public" >"$TMP/server.log" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null; rm -rf "$TMP"; find "$ROOT/storage/uploads" -type f ! -name .gitkeep -delete 2>/dev/null' EXIT
sleep 1

PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); echo "  PASS  $1"; }
bad()  { FAIL=$((FAIL+1)); echo "  FAIL  $1   ($2)"; }
expect_code() { [ "$2" = "$3" ] && ok "$1" || bad "$1" "expected $3, got $2"; }
expect_in()   { grep -qi -- "$3" "$2" && ok "$1" || { bad "$1" "text not found: $3"; [ -n "${DEBUG:-}" ] && sed 's/<[^>]*>/ /g' "$2" | tr -s ' \n' | head -c 500; echo; }; }
expect_not_in(){ grep -qi -- "$3" "$2" && bad "$1" "unexpected text: $3" || ok "$1"; }

token() { grep -o 'name="_csrf" value="[^"]*"' "$1" | head -1 | sed 's/.*value="//;s/"$//'; }
get()  { curl -s -b "$1" -c "$1" -o "$TMP/out.html" -w "%{http_code}" "$BASE/$2"; }
getl() { curl -s -L -b "$1" -c "$1" -o "$TMP/out.html" -w "%{http_code}" "$BASE/$2"; }
post_form() {
  local jar="$1" path="$2" tokpage="$3"; shift 3
  curl -s -b "$jar" -c "$jar" -o "$TMP/tok.html" "$BASE/$tokpage"
  local tok; tok="$(token "$TMP/tok.html")"
  local args=(--data-urlencode "_csrf=$tok")
  for kv in "$@"; do args+=(--data-urlencode "$kv"); done
  curl -s -L -b "$jar" -c "$jar" -o "$TMP/out.html" -w "%{http_code}" "${args[@]}" "$BASE/$path"
}
# Reads the newest queued email to $1 containing path $2 and prints the 64-hex token from its link.
mail_token() {
  php -r '$p=new PDO("sqlite:".getenv("HC_SQLITE_PATH"));$s=$p->prepare("SELECT body_text FROM mail_outbox WHERE to_email=? AND body_text LIKE ? ORDER BY id DESC");$s->execute([$argv[1],"%".$argv[2]."%"]);$b=$s->fetchColumn();if($b&&preg_match("~".preg_quote($argv[2],"~")."\?t=([a-f0-9]{64})~",$b,$m))echo $m[1];' "$1" "$2"
}
db_val() { php -r '$p=new PDO("sqlite:".getenv("HC_SQLITE_PATH"));echo $p->query($argv[1])->fetchColumn();' "$1"; }
register_hostel() { # jar name email
  post_form "$1" hostel/register.php hostel/register.php name="$2" type=boys address="12 College Road" city=Mangaluru state=Karnataka pincode=575001 total_blocks=2 total_rooms=40 resident_count=80 hostel_phone= owner_name="Warden of $2" owner_designation=Warden owner_email="$3" owner_phone=9876543210 declare=1
}
approve_and_setup() { # hostel-id owner-email jar
  local t; t="$(mail_token "$2" hostel/verify.php)"
  curl -s -b "$3" -c "$3" -o "$TMP/out.html" "$BASE/hostel/verify.php?t=$t"
  post_form "$3" hostel/verify.php "hostel/verify.php?t=$t" t="$t" >/dev/null
}

echo "HostelCare HTTP tests"; echo "====================="
ADMIN="$TMP/admin.jar"; WA="$TMP/wa.jar"; WB="$TMP/wb.jar"; SA="$TMP/sa.jar"; SB="$TMP/sb.jar"; ANON="$TMP/anon.jar"; MAILER="$TMP/mailer.jar"

echo; echo "Installer and public pages"
c=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/"); expect_code "fresh system redirects to installer" "$c" 302
c=$(post_form "$ADMIN" install.php install.php name="Platform Admin" email=admin@test.local password=adminpass123 site="$BASE")
expect_code "installer completes" "$c" 200
expect_in "installer signs the admin in" "$TMP/out.html" "Hostels on the platform"
c=$(getl "$ANON" install.php); expect_in "installer locks itself after setup" "$TMP/out.html" "Sign in"
c=$(get "$ANON" ""); expect_code "landing page opens" "$c" 200
expect_in "landing offers the warden path" "$TMP/out.html" "run a hostel"
expect_in "landing offers the student path" "$TMP/out.html" "student"
c=$(get "$ANON" student/dashboard.php); expect_code "anonymous user redirected from student area" "$c" 302
c=$(get "$ANON" admin/dashboard.php);   expect_code "anonymous user redirected from admin area" "$c" 302
c=$(get "$ANON" warden/queue.php);      expect_code "anonymous user redirected from warden area" "$c" 302
c=$(get "$ANON" register.php);          expect_in "student sign-up without a QR code explains to scan" "$TMP/out.html" "QR code"
c=$(get "$ANON" "h.php?c=00000000000000000000000000000000"); expect_code "unknown QR code gives 404" "$c" 404
expect_not_in "unknown QR code reveals no hostel" "$TMP/out.html" "Sunrise"

echo; echo "Hostel A: request, confirmation, approval"
c=$(curl -s -b "$ANON" -o /dev/null -w "%{http_code}" --data "name=X" "$BASE/hostel/register.php"); expect_code "request POST without CSRF token rejected" "$c" 419
c=$(register_hostel "$WA" "Sunrise Boys Hostel" warden.a@test.local); expect_code "hostel request submitted" "$c" 200
expect_in "warden told to check email" "$TMP/out.html" "Check your email"
expect_in "confirmation email queued for the warden" <(db_val "SELECT body_text FROM mail_outbox WHERE to_email='warden.a@test.local'") "hostel/verify.php?t="
[ "$(db_val "SELECT COUNT(*) FROM mail_outbox WHERE to_email='admin@test.local'")" = "0" ] && ok "admin not emailed before the warden confirms" || bad "admin emailed too early" "-"
c=$(post_form "$WA" hostel/register.php hostel/register.php name="" owner_email=bad); expect_in "invalid request shows errors" "$TMP/out.html" "Enter"
VT="$(mail_token warden.a@test.local hostel/verify.php)"
c=$(get "$ANON" "hostel/verify.php?t=$VT"); expect_code "confirmation page opens" "$c" 200
[ "$(db_val "SELECT status FROM hostels WHERE owner_email='warden.a@test.local'")" = "unverified" ] && ok "opening the link alone changes nothing (scanner-safe)" || bad "status changed by GET" "-"
c=$(post_form "$MAILER" hostel/verify.php "hostel/verify.php?t=$VT" t="$VT"); expect_in "pressing confirm moves it to the administrator" "$TMP/out.html" "administrator"
[ "$(db_val "SELECT status FROM hostels WHERE owner_email='warden.a@test.local'")" = "pending" ] && ok "request is pending" || bad "not pending" "-"
DT="$(mail_token admin@test.local admin/decision.php)"
[ -n "$DT" ] && ok "administrator emailed a decision link" || bad "no decision email" "-"
c=$(get "$ANON" "admin/decision.php?t=$DT"); expect_code "decision page opens without signing in" "$c" 200
expect_in "decision page shows the hostel" "$TMP/out.html" "Sunrise Boys Hostel"
expect_in "decision page offers Accept" "$TMP/out.html" "Accept"
expect_in "decision page offers Reject" "$TMP/out.html" "Reject"
expect_not_in "decision page shows no other data" "$TMP/out.html" "password"
[ "$(db_val "SELECT status FROM hostels WHERE owner_email='warden.a@test.local'")" = "pending" ] && ok "opening the decision link alone does not approve" || bad "approved by GET" "-"
c=$(get "$ANON" "admin/decision.php?t=${DT:0:63}0"); expect_in "wrong decision token is refused" "$TMP/out.html" "not valid"
c=$(post_form "$ANON" admin/decision.php "admin/decision.php?t=$DT" t="$DT" action=accept note=Welcome); expect_in "accepting from the email link works" "$TMP/out.html" "accepted"
c=$(post_form "$ANON" admin/decision.php "admin/decision.php?t=$DT" t="$DT" action=reject); expect_in "the link cannot decide a second time" "$TMP/out.html" "not valid"
[ "$(db_val "SELECT status FROM hostels WHERE owner_email='warden.a@test.local'")" = "approved" ] && ok "hostel is approved" || bad "not approved" "-"

echo; echo "Hostel A: owner opens the dashboard"
ST="$(mail_token warden.a@test.local hostel/setup.php)"
[ -n "$ST" ] && ok "warden emailed a setup link" || bad "no setup email" "-"
c=$(get "$ANON" "hostel/setup.php?t=$ST"); expect_code "setup page opens" "$c" 200
c=$(post_form "$WA" hostel/setup.php "hostel/setup.php?t=$ST" t="$ST" password=short password2=short); expect_in "short password refused" "$TMP/out.html" "at least"
c=$(post_form "$WA" hostel/setup.php "hostel/setup.php?t=$ST" t="$ST" password=wardenpass1 password2=wardenpass1); expect_in "warden lands on the setup checklist" "$TMP/out.html" "Set up Sunrise Boys Hostel"
c=$(getl "$ANON" "hostel/setup.php?t=$ST"); expect_in "setup link works only once" "$TMP/out.html" "not valid"
c=$(getl "$WA" warden/qr.php); expect_in "no QR before publishing (sent back to setup)" "$TMP/out.html" "Set up"
c=$(get "$WA" admin/dashboard.php); expect_code "warden blocked from platform admin" "$c" 403
c=$(post_form "$WA" warden/setup.php warden/setup.php); expect_in "publish refused while required steps are missing" "$TMP/out.html" "Finish this step first"

echo; echo "Hostel A: configure"
c=$(post_form "$WA" warden/hostel.php warden/hostel.php name="Sunrise Boys Hostel" address="12 College Road" hostel_phone= blocks=$'Ganga\nYamuna' registration_open=1); expect_in "blocks saved" "$TMP/out.html" "Hostel details saved"
c=$(post_form "$WA" warden/categories.php warden/categories.php action=add name=Plumbing default_priority=high sla_hours=24); expect_in "category added" "$TMP/out.html" "Category added"
c=$(post_form "$WA" warden/categories.php warden/categories.php action=add name=plumbing default_priority=high sla_hours=24); expect_in "duplicate category refused" "$TMP/out.html" "already have"
c=$(post_form "$WA" warden/form.php warden/form.php action=save label="Is it inside the room?" field_type=dropdown options=$'Yes\nNo' is_required=1); expect_in "dropdown question added" "$TMP/out.html" "Question added"
c=$(post_form "$WA" warden/form.php warden/form.php action=save label="Broken" field_type=dropdown options="One"); expect_in "dropdown with one option refused" "$TMP/out.html" "at least two"
c=$(post_form "$WA" warden/form.php warden/form.php action=save label="Anything else we should know?" field_type=text); expect_in "text question added" "$TMP/out.html" "Question added"
c=$(post_form "$WA" warden/staff.php warden/staff.php action=add name="Ravi Kumar" trade=Plumber phone=9812345678 note="5 minutes away"); expect_in "service provider added" "$TMP/out.html" "Added to your service providers"
expect_in "provider mobile shown" "$TMP/out.html" "9812345678"
c=$(post_form "$WA" warden/staff.php warden/staff.php action=add name=Bad trade=Plumber phone=123); expect_in "invalid provider mobile refused" "$TMP/out.html" "valid mobile"
c=$(post_form "$WA" warden/setup.php warden/setup.php); expect_in "publish succeeds and opens the QR page" "$TMP/out.html" "Your QR code"
QR="$(db_val "SELECT qr_token FROM hostels WHERE owner_email='warden.a@test.local'")"
[ ${#QR} = 32 ] && ok "QR token generated" || bad "QR token" "$QR"
expect_in "QR page carries the student link" "$TMP/out.html" "h.php?c=$QR"
expect_in "QR image is drawn by the local script" "$TMP/out.html" "assets/js/qrcode.js"
c=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/assets/js/qrcode.js"); expect_code "QR library is served locally" "$c" 200

echo; echo "Hostel B (isolation)"
register_hostel "$WB" "Lakeview Girls Hostel" warden.b@test.local >/dev/null
VB="$(mail_token warden.b@test.local hostel/verify.php)"; post_form "$MAILER" hostel/verify.php "hostel/verify.php?t=$VB" t="$VB" >/dev/null
DB="$(mail_token admin@test.local admin/decision.php)"
c=$(getl "$ADMIN" admin/requests.php); expect_in "admin sees the pending request in the dashboard" "$TMP/out.html" "Lakeview Girls Hostel"
IDB="$(db_val "SELECT id FROM hostels WHERE owner_email='warden.b@test.local'")"
c=$(post_form "$ADMIN" admin/requests.php admin/requests.php id="$IDB" action=accept note=); expect_in "admin accepts from the dashboard" "$TMP/out.html" "Accepted"
SB_T="$(mail_token warden.b@test.local hostel/setup.php)"
post_form "$WB" hostel/setup.php "hostel/setup.php?t=$SB_T" t="$SB_T" password=wardenpass2 password2=wardenpass2 >/dev/null
post_form "$WB" warden/hostel.php warden/hostel.php name="Lakeview Girls Hostel" address="9 Lake Road" hostel_phone= blocks="East" registration_open=1 >/dev/null
post_form "$WB" warden/categories.php warden/categories.php action=add name=Electrical default_priority=medium sla_hours=48 >/dev/null
post_form "$WB" warden/setup.php warden/setup.php >/dev/null
QRB="$(db_val "SELECT qr_token FROM hostels WHERE owner_email='warden.b@test.local'")"
[ ${#QRB} = 32 ] && [ "$QRB" != "$QR" ] && ok "hostel B is live with its own QR code" || bad "hostel B not live" "$QRB"

echo; echo "Students scan the QR code"
c=$(get "$ANON" "h.php?c=$QR"); expect_code "QR landing opens" "$c" 200
expect_in "QR landing names the hostel" "$TMP/out.html" "Sunrise Boys Hostel"
expect_not_in "QR landing shows no other hostel" "$TMP/out.html" "Lakeview"
c=$(get "$ANON" "register.php?c=$QR"); expect_in "registration offers only this hostel's blocks" "$TMP/out.html" "Yamuna"
expect_not_in "registration does not show another hostel's block" "$TMP/out.html" ">East<"
c=$(post_form "$SA" register.php "register.php?c=$QR" c="$QR" name="Asha Rao" login_id=cs101 email=asha@test.local phone= block=Zzz room=101 password=studentpass1 password2=studentpass1); expect_in "block outside the hostel list refused" "$TMP/out.html" "Choose your block"
c=$(post_form "$SA" register.php "register.php?c=$QR" c="$QR" name="Asha Rao" login_id=cs101 email=asha@test.local phone= block=Ganga room=101 password=studentpass1 password2=studentpass1); expect_in "student registers and lands on the complaint form" "$TMP/out.html" "Raise a complaint"
expect_in "form shows the hostel's own question" "$TMP/out.html" "Is it inside the room?"
expect_in "form lists this hostel's category" "$TMP/out.html" "Plumbing"
expect_not_in "form does not list another hostel's category" "$TMP/out.html" "Electrical"
c=$(post_form "$SB" register.php "register.php?c=$QRB" c="$QRB" name="Bina Nair" login_id=CS101 email=bina@test.local phone= block=East room=7 password=studentpass2 password2=studentpass2); expect_in "same hostel ID registers in hostel B" "$TMP/out.html" "Raise a complaint"
c=$(get "$SA" warden/queue.php);    expect_code "student blocked from warden queue" "$c" 403
c=$(get "$SA" admin/dashboard.php); expect_code "student blocked from admin area" "$c" 403
c=$(get "$SA" warden/export.php);   expect_code "student blocked from export" "$c" 403
c=$(curl -s -b "$SA" -o /dev/null -w "%{http_code}" --data "title=x" "$BASE/student/new.php"); expect_code "complaint POST without CSRF token rejected" "$c" 419
c=$(post_form "$SA" student/new.php student/new.php category_id="$(db_val "SELECT id FROM categories WHERE name='Plumbing'")" title="Tap is leaking" description="The corridor tap leaks all night." priority=high block=Ganga place="Room 101"); expect_in "required custom question enforced" "$TMP/out.html" "is required"
FID="$(db_val "SELECT id FROM hostel_fields WHERE label LIKE 'Is it%'")"
c=$(post_form "$SA" student/new.php student/new.php category_id="$(db_val "SELECT id FROM categories WHERE name='Plumbing'")" title="Tap is leaking" description="The corridor tap leaks all night." priority=high block=Ganga place="Room 101" "f_$FID=Yes"); expect_in "complaint registered" "$TMP/out.html" "Tap is leaking"
expect_in "answer shown to the student" "$TMP/out.html" "Is it inside the room?"
CID="$(db_val "SELECT id FROM complaints LIMIT 1")"

echo; echo "Isolation over HTTP"
c=$(get "$SB" "student/view.php?id=$CID"); [ "$c" = 403 ] || [ "$c" = 404 ] && ok "student of B cannot open A's complaint ($c)" || bad "student B opened A's complaint" "$c"
c=$(get "$WB" "warden/view.php?id=$CID"); [ "$c" = 403 ] || [ "$c" = 404 ] && ok "warden of B cannot open A's complaint ($c)" || bad "warden B opened A's complaint" "$c"
c=$(get "$WB" warden/queue.php); expect_not_in "queue of B shows nothing of A" "$TMP/out.html" "Tap is leaking"
c=$(get "$WA" warden/queue.php); expect_in "queue of A shows the complaint" "$TMP/out.html" "Tap is leaking"
c=$(get "$ADMIN" "warden/view.php?id=$CID"); expect_code "platform admin cannot open a complaint" "$c" 403
c=$(getl "$ADMIN" admin/dashboard.php); expect_not_in "platform admin dashboard shows no complaint text" "$TMP/out.html" "Tap is leaking"
c=$(get "$WA" "warden/view.php?id=$CID"); expect_in "owner warden opens it and sees the custom answer" "$TMP/out.html" "Is it inside the room?"
expect_in "provider offered for assignment" "$TMP/out.html" "Ravi Kumar"
c=$(post_form "$WA" "warden/view.php?id=$CID" "warden/view.php?id=$CID" action=assign staff_id="$(db_val "SELECT id FROM staff LIMIT 1")" note=); expect_in "provider assigned" "$TMP/out.html" "assigned"
c=$(post_form "$WA" "warden/view.php?id=$CID" "warden/view.php?id=$CID" action=status to=resolved note="Fixed the washer"); expect_in "warden resolves the complaint" "$TMP/out.html" "Status updated"
c=$(get "$SA" notifications.php); expect_code "student sees notifications" "$c" 200

echo; echo "Exports and QR safety"
c=$(curl -s -b "$WA" -o "$TMP/export.csv" -w "%{http_code}" "$BASE/warden/export.php?download=1"); expect_code "owner downloads CSV" "$c" 200
expect_in "CSV has the complaint" "$TMP/export.csv" "Tap is leaking"
c=$(curl -s -b "$WB" -o "$TMP/exportb.csv" -w "%{http_code}" "$BASE/warden/export.php?download=1"); expect_not_in "CSV of hostel B has nothing of A" "$TMP/exportb.csv" "Tap is leaking"
c=$(post_form "$WA" warden/qr.php warden/qr.php); expect_in "new QR code can be made" "$TMP/out.html" "New QR code created"
c=$(get "$ANON" "h.php?c=$QR"); expect_code "the old QR code stops working" "$c" 404

echo; echo "Suspension and email page"
c=$(post_form "$ADMIN" "admin/hostel.php?id=$IDB" "admin/hostel.php?id=$IDB" action=suspend);
 expect_in "admin suspends hostel B" "$TMP/out.html" "suspended"
c=$(get "$WB" warden/queue.php); [ "$c" = 302 ] || [ "$c" = 403 ] && ok "suspended hostel warden is locked out ($c)" || bad "warden B still in" "$c"
c=$(get "$ANON" "h.php?c=$QRB"); expect_code "suspended hostel QR shows nothing" "$c" 404
c=$(get "$ADMIN" admin/mail.php); expect_in "admin email page opens" "$TMP/out.html" "Not switched on"
expect_not_in "email page shows no one-time links" "$TMP/out.html" "decision.php?t="
c=$(post_form "$ADMIN" "admin/hostel.php?id=$IDB" "admin/hostel.php?id=$IDB" action=reactivate); expect_in "admin reactivates the hostel" "$TMP/out.html" "reactivated"
c=$(post_form "$ANON" logout.php login.php); [ "$c" = 200 ] && ok "sign out works" || bad "sign out" "$c"

[ "$FAIL" -gt 0 ] && [ -n "${DEBUG:-}" ] && grep -E "hostel.php" "$TMP/server.log" | tail -12
echo; echo "$PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
