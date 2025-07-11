<?php
// logout.php
session_start(); // Mulai session untuk dapat mengakses dan menghancurkannya

// Hapus semua variabel session
$_SESSION = array();

// Hancurkan cookie session jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Atur waktu kedaluwarsa ke masa lalu
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hancurkan session sepenuhnya
session_destroy();

// Arahkan pengguna kembali ke halaman utama (index.php)
header("Location: index.php");
exit; // Pastikan tidak ada kode lain yang dieksekusi setelah redirect
?>