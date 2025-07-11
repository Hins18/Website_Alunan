<?php
// alunantest/checkout_midtrans_proses.php
session_start();
require_once 'db_config.php';

// Pastikan Anda sudah menjalankan "composer require midtrans/midtrans-php"
if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Library Midtrans tidak ditemukan.']);
    exit;
}

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Terjadi kesalahan tidak dikenal.'];

// --- KONFIGURASI MIDTRANS ---
\Midtrans\Config::$serverKey = 'SB-Mid-server-jzU8bc-luxflRxkVWU10lbTG';
\Midtrans\Config::$isProduction = false; // false untuk sandbox
\Midtrans\Config::$isSanitized = true;
\Midtrans\Config::$is3ds = true;
// --- AKHIR KONFIGURASI MIDTRANS ---

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $response['message'] = 'Sesi tidak valid. Silakan login ulang.';
    echo json_encode($response);
    exit;
}
$user_id = $_SESSION['user_id'] ?? null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'process_order') {
    if (empty($_SESSION['keranjang'])) {
        $response['message'] = 'Keranjang Anda kosong.';
        echo json_encode($response);
        exit;
    }

    $nama_pemesan = trim($_POST['nama_pemesan'] ?? '');
    $nomor_telepon = trim($_POST['nomor_telepon'] ?? '');
    $alamat_pengiriman = trim($_POST['alamat_pengiriman'] ?? '');
    $metode_pembayaran_info = trim($_POST['metode_pembayaran'] ?? ''); 
    $catatan_pesanan = trim($_POST['catatan_pesanan'] ?? null);

    if (empty($nama_pemesan) || empty($nomor_telepon) || empty($alamat_pengiriman)) {
        $response['message'] = 'Nama, Nomor Telepon, dan Alamat wajib diisi.';
        echo json_encode($response);
        exit;
    }

    $total_harga_keseluruhan = 0;
    $midtrans_items = [];
    $items_to_order = [];

    foreach ($_SESSION['keranjang'] as $kd_brng => $item) {
        $harga_satuan = (float)$item['harga_satuan'];
        $kuantitas = (int)$item['kuantitas'];
        $subtotal = $kuantitas * $harga_satuan;
        $total_harga_keseluruhan += $subtotal;
        $items_to_order[] = ['kd_brng' => $item['kd_brng'], 'nama' => $item['nama'], 'harga_satuan' => $harga_satuan, 'kuantitas' => $kuantitas, 'subtotal' => $subtotal];
        $midtrans_items[] = ['id' => $item['kd_brng'], 'price' => $harga_satuan, 'quantity' => $kuantitas, 'name' => substr($item['nama'], 0, 50)];
    }

    if (empty($items_to_order)) {
        $response['message'] = 'Tidak ada item valid di keranjang.';
        echo json_encode($response);
        exit;
    }
    
    $conn->begin_transaction();
    try {
        $order_id_unik = 'ALN-' . time() . '-' . rand(100, 999);
        $status_pesanan_awal = 'pending';

        $stmt_order = $conn->prepare("INSERT INTO orders (id, user_id, nama_pemesan, nomor_telepon, alamat_pengiriman, total_harga, status_pesanan, metode_pembayaran, catatan_pesanan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        if (!$stmt_order) throw new Exception("Gagal menyiapkan statement pesanan: " . $conn->error);
        $stmt_order->bind_param("sisssdsss", $order_id_unik, $user_id, $nama_pemesan, $nomor_telepon, $alamat_pengiriman, $total_harga_keseluruhan, $status_pesanan_awal, $metode_pembayaran_info, $catatan_pesanan);
        if (!$stmt_order->execute()) throw new Exception("Gagal menyimpan pesanan: " . $stmt_order->error);
        $stmt_order->close();

        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, kd_brng, nm_brng_saat_pesan, harga_satuan_saat_pesan, kuantitas, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt_item) throw new Exception("Gagal menyiapkan statement item pesanan: " . $conn->error);
        foreach ($items_to_order as $item) {
            $stmt_item->bind_param("sssdid", $order_id_unik, $item['kd_brng'], $item['nama'], $item['harga_satuan'], $item['kuantitas'], $item['subtotal']);
            if (!$stmt_item->execute()) throw new Exception("Gagal menyimpan item pesanan: " . $stmt_item->error);
        }
        $stmt_item->close();

        $conn->commit();

        // Siapkan parameter untuk Midtrans
        $params = [
            'transaction_details' => ['order_id' => $order_id_unik, 'gross_amount' => $total_harga_keseluruhan],
            'item_details' => $midtrans_items,
            'customer_details' => ['first_name' => $nama_pemesan, 'phone' => $_POST['nomor_telepon']]
        ];

        $snapToken = \Midtrans\Snap::getSnapToken($params);

        $_SESSION['keranjang'] = []; // Kosongkan keranjang setelah token didapat

        $response['success'] = true;
        $response['message'] = 'Token pembayaran berhasil dibuat.';
        $response['snap_token'] = $snapToken;
        $response['order_id'] = $order_id_unik;

    } catch (Exception $e) {
        $conn->rollback(); 
        error_log("Checkout Exception: " . $e->getMessage());
        $response['message'] = 'Error Sebenarnya: ' . $e->getMessage();
    }
}

echo json_encode($response);
$conn->close();
exit;
?>