<?php
// alunantest/review_aksi.php
session_start();
require_once 'db_config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'Aksi tidak valid atau data tidak lengkap.'];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'submit_review') {
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        $response['message'] = 'Anda harus login untuk memberi review.';
        echo json_encode($response);
        exit;
    }

    $user_id = $_SESSION['user_id'] ?? null; // Pastikan user_id ada di session
    $kd_brng = $_POST['kd_brng_review'] ?? null;
    $nm_brng_review_ref = $_POST['nm_brng_review'] ?? null; // Untuk referensi/logging jika perlu
    $rating = isset($_POST['review_rating']) ? intval($_POST['review_rating']) : 0;
    $komentar = trim($_POST['review_komentar'] ?? '');

    if (!$user_id) {
        $response['message'] = 'Sesi pengguna tidak valid untuk memberi review.';
        echo json_encode($response);
        exit;
    }
    if (empty($kd_brng)) {
        $response['message'] = 'Produk tidak teridentifikasi untuk direview.';
        echo json_encode($response);
        exit;
    }
    if ($rating < 1 || $rating > 5) {
        $response['message'] = 'Rating harus antara 1 dan 5 bintang.';
        echo json_encode($response);
        exit;
    }
    // Komentar boleh kosong, jadi tidak perlu validasi empty di sini kecuali Anda mau.

    // Opsional: Cek apakah user sudah pernah mereview produk ini
    // Anda bisa mengaktifkan ini jika ingin membatasi satu review per user per produk
    /*
    $stmt_check = $conn->prepare("SELECT id FROM product_reviews WHERE user_id = ? AND kd_brng = ?");
    if ($stmt_check) {
        $stmt_check->bind_param("is", $user_id, $kd_brng);
        $stmt_check->execute();
        $stmt_check->store_result();
        if ($stmt_check->num_rows > 0) {
            $response['message'] = 'Anda sudah pernah memberi review untuk produk "' . htmlspecialchars($nm_brng_review_ref) . '".';
            echo json_encode($response);
            $stmt_check->close();
            $conn->close();
            exit;
        }
        $stmt_check->close();
    } else {
        error_log('Review Check Prepare Error: ' . $conn->error);
        $response['message'] = 'Gagal memeriksa riwayat review: ' . $conn->error; // Hati-hati menampilkan error DB ke user
        echo json_encode($response);
        $conn->close();
        exit;
    }
    */

    $status_review_default = 'pending'; // Review baru akan menunggu persetujuan admin

    $stmt_insert = $conn->prepare("INSERT INTO product_reviews (kd_brng, user_id, rating, komentar, status_review, tanggal_review) VALUES (?, ?, ?, ?, ?, NOW())");
    if ($stmt_insert) {
        // Tipe: s (kd_brng), i (user_id), i (rating), s (komentar), s (status_review)
        $stmt_insert->bind_param("siiss", $kd_brng, $user_id, $rating, $komentar, $status_review_default);
        if ($stmt_insert->execute()) {
            $response['success'] = true;
            $response['message'] = 'Review Anda untuk "' . htmlspecialchars($nm_brng_review_ref) . '" telah berhasil dikirim dan menunggu persetujuan admin. Terima kasih!';
        } else {
            $response['message'] = 'Gagal menyimpan review: ' . $stmt_insert->error;
            error_log('Review Insert Execute Error: ' . $stmt_insert->error . ' - Data: kd_brng=' . $kd_brng . ', user_id=' . $user_id);
        }
        $stmt_insert->close();
    } else {
        $response['message'] = 'Gagal menyiapkan statement review: ' . $conn->error;
        error_log('Review Insert Prepare Error: ' . $conn->error);
    }
}

echo json_encode($response);
$conn->close();
?>