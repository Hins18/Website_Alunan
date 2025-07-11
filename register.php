<?php
// alunantest/register.php
session_start();
require_once 'db_config.php'; 

header('Content-Type: application/json'); 

$response = ['success' => false, 'message' => ''];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password_input = $_POST['password'] ?? ''; 
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($password_input) || empty($confirm_password)) {
        $response['message'] = "Semua field wajib diisi.";
    } elseif (strlen($username) < 4) {
        $response['message'] = "Username minimal harus 4 karakter.";
    } elseif (strlen($password_input) < 6) {
        $response['message'] = "Password minimal harus 6 karakter.";
    } elseif ($password_input !== $confirm_password) {
        $response['message'] = "Password dan konfirmasi password tidak cocok.";
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        if ($stmt_check === false) {
            $response['message'] = "Kesalahan server (cek username).";
        } else {
            $stmt_check->bind_param("s", $username);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $response['message'] = "Username sudah digunakan.";
            } else {
                // Simpan password sebagai plain text (SESUAI PERMINTAAN UNTUK UJI COBA)
                $plain_password = $password_input; 
                
                // --- Perubahan: Tambahkan role saat insert ---
                $default_role = 'user'; // Atur role default untuk pengguna baru
                $stmt_insert = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                if ($stmt_insert === false) {
                    $response['message'] = "Kesalahan server (insert user).";
                } else {
                    // Mengikat plain_password dan default_role
                    $stmt_insert->bind_param("sss", $username, $plain_password, $default_role); 
                    if ($stmt_insert->execute()) {
                        $response['success'] = true;
                        $response['message'] = "Registrasi berhasil! Silakan login.";
                    } else {
                        $response['message'] = "Gagal melakukan registrasi.";
                    }
                    $stmt_insert->close();
                }
                // --- Akhir Perubahan ---
            }
            $stmt_check->close();
        }
    }
    $conn->close();
} else {
    $response['message'] = "Metode permintaan tidak valid.";
}

echo json_encode($response);
exit;
?>