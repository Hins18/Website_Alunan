<?php
// alunantest/admin_user_form.php
session_start();
require_once 'db_config.php';

// Proteksi halaman
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['page_message'] = ['type' => 'danger', 'text' => 'Anda tidak memiliki hak akses.'];
    header("Location: index.php");
    exit;
}

$current_page = 'admin_users.php';
$error_message = '';
$success_message = '';

if (isset($_SESSION['admin_form_message'])) {
    if ($_SESSION['admin_form_message']['type'] === 'success') {
        $success_message = $_SESSION['admin_form_message']['text'];
    } else {
        $error_message = $_SESSION['admin_form_message']['text'];
    }
    unset($_SESSION['admin_form_message']);
}

$action = $_GET['action'] ?? 'add';
$user_id_for_edit_url = isset($_GET['id']) ? intval($_GET['id']) : null;

$user = [
    'id' => '',
    'username' => '',
    'role' => 'user'
];
$form_title = ($action === 'edit' && $user_id_for_edit_url) ? "Edit Pengguna" : "Tambah Pengguna Baru";
$submit_button_text = ($action === 'edit' && $user_id_for_edit_url) ? "Update Pengguna" : "Tambah Pengguna";
$is_editing = false;
$original_username_on_load = '';

if ($action === 'edit' && $user_id_for_edit_url) {
    $is_editing = true;
    // echo "DEBUG: Mode Edit, ID Pengguna dari URL: " . $user_id_for_edit_url . "<br>"; // DEBUG
    $stmt_select = $conn->prepare("SELECT id, username, role FROM users WHERE id = ?");
    if ($stmt_select) {
        $stmt_select->bind_param("i", $user_id_for_edit_url);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $original_username_on_load = $user['username'];
            $form_title = "Edit Pengguna: " . htmlspecialchars($user['username']);
        } else {
            $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Pengguna dengan ID ' . htmlspecialchars($user_id_for_edit_url) . ' tidak ditemukan.'];
            header("Location: admin_users.php");
            exit;
        }
        $stmt_select->close();
    } else {
        $error_message = "Gagal menyiapkan data pengguna untuk diedit: " . $conn->error;
    }
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // echo "DEBUG: Form telah di-POST.<br>"; // DEBUG

    // $action diambil dari URL saat halaman dimuat, bukan dari POST.
    // $user_id_for_edit_url adalah ID pengguna dari URL jika mode 'edit'.
    
    $user_id_from_form = isset($_POST['user_id']) ? intval($_POST['user_id']) : null;
    $username_post = trim($_POST['username'] ?? '');
    $role_post = $_POST['role'] ?? 'user';

    // Update array $user dengan nilai dari POST untuk ditampilkan kembali jika ada error
    $user['id'] = $user_id_from_form; // Ini akan menjadi ID pengguna yang sedang diedit jika ada
    $user['username'] = $username_post;
    $user['role'] = $role_post;

    // echo "DEBUG: Data POST - ID: {$user_id_from_form}, Username: {$username_post}, Role: {$role_post}, Action: {$action}, Original Username on Load: {$original_username_on_load}<br>"; // DEBUG

    if (empty($username_post) || empty($role_post)) {
        $error_message = "Username dan Role wajib diisi.";
    } elseif (strlen($username_post) < 4) {
        $error_message = "Username minimal harus 4 karakter.";
    } elseif (!in_array($role_post, ['user', 'admin'])) {
        $error_message = "Role tidak valid.";
    } else {
        $username_safe = $conn->real_escape_string($username_post);
        $role_safe = $conn->real_escape_string($role_post);

        if ($action === 'edit' && $user_id_from_form) {
            // echo "DEBUG: Masuk blok proses EDIT.<br>"; // DEBUG
            $can_update = true;

            // Cek duplikasi username HANYA jika username diubah
            if (strtolower($username_safe) !== strtolower($original_username_on_load)) {
                // echo "DEBUG: Username diubah, cek duplikasi untuk '{$username_safe}'. Username asli: '{$original_username_on_load}'<br>"; // DEBUG
                $stmt_check_username = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
                if ($stmt_check_username) {
                    $stmt_check_username->bind_param("si", $username_safe, $user_id_from_form);
                    $stmt_check_username->execute();
                    $stmt_check_username->store_result();
                    if ($stmt_check_username->num_rows > 0) {
                        $error_message = "Username '" . htmlspecialchars($username_safe) . "' sudah digunakan oleh pengguna lain.";
                        $can_update = false;
                    }
                    $stmt_check_username->close();
                } else {
                    $error_message = "Error cek username: " . $conn->error;
                    $can_update = false;
                }
            } else {
                // echo "DEBUG: Username tidak diubah.<br>"; // DEBUG
            }

            // Proteksi Admin Utama
            if (strtolower($original_username_on_load) === 'admin') {
                if ($role_safe !== 'admin' && $user_id_from_form == 1) { // Asumsi ID admin utama adalah 1 atau sesuaikan
                    $error_message = "Anda tidak dapat mengubah role akun admin utama ('admin') menjadi selain 'admin'.";
                    $can_update = false;
                }
                if (strtolower($username_safe) !== 'admin' && $user_id_from_form == 1) {
                    $error_message = "Username 'admin' utama tidak dapat diubah.";
                    $can_update = false;
                }
            }
            
            // echo "DEBUG: Status can_update sebelum query: " . ($can_update ? 'true' : 'false') . ", Error message: " . $error_message . "<br>"; // DEBUG

            if ($can_update && empty($error_message)) {
                // echo "DEBUG: Melakukan query UPDATE.<br>"; // DEBUG
                $sql_update = "UPDATE users SET 
                                username = '" . $username_safe . "', 
                                role = '" . $role_safe . "' 
                               WHERE id = " . intval($user_id_from_form);
                
                if ($conn->query($sql_update) === TRUE) {
                    // echo "DEBUG: Query UPDATE berhasil. Affected rows: " . $conn->affected_rows . "<br>"; // DEBUG
                    if ($conn->affected_rows > 0) {
                        $_SESSION['admin_message'] = ['type' => 'success', 'text' => 'Data pengguna berhasil diperbarui!'];
                    } else {
                        $_SESSION['admin_message'] = ['type' => 'info', 'text' => 'Tidak ada perubahan data pada pengguna (data yang diinput sama dengan data sebelumnya).'];
                    }
                    header("Location: admin_users.php");
                    exit;
                } else {
                    $error_message = "Gagal memperbarui data pengguna: " . $conn->error;
                    // echo "DEBUG: Query UPDATE GAGAL: " . $conn->error . "<br>"; // DEBUG
                }
            }
        } elseif ($action === 'add') { 
             $error_message = "Fitur tambah pengguna dari form ini belum aktif.";
        } else {
            $error_message = "Aksi tidak valid atau ID pengguna tidak ada untuk diproses.";
        }
    }
    // echo "DEBUG: Error message setelah POST: " . $error_message . "<br>"; // DEBUG
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title><?php echo htmlspecialchars($form_title); ?> - Admin Alunan</title>
  <link rel="shortcut icon" href="images/favicon.png" type="">
  <link rel="stylesheet" type="text/css" href="css/bootstrap.css" />
  <link href="css/font-awesome.min.css" rel="stylesheet" />
  <link href="css/style.css" rel="stylesheet" />
  <link href="css/responsive.css" rel="stylesheet" />
  <style>
    body { font-family: 'Open Sans', sans-serif; background-color: #f4f7f6; color: #333; display: flex; min-height: 100vh; overflow-x: hidden; }
    .admin-wrapper { display: flex; width: 100%; }
    .admin-sidebar { width: 260px; background-color: #222831; color: #ffffff; padding: 20px 0; height: 100vh; position: fixed; top: 0; left: 0; overflow-y: auto; transition: width 0.3s ease; z-index: 1030; }
    .admin-sidebar .sidebar-header { padding: 0 20px 20px 20px; text-align: center; border-bottom: 1px solid #444; margin-bottom: 20px; }
    .admin-sidebar .sidebar-header h3 { font-family: 'Dancing Script', cursive; color: #ffbe33; margin: 0; font-size: 2.2rem; }
    .admin-sidebar .sidebar-header .admin-welcome { font-size: 0.9rem; color: #ccc; margin-top: 5px; }
    .admin-sidebar ul.components { padding: 0; margin: 0; list-style: none; }
    .admin-sidebar ul li a { padding: 12px 20px; font-size: 1.05em; display: block; color: #dbdbdb; text-decoration: none; transition: background-color 0.3s ease, color 0.3s ease; border-left: 3px solid transparent; }
    .admin-sidebar ul li a:hover { background-color: #343a40; color: #ffbe33; border-left-color: #ffbe33; }
    .admin-sidebar ul li a i { margin-right: 10px; }
    .admin-sidebar ul li.active > a, .admin-sidebar ul li.active > a:hover { background-color: #ffbe33; color: #222831; border-left-color: #ffffff; }
    .admin-sidebar .logout-link-container { padding: 20px; border-top: 1px solid #444; margin-top: auto; }
    .admin-sidebar .btn_logout_admin { display: block; width: 100%; padding: 10px 15px; background-color: #ffbe33; color: #222831; border-radius: 5px; text-align: center; font-weight: bold; text-decoration: none; transition: background-color 0.3s ease; }
    .admin-sidebar .btn_logout_admin:hover { background-color: #e69c00; color: #ffffff; }
    .admin-content { flex-grow: 1; padding: 25px; margin-left: 260px; transition: margin-left 0.3s ease; width: calc(100% - 260px); }
    .admin-content .content-header { display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
    .admin-content .content-header h2 { font-family: 'Dancing Script', cursive; color: #222831; margin: 0; font-size: 2rem; }
    .card-admin { background-color: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); margin-bottom: 20px; }
    .card-admin .card-header-admin { font-family: 'Open Sans', sans-serif; font-size: 1.25rem; font-weight: 600; color: #222831; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    .card-admin .card-body-admin .form-group { margin-bottom: 1.5rem; } 
    .card-admin .card-body-admin label { font-weight: 500; color: #333; }
    .form-control-admin { display: block; width: 100%; padding: .575rem .95rem; font-size: 1rem; font-weight: 400; line-height: 1.5; color: #495057; background-color: #fff; background-clip: padding-box; border: 1px solid #ced4da; border-radius: .25rem; transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out; }
    .form-control-admin:focus { color: #495057; background-color: #fff; border-color: #ffbe33; outline: 0; box-shadow: 0 0 0 0.2rem rgba(255, 190, 51, .25); }
    .btn-admin-action-form { padding: 10px 25px; font-size: 1rem; background-color: #ffbe33; color: #ffffff; border-radius: 5px; text-decoration: none; transition: background-color 0.3s ease; border: none; cursor: pointer; font-weight: 500; }
    .btn-admin-action-form:hover { background-color: #e69c00; color: #ffffff; }
    .btn-cancel { background-color: #6c757d; margin-left: 10px; }
    .btn-cancel:hover { background-color: #5a6268; }
    .message { margin-bottom: 15px; padding: 10px; border-radius: 5px; font-size: 0.9em; }
    .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    #sidebarCollapse { display: none; padding: 10px; background-color: #ffbe33; color: #222831; border: none; border-radius: 5px; font-size: 1.2rem; cursor: pointer; position: fixed; top: 15px; left: 15px; z-index: 1031; }
    @media (max-width: 768px) { .admin-sidebar { width: 0; } .admin-sidebar.active { width: 260px; } .admin-content { margin-left: 0; width: 100%; } #sidebarCollapse { display: block; } .admin-sidebar .sidebar-header h3 { font-size: 1.8rem; } .admin-sidebar ul li a { padding: 10px 15px; font-size: 1em; } }
  </style>
</head>
<body>
<div class="admin-wrapper">
    <nav class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header"><h3>Alunan</h3><div class="admin-welcome">Admin: <?php echo htmlspecialchars($_SESSION['username']); ?></div></div>
        <ul class="components">
            <li class="<?php echo ($current_page == 'admin.php') ? 'active' : ''; ?>"><a href="admin.php"><i class="fa fa-tachometer"></i> Dashboard</a></li>
            <li class="<?php echo ($current_page == 'admin_menu.php') ? 'active' : ''; ?>"><a href="admin_menu.php"><i class="fa fa-cutlery"></i> Kelola Menu</a></li>
            <li class="<?php echo ($current_page == 'admin_users.php') ? 'active' : ''; ?>"><a href="admin_users.php"><i class="fa fa-users"></i> Kelola Pengguna</a></li>
            <li><a href="#"><i class="fa fa-calendar"></i> Kelola Reservasi</a></li>
        </ul>
        <div class="logout-link-container"><a href="logout.php" class="btn_logout_admin"><i class="fa fa-sign-out"></i> Logout</a></div>
    </nav>
    <button type="button" id="sidebarCollapse" class="btn"><i class="fa fa-bars"></i></button>
    <div class="admin-content" id="adminContent">
        <div class="content-header"><h2><?php echo htmlspecialchars($form_title); ?></h2></div>
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card-admin">
                        <div class="card-body-admin">
                            <?php if (!empty($error_message)): ?>
                                <div class="alert alert-danger message error-message" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($success_message)): ?>
                                <div class="alert alert-success message success-message" role="alert"><?php echo $success_message; ?></div>
                            <?php endif; ?>

                            <form action="admin_user_form.php?action=<?php echo htmlspecialchars($action); ?><?php echo ($user_id_for_edit_url ? '&id=' . htmlspecialchars($user_id_for_edit_url) : ''); ?>" method="POST">
                                <?php if ($is_editing && isset($user['id'])): ?>
                                    <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                <?php endif; ?>
                                <div class="form-group">
                                    <label for="username">Username <span style="color:red;">*</span></label>
                                    <input type="text" name="username" id="username" class="form-control-admin" value="<?php echo htmlspecialchars($user['username']); ?>" required 
                                           <?php echo ($is_editing && strtolower($original_username_on_load) === 'admin' ? 'readonly' : ''); ?>>
                                     <?php if ($is_editing && strtolower($original_username_on_load) === 'admin'): ?>
                                        <small class="form-text text-muted">Username 'admin' utama tidak dapat diubah.</small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label for="role">Role <span style="color:red;">*</span></label>
                                    <select name="role" id="role" class="form-control-admin" required 
                                            <?php echo ($is_editing && strtolower($original_username_on_load) === 'admin' && $user['id'] == ($_SESSION['user_id'] ?? null) ? 'disabled' : ''); ?>>
                                        <option value="user" <?php echo (isset($user['role']) && $user['role'] === 'user' ? 'selected' : ''); ?>>User</option>
                                        <option value="admin" <?php echo (isset($user['role']) && $user['role'] === 'admin' ? 'selected' : ''); ?>>Admin</option>
                                    </select>
                                    <?php if ($is_editing && strtolower($original_username_on_load) === 'admin' && $user['id'] == ($_SESSION['user_id'] ?? null)): ?>
                                        <small class="form-text text-muted">Role untuk akun admin utama ('<?php echo htmlspecialchars($original_username_on_load); ?>') tidak dapat diubah.</small>
                                         <input type="hidden" name="role" value="admin" /> 
                                    <?php endif; ?>
                                </div>
                                <button type="submit" class="btn btn-admin-action-form"><?php echo htmlspecialchars($submit_button_text); ?></button>
                                <a href="admin_users.php" class="btn btn-admin-action-form btn-cancel">Batal</a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="js/jquery-3.4.1.min.js"></script>
<script src="js/bootstrap.bundle.min.js"></script> 
<script>
    $(document).ready(function () {
        $('#sidebarCollapse').on('click', function () {
            $('#adminSidebar').toggleClass('active');
        });
    });
</script>
</body>
</html>