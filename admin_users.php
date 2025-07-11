<?php
// alunantest/admin_users.php
session_start();
require_once 'db_config.php';

// Proteksi halaman: Cek login dan role
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Alihkan ke index.php jika bukan admin dan set pesan
    $_SESSION['page_message'] = ['type' => 'danger', 'text' => 'Anda tidak memiliki hak akses ke halaman admin.'];
    header("Location: index.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$feedback_message = ''; // Untuk pesan feedback dari session atau proses

// Ambil pesan dari session jika ada (misalnya, setelah redirect dari form edit/hapus)
if (isset($_SESSION['admin_message'])) {
    $message_type = $_SESSION['admin_message']['type'] ?? 'info';
    $message_text = $_SESSION['admin_message']['text'] ?? 'Tidak ada pesan.';
    $feedback_message = "<div class='alert alert-" . htmlspecialchars($message_type) . " alert-dismissible fade show' role='alert'>" .
                       htmlspecialchars($message_text) .
                       "<button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button></div>";
    unset($_SESSION['admin_message']);
}

// --- AWAL LOGIKA HAPUS PENGGUNA ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id_to_delete = intval($_GET['id']);
    $can_delete = true;
    $message_after_action = ['type' => 'error', 'text' => 'Operasi gagal.'];

    // Ambil detail pengguna yang akan dihapus untuk pengecekan
    $stmt_check_user = $conn->prepare("SELECT username, role FROM users WHERE id = ?");
    if ($stmt_check_user) {
        $stmt_check_user->bind_param("i", $id_to_delete);
        $stmt_check_user->execute();
        $result_check_user = $stmt_check_user->get_result();
        if ($result_check_user->num_rows === 1) {
            $user_to_delete = $result_check_user->fetch_assoc();

            // Proteksi: Jangan hapus admin utama (misalnya username 'admin')
            if (strtolower($user_to_delete['username']) === 'admin') {
                $message_after_action = ['type' => 'error', 'text' => 'Tidak dapat menghapus akun admin utama.'];
                $can_delete = false;
            }
            // Proteksi: Jangan biarkan admin menghapus akunnya sendiri
            elseif ($id_to_delete === $_SESSION['user_id']) {
                $message_after_action = ['type' => 'error', 'text' => 'Anda tidak dapat menghapus akun Anda sendiri.'];
                $can_delete = false;
            }
        } else {
            $message_after_action = ['type' => 'error', 'text' => 'Pengguna yang akan dihapus tidak ditemukan.'];
            $can_delete = false;
        }
        $stmt_check_user->close();
    } else {
        $message_after_action = ['type' => 'error', 'text' => 'Gagal menyiapkan data pengguna untuk dihapus: ' . $conn->error];
        $can_delete = false;
    }

    if ($can_delete) {
        $stmt_delete = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt_delete) {
            $stmt_delete->bind_param("i", $id_to_delete);
            if ($stmt_delete->execute()) {
                if ($stmt_delete->affected_rows > 0) {
                    $message_after_action = ['type' => 'success', 'text' => 'Pengguna berhasil dihapus.'];
                } else {
                    // Ini bisa terjadi jika user sudah dihapus di tab lain
                    $message_after_action = ['type' => 'warning', 'text' => 'Pengguna tidak ditemukan atau sudah dihapus sebelumnya.'];
                }
            } else {
                $message_after_action = ['type' => 'error', 'text' => 'Gagal menghapus pengguna dari database: ' . $stmt_delete->error];
            }
            $stmt_delete->close();
        } else {
            $message_after_action = ['type' => 'error', 'text' => 'Gagal menyiapkan statement hapus pengguna: ' . $conn->error];
        }
    }
    $_SESSION['admin_message'] = $message_after_action;
    header("Location: admin_users.php"); 
    exit;
}
// --- AKHIR LOGIKA HAPUS PENGGUNA ---
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>Kelola Pengguna - Admin Alunan</title>
  <link rel="shortcut icon" href="images/favicon.png" type="">
  <link rel="stylesheet" type="text/css" href="css/bootstrap.css" />
  <link href="css/font-awesome.min.css" rel="stylesheet" />
  <link href="css/style.css" rel="stylesheet" />
  <link href="css/responsive.css" rel="stylesheet" />
  <style>
    /* (Salin CSS dari admin.php untuk konsistensi) */
    body { font-family: 'Open Sans', sans-serif; background-color: #f4f7f6; color: #333; display: flex; min-height: 100vh; overflow-x: hidden; }
    .admin-wrapper { display: flex; width: 100%; }
    .admin-sidebar { width: 260px; background-color: #222831; color: #ffffff; padding: 20px 0; height: 100vh; position: fixed; top: 0; left: 0; overflow-y: auto; transition: width 0.3s ease; z-index: 1030;}
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
    .card-admin .card-header-admin { font-family: 'Open Sans', sans-serif; font-size: 1.25rem; font-weight: 600; color: #222831; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;}
    .card-admin .card-body-admin p { color: #333; line-height: 1.6; }
    .btn-admin-action { padding: 6px 12px; font-size: 0.875rem; background-color: #ffbe33; color: #ffffff; border-radius: 5px; text-decoration: none; transition: background-color 0.3s ease; border: none; cursor: pointer; font-weight: 500; margin-left: 5px; }
    .btn-admin-action:hover { background-color: #e69c00; color: #ffffff; }
    .btn-admin-action-edit { background-color: #17a2b8; } 
    .btn-admin-action-edit:hover { background-color: #117a8b; }
    .btn-admin-action-danger { background-color: #dc3545; } 
    .btn-admin-action-danger:hover { background-color: #c82333; }
    .table-admin { width: 100%; margin-bottom: 1rem; color: #212529; background-color: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .table-admin th, .table-admin td { padding: 0.8rem 1rem; vertical-align: middle; border-top: 1px solid #dee2e6; font-family: 'Open Sans', sans-serif; font-size:0.95rem; }
    .table-admin thead th { vertical-align: bottom; border-bottom: 2px solid #ffbe33; background-color: #222831; color: #ffffff; font-weight: 600; }
    .table-admin tbody tr:hover { background-color: #f8f9fa; }
    .table-admin td .fa { margin-right: 5px; }
    .message { margin-top: 0; margin-bottom: 15px; padding: 10px; border-radius: 5px; font-size: 0.9em; } 
    .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    #sidebarCollapse { display: none; padding: 10px; background-color: #ffbe33; color: #222831; border: none; border-radius: 5px; font-size: 1.2rem; cursor: pointer; position: fixed; top: 15px; left: 15px; z-index: 1031; }
    @media (max-width: 768px) { .admin-sidebar { width: 0; } .admin-sidebar.active { width: 260px; } .admin-content { margin-left: 0; width: 100%; } #sidebarCollapse { display: block; } /* ... sisa media query ... */ .table-admin th, .table-admin td {font-size: 0.85rem; padding: 0.6rem;} .btn-admin-action {margin-bottom:5px; display: block; width: fit-content;} }
  </style>
</head>
<body>
<div class="admin-wrapper">
    <nav class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <h3>Alunan</h3>
            <div class="admin-welcome">
                Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                <br><small>(Role: <?php echo htmlspecialchars($_SESSION['user_role']); ?>)</small>
            </div>
        </div>
        <ul class="components">
            <li class="<?php echo ($current_page == 'admin.php') ? 'active' : ''; ?>">
                <a href="admin.php"><i class="fa fa-tachometer"></i> Dashboard</a>
            </li>
            <li class="<?php echo ($current_page == 'admin_menu.php' || $current_page == 'admin_menu_form.php') ? 'active' : ''; ?>">
                <a href="admin_menu.php"><i class="fa fa-cutlery"></i> Kelola Menu</a>
            </li>
            <li class="<?php echo ($current_page == 'admin_users.php' || $current_page == 'admin_user_form.php') ? 'active' : ''; ?>">
                <a href="admin_users.php"><i class="fa fa-users"></i> Kelola Pengguna</a>
            </li>
            <li class="<?php echo ($current_page == 'admin_orders.php' || $current_page == 'admin_order_detail.php') ? 'active' : ''; ?>">
                <a href="admin_orders.php"><i class="fa fa-history"></i> Riwayat Pesanan</a>
            </li>
            <li> <a href="#"><i class="fa fa-calendar"></i> Kelola Reservasi</a>
            </li>
        </ul>
        <div class="logout-link-container">
            <a href="logout.php" class="btn_logout_admin"><i class="fa fa-sign-out"></i> Logout</a>
        </div>
    </nav>
    <button type="button" id="sidebarCollapse" class="btn"><i class="fa fa-bars"></i></button>
    <div class="admin-content" id="adminContent">
        <div class="content-header"><h2>Kelola Pengguna</h2></div>
        <div class="container-fluid">
            <?php if (!empty($feedback_message)) echo $feedback_message; ?>
            <div class="row">
                <div class="col-lg-12">
                    <div class="card-admin">
                        <div class="card-header-admin">
                            Daftar Pengguna Terdaftar
                            </div>
                        <div class="card-body-admin table-responsive">
                             <table class="table-admin">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Role</th>
                                        <th>Terdaftar Sejak</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql_users_list = "SELECT id, username, role, created_at FROM users ORDER BY created_at DESC";
                                    $result_users_list = $conn->query($sql_users_list);
                                    if ($result_users_list && $result_users_list->num_rows > 0) {
                                        while($row_user = $result_users_list->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>" . $row_user['id'] . "</td>";
                                            echo "<td>" . htmlspecialchars($row_user['username']) . "</td>";
                                            echo "<td>" . htmlspecialchars(ucfirst($row_user['role'])) . "</td>";
                                            echo "<td>" . date('d M Y, H:i', strtotime($row_user['created_at'])) . "</td>";
                                            echo "<td>
                                                    <a href='admin_user_form.php?action=edit&id=" . $row_user['id'] . "' class='btn btn-sm btn-admin-action btn-admin-action-edit' title='Edit Pengguna'><i class='fa fa-pencil'></i> Edit</a> ";
                                            
                                            // Logika tombol hapus dengan proteksi
                                            if (strtolower($row_user['username']) !== 'admin' && $row_user['id'] !== $_SESSION['user_id']) {
                                                echo "<a href='admin_users.php?action=delete&id=" . $row_user['id'] . "' 
                                                       class='btn btn-sm btn-admin-action btn-admin-action-danger' 
                                                       title='Hapus Pengguna'
                                                       onclick='return confirm(\"Apakah Anda yakin ingin menghapus pengguna: " . htmlspecialchars(addslashes($row_user['username']), ENT_QUOTES) . "?\")'>
                                                       <i class='fa fa-trash'></i> Hapus
                                                    </a>";
                                            } else {
                                                echo "<button class='btn btn-sm btn-admin-action btn-admin-action-danger' disabled title='Tidak dapat dihapus'><i class='fa fa-trash'></i> Hapus</button>";
                                            }
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' class='text-center'>Belum ada pengguna terdaftar.</td></tr>";
                                    }
                                    // $conn->close(); // Ditutup di akhir skrip jika ini query terakhir
                                    ?>
                                </tbody>
                            </table>
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
        // Inisialisasi tooltip Bootstrap jika ada
        if (typeof $('[data-toggle="tooltip"]').tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }
        // Inisialisasi popover Bootstrap jika ada
        if (typeof $('[data-toggle="popover"]').popover === 'function') {
            $('[data-toggle="popover"]').popover();
        }
        
        $('#sidebarCollapse').on('click', function () {
            $('#adminSidebar').toggleClass('active');
        });

        // Menutup alert secara otomatis setelah beberapa detik (opsional)
        // window.setTimeout(function() {
        // $(".alert").fadeTo(500, 0).slideUp(500, function(){
        //     $(this).remove(); 
        // });
        // }, 4000); // 4 detik
    });
  </script>
</body>
</html>