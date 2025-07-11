<?php
// alunantest/notifikasi_midtrans.php
require_once 'db_config.php';
require_once dirname(__FILE__) . '/vendor/autoload.php';

// Set Server Key Midtrans Anda
\Midtrans\Config::$serverKey = 'SB-Mid-server-jzU8bc-luxflRxkVWU10lbTG';
\Midtrans\Config::$isProduction = false;

try {
    $notif = new \Midtrans\Notification();
} catch (Exception $e) {
    error_log("Error membuat objek notifikasi: " . $e->getMessage());
    http_response_code(400); 
    exit('Invalid notification object');
}

$transaction = $notif->transaction_status;
$order_id = $notif->order_id;
$fraud = $notif->fraud_status;

// Log notifikasi yang masuk ke file error log server Anda untuk debugging
error_log("Menerima notifikasi untuk Order ID: $order_id, Status Transaksi: $transaction, Status Fraud: $fraud");

// Logika untuk menentukan status baru di database Anda
$new_status = '';
if ($transaction == 'capture' || $transaction == 'settlement') {
    // Hanya update jika status fraud 'accept'
    if ($fraud == 'accept') {
      // Pembayaran berhasil dan dianggap aman
      $new_status = 'diproses'; // Ubah status menjadi 'diproses'
    }
} else if ($transaction == 'pending') {
    $new_status = 'pending'; // Status masih menunggu
} else if ($transaction == 'deny' || $transaction == 'cancel' || $transaction == 'expire') {
    $new_status = 'dibatalkan'; // Pesanan dibatalkan/gagal
}

// Hanya update database jika ada status baru yang valid untuk diubah
if (!empty($new_status)) {
    $stmt_update = $conn->prepare("UPDATE orders SET status_pesanan = ? WHERE id = ?");
    if ($stmt_update) {
        $stmt_update->bind_param("ss", $new_status, $order_id);
        if ($stmt_update->execute()) {
            error_log("Status pesanan #$order_id berhasil diperbarui menjadi '$new_status'");
        } else {
            error_log("Gagal memperbarui status pesanan #$order_id: " . $stmt_update->error);
        }
        $stmt_update->close();
    } else {
        error_log("Gagal menyiapkan statement update status: " . $conn->error);
    }
}

$conn->close();

// Kirim respons HTTP 200 OK ke Midtrans untuk mengkonfirmasi notifikasi telah diterima
http_response_code(200);
?>