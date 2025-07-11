<?php
// alunantest/keranjang_aksi.php
session_start();
require_once 'db_config.php'; // Jika Anda memerlukan koneksi DB untuk validasi produk di masa depan

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Aksi tidak dikenal.'];

// Pastikan keranjang di session sudah ada
if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;

switch ($action) {
    case 'add':
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
                $response['message'] = 'Anda harus login untuk menambahkan item.';
                break;
            }
            $kd_brng = $_POST['kd_brng'] ?? null;
            $nm_brng = $_POST['nm_brng'] ?? 'Nama Tidak Diketahui';
            $harga_brng = isset($_POST['harga_brng']) ? floatval($_POST['harga_brng']) : 0;
            $ft_brng = $_POST['ft_brng'] ?? 'images/default_food.png';
            $kuantitas = isset($_POST['kuantitas']) ? intval($_POST['kuantitas']) : 0;

            if ($kd_brng && $kuantitas > 0 && $harga_brng > 0) {
                if (isset($_SESSION['keranjang'][$kd_brng])) {
                    // Jika item sudah ada, tambahkan kuantitasnya
                    $_SESSION['keranjang'][$kd_brng]['kuantitas'] += $kuantitas;
                    $_SESSION['keranjang'][$kd_brng]['harga_total'] = $_SESSION['keranjang'][$kd_brng]['kuantitas'] * $_SESSION['keranjang'][$kd_brng]['harga_satuan'];
                } else {
                    // Jika item baru
                    $_SESSION['keranjang'][$kd_brng] = [
                        'kd_brng'      => $kd_brng,
                        'nama'         => $nm_brng,
                        'harga_satuan' => $harga_brng,
                        'foto'         => $ft_brng,
                        'kuantitas'    => $kuantitas,
                        'harga_total'  => $harga_brng * $kuantitas
                    ];
                }
                $response['success'] = true;
                $response['message'] = htmlspecialchars($nm_brng) . ' berhasil ditambahkan ke keranjang!';
            } else {
                $response['message'] = 'Data item tidak lengkap atau kuantitas/harga tidak valid.';
            }
        } else {
            $response['message'] = 'Metode tidak diizinkan untuk aksi ini.';
        }
        break;

    case 'count':
        $total_items = 0;
        foreach ($_SESSION['keranjang'] as $item) {
            $total_items += $item['kuantitas'];
        }
        $response['success'] = true;
        $response['count'] = $total_items;
        $response['message'] = 'Jumlah item di keranjang.';
        break;

    case 'get_cart_summary':
    case 'get_cart_detail':
        $cart_items = [];
        $total_harga_keseluruhan = 0;
        foreach ($_SESSION['keranjang'] as $kd => $item) {
            $cart_items[] = $item; // Kirim semua detail item
            $total_harga_keseluruhan += $item['harga_total'];
        }
        $response['success'] = true;
        $response['cart_items'] = array_values($cart_items); // Kirim sebagai array numerik
        $response['total_harga_keseluruhan'] = $total_harga_keseluruhan;
        $response['message'] = 'Data keranjang berhasil diambil.';
        break;

    case 'update_quantity':
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $kd_brng = $_POST['kd_brng'] ?? null;
            $kuantitas = isset($_POST['kuantitas']) ? intval($_POST['kuantitas']) : 0;

            if ($kd_brng && isset($_SESSION['keranjang'][$kd_brng]) && $kuantitas > 0) {
                $_SESSION['keranjang'][$kd_brng]['kuantitas'] = $kuantitas;
                $_SESSION['keranjang'][$kd_brng]['harga_total'] = $kuantitas * $_SESSION['keranjang'][$kd_brng]['harga_satuan'];
                $response['success'] = true;
                $response['message'] = 'Kuantitas berhasil diperbarui.';
            } elseif ($kd_brng && isset($_SESSION['keranjang'][$kd_brng]) && $kuantitas <= 0) {
                // Jika kuantitas jadi 0 atau kurang, hapus item
                unset($_SESSION['keranjang'][$kd_brng]);
                $response['success'] = true;
                $response['message'] = 'Item dihapus dari keranjang karena kuantitas 0.';
            } else {
                $response['message'] = 'Gagal memperbarui kuantitas: Item tidak ditemukan atau kuantitas tidak valid.';
            }
        } else {
             $response['message'] = 'Metode tidak diizinkan.';
        }
        break;
        
    case 'remove_item':
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $kd_brng = $_POST['kd_brng'] ?? null;
            if ($kd_brng && isset($_SESSION['keranjang'][$kd_brng])) {
                unset($_SESSION['keranjang'][$kd_brng]);
                $response['success'] = true;
                $response['message'] = 'Item berhasil dihapus dari keranjang.';
            } else {
                $response['message'] = 'Gagal menghapus item: Item tidak ditemukan.';
            }
        } else {
             $response['message'] = 'Metode tidak diizinkan.';
        }
        break;
    
    default:
        // Biarkan respons default 'Aksi tidak dikenal.'
        break;
}

echo json_encode($response);
exit;
?>