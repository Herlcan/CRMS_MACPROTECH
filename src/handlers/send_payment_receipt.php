<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

header('Content-Type: application/json');

require_once '../db/connection.php';
require_once 'config.php';
require_once 'payment_schema.php';

require_once __DIR__ . '/../../vendor/PHPMailer-master/src/Exception.php';
require_once __DIR__ . '/../../vendor/PHPMailer-master/src/PHPMailer.php';
require_once __DIR__ . '/../../vendor/PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

function money(float $amount): string
{
    return 'PHP ' . number_format($amount, 2);
}

function html_text(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function receipt_row(string $label, float $amount, bool $bold = false): string
{
    $label = html_text($label);
    $amountText = money($amount);
    $left = $bold ? "<strong>{$label}</strong>" : $label;
    $right = $bold ? "<strong>{$amountText}</strong>" : $amountText;

    return "<tr><td>{$left}</td><td style=\"text-align:right;\">{$right}</td></tr>";
}

$response = ['success' => false, 'message' => 'Unknown error'];

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        throw new Exception('Unauthorized');
    }

    ensure_payment_detail_columns($conn);
    ensure_items_inventory_columns($conn);

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $paymentId = isset($_POST['payment_id']) ? intval($_POST['payment_id']) : 0;
    if ($paymentId <= 0) {
        throw new Exception('Invalid payment ID');
    }

    $paymentQuery = mysqli_prepare($conn, "
        SELECT
            p.*,
            wo.code AS work_order_code,
            wo.unit_type,
            wo.brand,
            wo.model,
            wo.prob_find,
            wo.status AS work_order_status,
            CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
            c.email AS customer_email,
            CONCAT(u.first_name, ' ', u.last_name) AS technician_name
        FROM payments p
        LEFT JOIN work_order wo ON p.work_order_id = wo.id
        LEFT JOIN client c ON wo.client_id = c.id
        LEFT JOIN users u ON wo.technician_id = u.id
        WHERE p.id = ?
        LIMIT 1
    ");

    if (!$paymentQuery) {
        throw new Exception('Database error: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($paymentQuery, "i", $paymentId);
    mysqli_stmt_execute($paymentQuery);
    $paymentResult = mysqli_stmt_get_result($paymentQuery);
    $payment = mysqli_fetch_assoc($paymentResult);
    mysqli_stmt_close($paymentQuery);

    if (!$payment) {
        throw new Exception('Payment record not found');
    }

    if (empty($payment['customer_email'])) {
        throw new Exception('Customer email is missing');
    }

    $workOrderId = (int) $payment['work_order_id'];
    $costs = get_payment_costs($conn, $workOrderId);
    $totalRefunded = get_total_refunded($conn, $paymentId);
    $grossTotal = (float) $costs['gross_total'];
    $discount = (float) ($payment['discount_amount'] ?? 0);
    $amountPaid = (float) ($payment['amount_paid'] ?? 0);
    $netTotal = max(0, $grossTotal - $discount);
    $computed = calculate_payment_status($netTotal, $amountPaid, $totalRefunded);

    $parts = [];
    $partsQuery = mysqli_prepare($conn, "
        SELECT
            pi.quantity,
            COALESCE(i.product_code, '') AS product_code,
            COALESCE(i.brand_name, 'Unknown Item') AS product_name,
            COALESCE(i.model, '') AS product_model,
            COALESCE(i.average_price, 0) AS product_price
        FROM purchased_item pi
        LEFT JOIN items i ON pi.product_id = i.id
        WHERE pi.work_order_id = ?
        ORDER BY pi.id ASC
    ");

    if ($partsQuery) {
        mysqli_stmt_bind_param($partsQuery, "i", $workOrderId);
        mysqli_stmt_execute($partsQuery);
        $partsResult = mysqli_stmt_get_result($partsQuery);
        $parts = mysqli_fetch_all($partsResult, MYSQLI_ASSOC);
        mysqli_stmt_close($partsQuery);
    }

    $partsRows = '';
    if ($parts) {
        foreach ($parts as $part) {
            $qty = (float) $part['quantity'];
            $price = (float) $part['product_price'];
            $item = trim(($part['product_name'] ?? '') . ' ' . ($part['product_model'] ?? ''));
            if ($item === '') {
                $item = $part['product_code'] ?: 'Item';
            }

            $partsRows .= '<tr>'
                . '<td>' . html_text($item) . '</td>'
                . '<td style="text-align:right;">' . number_format($qty, 0) . '</td>'
                . '<td style="text-align:right;">' . money($price) . '</td>'
                . '<td style="text-align:right;">' . money($qty * $price) . '</td>'
                . '</tr>';
        }
    } else {
        $partsRows = '<tr><td colspan="4" style="text-align:center;color:#6b7280;">No purchased items</td></tr>';
    }

    $device = trim(($payment['brand'] ?? '') . ' ' . ($payment['model'] ?? ''));
    if ($device === '') {
        $device = $payment['unit_type'] ?? '';
    }

    $referenceHtml = '';
    if (!empty($payment['reference_number'])) {
        $referenceHtml = '<div><strong>Reference #:</strong> ' . html_text($payment['reference_number']) . '</div>';
    }

    $body = '
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                body { font-family: Arial, sans-serif; color: #111827; line-height: 1.5; }
                .wrap { max-width: 760px; margin: 0 auto; }
                .header { border-bottom: 2px solid #111827; padding-bottom: 16px; margin-bottom: 18px; }
                h2, h3 { margin: 0 0 12px; }
                .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 22px; margin-bottom: 18px; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
                th, td { border-bottom: 1px solid #e5e7eb; padding: 9px; }
                th { text-align: left; background: #f8fafc; }
                .right { text-align: right; }
                .total { font-weight: 700; }
                .muted { color: #6b7280; }
            </style>
        </head>
        <body>
            <div class="wrap">
                <div class="header">
                    <h2>MACPROTECH Payment Receipt</h2>
                    <div>Payment Code: ' . html_text($payment['payment_code']) . '</div>
                    <div>Date: ' . html_text(date('F j, Y')) . '</div>
                </div>
                <p>Dear <strong>' . html_text($payment['customer_name']) . '</strong>,</p>
                <p>Please see your payment receipt below.</p>
                <div class="grid">
                    <div><strong>Work Order:</strong> ' . html_text($payment['work_order_code']) . '</div>
                    <div><strong>Device:</strong> ' . html_text($device) . '</div>
                    <div><strong>Technician:</strong> ' . html_text($payment['technician_name']) . '</div>
                    <div><strong>Repair Status:</strong> ' . html_text($payment['work_order_status']) . '</div>
                    <div><strong>Payment Method:</strong> ' . html_text($payment['payment_method'] ?: 'Cash') . '</div>
                    <div><strong>Payment Status:</strong> ' . html_text($computed['payment_status']) . '</div>
                    ' . $referenceHtml . '
                </div>
                <h3>Cost Breakdown</h3>
                <table>
                    ' . receipt_row('Diagnostic Fee', (float) $costs['diagnostic_fee']) . '
                    ' . receipt_row('Work Order Cost', (float) $costs['work_order_cost']) . '
                    ' . receipt_row('Purchased Parts', (float) $costs['purchased_parts_total']) . '
                    ' . receipt_row('Total', $grossTotal, true) . '
                </table>
                <h3>Purchased Items</h3>
                <table>
                    <tr><th>Item</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Subtotal</th></tr>
                    ' . $partsRows . '
                </table>
                <h3>Payment Summary</h3>
                <table>
                    ' . receipt_row('Total', $grossTotal) . '
                    ' . receipt_row('Discount', $discount) . '
                    ' . receipt_row('Amount Due', $netTotal) . '
                    ' . receipt_row('Paid', $amountPaid) . '
                    ' . receipt_row('Refunded', $totalRefunded) . '
                    ' . receipt_row('Actual Paid', (float) $computed['actual_paid']) . '
                    ' . receipt_row('Change', (float) $computed['change_amount']) . '
                    ' . receipt_row('Remaining', (float) $computed['remaining_balance'], true) . '
                </table>
                <p class="muted">Thank you for trusting Macprotech Computer Repair Services.</p>
            </div>
        </body>
        </html>
    ';

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) SMTP_PORT;

    $mail->setFrom(SMTP_USER, 'Macprotech Computer Repair');
    $mail->addAddress($payment['customer_email'], $payment['customer_name'] ?: 'Customer');
    $mail->isHTML(true);
    $mail->Subject = 'MACPROTECH Payment Receipt - ' . ($payment['payment_code'] ?: 'Payment');
    $mail->Body = $body;
    $mail->AltBody = "MACPROTECH Payment Receipt\n"
        . "Payment Code: " . ($payment['payment_code'] ?? '') . "\n"
        . "Work Order: " . ($payment['work_order_code'] ?? '') . "\n"
        . "Total: " . money($grossTotal) . "\n"
        . "Paid: " . money($amountPaid) . "\n"
        . "Refunded: " . money($totalRefunded) . "\n"
        . "Remaining: " . money((float) $computed['remaining_balance']) . "\n"
        . "Status: " . $computed['payment_status'];

    $mail->send();

    $response = [
        'success' => true,
        'message' => 'Receipt emailed successfully'
    ];
} catch (MailException $e) {
    error_log('Receipt email failed: ' . $e->getMessage());
    $response = ['success' => false, 'message' => 'Failed to send receipt email. Please check SMTP settings.'];
} catch (Exception $e) {
    $response = ['success' => false, 'message' => $e->getMessage()];
}

echo json_encode($response);
?>
