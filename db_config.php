<?php
define('DB_SERVER', 'localhost'); // atau alamat server database Anda
define('DB_USERNAME', 'root');    // username database Anda
define('DB_PASSWORD', '');        // password database Anda
define('DB_NAME', 'alunan'); // nama database Anda

// Membuat koneksi
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
// echo "Koneksi berhasil"; // Hapus atau beri komentar setelah tes
?>