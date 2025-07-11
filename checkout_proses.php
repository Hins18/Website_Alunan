<?php
// alunantest/checkout_proses.php
session_start();
require_once 'db_config.php';

// Untuk debugging, tampilkan error ke browser. Hapus/komentari di produksi.
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

// Untuk produksi, lebih baik log error ke file.
error_reporting(0); 
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Pastikan Anda tahu lokasi error_log PHP Anda (cek php.ini atau log Apache)
// error_log("checkout_proses.php diakses pada: " . date("Y-m-d H:i:s"));


header('Content-Type: application/json'); 
$response = ['success' => false, 'message' => 'Aksi tidak dikenal atau data tidak lengkap.'];

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $response['message'] = 'Sesi tidak valid atau Anda belum login. Silakan login terlebih dahulu.';
    echo json_encode($response);
    exit;
}
$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'process_order') {
    // error_log("checkout_proses.php: Data POST diterima: " . print_r($_POST, true)); 
    // error_log("checkout_proses.php: Session Keranjang: " . print_r($_SESSION['keranjang'], true));

    if (empty($_SESSION['keranjang'])) {
        $response['message'] = 'Keranjang belanja Anda kosong. Tidak ada yang bisa di-checkout.';
        echo json_encode($response);
        exit;
    }

    $nama_pemesan = trim($_POST['nama_pemesan'] ?? '');
    $nomor_telepon = trim($_POST['nomor_telepon'] ?? '');
    $alamat_pengiriman = trim($_POST['alamat_pengiriman'] ?? '');
    $metode_pembayaran = trim($_POST['metode_pembayaran'] ?? '');
    $catatan_pesanan = trim($_POST['catatan_pesanan'] ?? null);

    if (empty($nama_pemesan) || empty($nomor_telepon) || empty($alamat_pengiriman) || empty($metode_pembayaran)) {
        $response['message'] = 'Data pelanggan tidak lengkap. Nama, nomor telepon, alamat, dan metode pembayaran wajib diisi.';
        echo json_encode($response);
        exit;
    }

    $total_harga_keseluruhan = 0;
    $items_to_order = [];

    foreach ($_SESSION['keranjang'] as $kd_brng => $item_from_session) {
        if (
            isset($item_from_session['kuantitas']) && $item_from_session['kuantitas'] > 0 &&
            isset($item_from_session['harga_satuan']) && // Pastikan field ini ada di session keranjang
            isset($item_from_session['nama']) &&
            isset($item_from_session['kd_brng'])
        ) {
            $harga_satuan = (float)$item_from_session['harga_satuan'];
            $kuantitas = (int)$item_from_session['kuantitas'];
            $subtotal = $kuantitas * $harga_satuan;
            $total_harga_keseluruhan += $subtotal;

            $items_to_order[] = [
                'kd_brng'                   => $item_from_session['kd_brng'],
                'nm_brng_saat_pesan'        => $item_from_session['nama'], 
                'harga_satuan_saat_pesan'   => $harga_satuan, 
                'kuantitas'                 => $kuantitas,
                'subtotal'                  => $subtotal
            ];
        } else {
            error_log("Item tidak lengkap di keranjang saat checkout untuk KD_BRNG: " . $kd_brng . " - Data: " . print_r($item_from_session, true));
        }
    }

    if (empty($items_to_order)) {
        $response['message'] = 'Tidak ada item valid di keranjang untuk diproses.';
        echo json_encode($response);
        exit;
    }
    
    $conn->begin_transaction();

    try {
        $status_pesanan = 'pending';
        $stmt_order = $conn->prepare("INSERT INTO orders (user_id, nama_pemesan, nomor_telepon, alamat_pengiriman, total_harga, status_pesanan, metode_pembayaran, catatan_pesanan, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        
        if (!$stmt_order) {
            throw new Exception("DB Prepare Error (orders): " . $conn->error);
        }
        
        // Tipe data: i (user_id bisa null), s (nama), s (telepon), s (alamat), d (total), s (status), s (metode), s (catatan)
        $stmt_order->bind_param("isssdsss", $user_id, $nama_pemesan, $nomor_telepon, $alamat_pengiriman, $total_harga_keseluruhan, $status_pesanan, $metode_pembayaran, $catatan_pesanan);
        
        if (!$stmt_order->execute()) {
            throw new Exception("DB Execute Error (orders): " . $stmt_order->error);
        }
        $order_id = $stmt_order->insert_id; 
        $stmt_order->close();

        if ($order_id <= 0) {
             throw new Exception("Gagal mendapatkan ID pesanan setelah insert.");
        }

        $stmt_item = $conn->prepare("INSERT INTO order_items (order_id, kd_brng, nm_brng_saat_pesan, harga_satuan_saat_pesan, kuantitas, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$stmt_item) {
            throw new Exception("DB Prepare Error (order_items): " . $conn->error);
        }

        foreach ($items_to_order as $ordered_item) {
            // Tipe data: i (order_id), s (kd_brng), s (nama), d (harga), i (kuantitas), d (subtotal)
            $stmt_item->bind_param("issdid", 
                $order_id, 
                $ordered_item['kd_brng'], 
                $ordered_item['nm_brng_saat_pesan'], 
                $ordered_item['harga_satuan_saat_pesan'], 
                $ordered_item['kuantitas'], 
                $ordered_item['subtotal']
            );
            if (!$stmt_item->execute()) {
                throw new Exception("DB Execute Error (order_items for KD: " . htmlspecialchars($ordered_item['kd_brng']) . "): " . $stmt_item->error);
            }
        }
        $stmt_item->close();

        $conn->commit();
        $_SESSION['keranjang'] = []; // Kosongkan keranjang setelah sukses
        
        $response['success'] = true;
        $response['message'] = 'Pesanan Anda dengan ID #' . $order_id . ' telah berhasil dibuat! Kami akan segera memprosesnya.';
        // $response['order_id'] = $order_id; // Anda bisa mengirim order_id jika perlu di client

    } catch (Exception $e) {
        $conn->rollback(); 
        error_log("Checkout_proses.php Exception: " . $e->getMessage());
        $response['message'] = 'Terjadi kesalahan internal saat memproses pesanan Anda. Silakan coba lagi nanti.';
        // $response['debug_error'] = $e->getMessage(); // Jangan tampilkan ini di produksi
    }

} else {
    error_log("Checkout_proses.php: Akses tidak valid. Request: " . print_r($_REQUEST, true) . " Method: " . $_SERVER["REQUEST_METHOD"]);
    // $response['message'] = 'Akses tidak valid atau parameter tidak lengkap.'; // Sudah ada di default
}

echo json_encode($response);
$conn->close(); 
exit;
?>