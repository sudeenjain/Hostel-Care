<?php
require __DIR__ . '/../../includes/bootstrap.php';
$user = require_role('warden');

function csv_cell($v): string
{
    $v = (string) $v;
    // Spreadsheet programs run text starting with these characters as formulas; a leading apostrophe makes it plain text.
    if ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) {
        $v = "'" . $v;
    }
    return $v;
}

$from = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('from')) ? get('from') : null;
$to = preg_match('/^\d{4}-\d{2}-\d{2}$/', get('to')) ? get('to') : null;
if (get('download') === '1') {
    $rows = export_rows($user, $from, $to);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="complaints-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Ticket', 'Created', 'Category', 'Title', 'Location', 'Priority', 'Status', 'Student', 'Provider', 'Resolved', 'Rating']);
    foreach ($rows as $r) {
        fputcsv($out, array_map('csv_cell', [ticket_no((int) $r['id']), $r['created_at'], $r['category'], $r['title'], $r['location'], $r['priority'], status_label($r['status']), $r['student_name'], $r['staff_name'] ?? '', $r['resolved_at'] ?? '', $r['rating'] ?? '']));
    }
    fclose($out);
    exit;
}
page_header('Export', $user);
?>
<h1>Export complaints</h1>
<div class="card narrow">
  <form method="get" class="stack">
    <input type="hidden" name="download" value="1">
    <label>From<input type="date" name="from"></label>
    <label>To<input type="date" name="to"></label>
    <button class="btn" type="submit">Download CSV</button>
  </form>
  <p class="muted">Confidential complaints are masked unless you are the owner warden.</p>
</div>
<?php page_footer(); ?>
