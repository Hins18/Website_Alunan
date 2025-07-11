<?php
// alunantest/login.php
session_start();
require_once 'db_config.php'; 

header('Content-Type: application/json'); 
$response = ['success' => false, 'message' => '', 'username' => null, 'role' => null];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_input = $_POST['username'] ?? '';
    $password_input = $_POST['password'] ?? ''; 

    if (!empty($username_input) && !empty($password_input)) {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?"); // Ambil juga kolom 'role'
        if ($stmt === false) {
            $response['message'] = "Kesalahan server: Gagal menyiapkan statement.";
        } else {
            $stmt->bind_param("s", $username_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                // Verifikasi password (plain text untuk uji coba)
                if ($password_input === $user['password']) { 
                    // Login berhasil
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_role'] = $user['role']; // Simpan role ke session
                    
                    $response['success'] = true;
                    $response['message'] = "Login berhasil!";
                    $response['username'] = $user['username'];
                    $response['role'] = $user['role']; 
                } else {
                    $_SESSION['logged_in'] = false; 
                    unset($_SESSION['username']);
                    unset($_SESSION['user_id']);
                    unset($_SESSION['user_role']); 
                    $response['message'] = "Username atau password salah.";
                }
            } else {
                $_SESSION['logged_in'] = false; 
                unset($_SESSION['username']);
                unset($_SESSION['user_id']);
                unset($_SESSION['user_role']); 
                $response['message'] = "Username atau password salah.";
            }
            $stmt->close();
        }
    } else {
        $response['message'] = "Username dan password tidak boleh kosong.";
    }
    $conn->close();
} else {
    $response['message'] = "Metode permintaan tidak valid.";
}

echo json_encode($response);
exit;
?>