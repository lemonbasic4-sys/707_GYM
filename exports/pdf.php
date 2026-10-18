<?php
/**
 * 707 GYM - PDF Export Generator
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

requireAuth();

$pdo = Database::getConnection();
$type = $_GET['type'] ?? 'members';

// Check if vendor autoloader exists (for Dompdf)
$vendorAutoload = BASE_PATH . '/vendor/autoload.php';
$hasDompdf = file_exists($vendorAutoload);

if ($hasDompdf) {
    require_once $vendorAutoload;
}

// Generate report HTML
$title = ucfirst($type) . ' Report - 707 GYM';
$date = date('F d, Y H:i:s');

ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= $title ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        .header { border-bottom: 2px solid #FF4D00; padding-bottom: 10px; margin-bottom: 20px; display: flex; justify-content: space-between; }
        .title { font-size: 20px; font-weight: bold; color: #111; margin: 0; }
        .subtitle { color: #666; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th { background: #f4f4f4; text-align: left; padding: 8px; border-bottom: 2px solid #ddd; font-weight: bold; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 3px 6px; border-radius: 4px; font-size: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1 class="title">707 GYM</h1>
            <div class="subtitle"><?= $title ?> &bull; Generated: <?= $date ?></div>
        </div>
    </div>

    <?php if ($type === 'members'): ?>
        <?php
        $members = $pdo->query("SELECT m.* FROM members m ORDER BY m.created_at DESC LIMIT 100")->fetchAll();
        ?>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><?= htmlspecialchars($m['first_name'] . ' ' . $m['last_name']) ?></td>
                        <td><?= htmlspecialchars($m['phone'] ?? '-') ?></td>
                        <td><?= ucfirst($m['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($type === 'payments'): ?>
        <?php
        $payments = $pdo->query("SELECT p.*, m.first_name, m.last_name FROM payments p JOIN members m ON p.member_id = m.id ORDER BY p.payment_date DESC LIMIT 100")->fetchAll();
        ?>
        <table>
            <thead>
                <tr>
                    <th>Tx ID</th>
                    <th>Type</th>
                    <th>Date</th>
                    <th>Member</th>
                    <th>Method</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $total = 0; foreach ($payments as $p): $total += $p['amount']; ?>
                    <tr>
                        <td><?= htmlspecialchars($p['transaction_id']) ?></td>
                        <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $p['transaction_type']))) ?></td>
                        <td><?= htmlspecialchars($p['payment_date']) ?></td>
                        <td><?= htmlspecialchars($p['first_name'] . ' ' . $p['last_name']) ?></td>
                        <td><?= ucfirst($p['payment_method']) ?></td>
                        <td class="text-right"><?= formatPhilippineCurrency($p['amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <th colspan="5" class="text-right">Total:</th>
                    <th class="text-right"><?= formatPhilippineCurrency($total) ?></th>
                </tr>
            </tbody>
        </table>
    <?php else: ?>
        <p>Report overview for system records.</p>
    <?php endif; ?>
</body>
</html>
<?php
$html = ob_get_clean();

if ($hasDompdf && class_exists('Dompdf\Dompdf')) {
    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $dompdf->stream("707_GYM_{$type}_" . date('Ymd') . ".pdf", ["Attachment" => true]);
} else {
    // Printable HTML fallback if composer packages not yet installed
    echo $html;
    echo '<script>window.print();</script>';
}
exit;
