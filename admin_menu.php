<?php
// alunantest/admin_menu.php
session_start();
require_once 'db_config.php'; 

// Proteksi halaman
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}
// --- AWAL PENAMBAHAN: Pengecekan Role Admin ---
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['page_message'] = ['type' => 'danger', 'text' => 'Anda tidak memiliki hak akses ke halaman admin.'];
    header("Location: index.php"); 
    exit;
}
// --- AKHIR PENAMBAHAN ---

$current_page = basename($_SERVER['PHP_SELF']);
$message = ''; 

if (isset($_SESSION['admin_message'])) {
    $message_type = $_SESSION['admin_message']['type'] ?? 'info'; 
    $message_text = $_SESSION['admin_message']['text'] ?? 'Tidak ada pesan.';
    $message = "<div class='alert alert-" . htmlspecialchars($message_type) . " message " . htmlspecialchars($message_type) . "-message'>" . htmlspecialchars($message_text) . "</div>";
    unset($_SESSION['admin_message']); 
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['kd'])) {
    $kd_to_delete = $_GET['kd'];
    $photo_path_to_delete = null;

    $stmt_get_photo = $conn->prepare("SELECT FT_BRNG FROM makanan_minuman WHERE KD_BRNG = ?");
    if ($stmt_get_photo) {
        $stmt_get_photo->bind_param("s", $kd_to_delete);
        $stmt_get_photo->execute();
        $result_photo = $stmt_get_photo->get_result();
        if ($result_photo->num_rows === 1) {
            $item_photo = $result_photo->fetch_assoc();
            $photo_path_to_delete = $item_photo['FT_BRNG'];
        }
        $stmt_get_photo->close();
    } else {
        $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Gagal menyiapkan data foto untuk dihapus: ' . $conn->error];
        header("Location: admin_menu.php"); 
        exit;
    }

    $stmt_delete = $conn->prepare("DELETE FROM makanan_minuman WHERE KD_BRNG = ?");
    if ($stmt_delete) {
        $stmt_delete->bind_param("s", $kd_to_delete);
        if ($stmt_delete->execute()) {
            if ($stmt_delete->affected_rows > 0) {
                if (!empty($photo_path_to_delete) && file_exists($photo_path_to_delete)) {
                    if (!@unlink($photo_path_to_delete)) {
                        $_SESSION['admin_message'] = ['type' => 'warning', 'text' => 'Item menu berhasil dihapus dari database, tetapi file gambar gagal dihapus dari server.'];
                    } else {
                         $_SESSION['admin_message'] = ['type' => 'success', 'text' => 'Item menu dan file gambar berhasil dihapus.'];
                    }
                } else {
                    $_SESSION['admin_message'] = ['type' => 'success', 'text' => 'Item menu berhasil dihapus (tidak ada file gambar terkait atau file sudah tidak ada).'];
                }
            } else {
                $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Gagal menghapus: Item menu dengan kode tersebut tidak ditemukan.'];
            }
        } else {
            $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Gagal menghapus item menu dari database: ' . $stmt_delete->error];
        }
        $stmt_delete->close();
    } else {
        $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Gagal menyiapkan statement hapus: ' . $conn->error];
    }
    header("Location: admin_menu.php"); 
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>Kelola Menu - Admin Alunan</title>
  <link rel="shortcut icon" href="images/favicon.png" type="">
  <link rel="stylesheet" type="text/css" href="css/bootstrap.css" />
  <link href="css/font-awesome.min.css" rel="stylesheet" />
  <link href="css/style.css" rel="stylesheet" />
  <link href="css/responsive.css" rel="stylesheet" />
  <style>
    /* (CSS Anda yang sudah ada) */
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
    .card-admin .card-header-admin { font-family: 'Open Sans', sans-serif; font-size: 1.25rem; font-weight: 600; color: #222831; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
    .card-admin .card-body-admin p { color: #333; line-height: 1.6; }
    .btn-admin-action { padding: 8px 15px; font-size: 0.9rem; background-color: #ffbe33; color: #ffffff; border-radius: 5px; text-decoration: none; transition: background-color 0.3s ease; border: none; cursor: pointer; font-weight: 500; margin-left: 5px; }
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
    .table-admin .item-image { max-width: 60px; max-height: 60px; border-radius: 4px; object-fit: cover; }
    .message { margin-top: 0; margin-bottom: 15px; padding: 10px; border-radius: 5px; font-size: 0.9em; } 
    .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    #sidebarCollapse { display: none; padding: 10px; background-color: #ffbe33; color: #222831; border: none; border-radius: 5px; font-size: 1.2rem; cursor: pointer; position: fixed; top: 15px; left: 15px; z-index: 1031; }
    @media (max-width: 768px) { 
        .admin-sidebar { width: 0; }
        .admin-sidebar.active { width: 260px; }
        .admin-content { margin-left: 0; width: 100%; }
        #sidebarCollapse { display: block; }
        .admin-sidebar .sidebar-header h3 { font-size: 1.8rem; }
        .admin-sidebar ul li a { padding: 10px 15px; font-size: 1em; }
        .table-admin th, .table-admin td {font-size: 0.85rem; padding: 0.6rem;} .btn-admin-action {margin-bottom:5px; display: block; width: fit-content;}
    }
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
        <div class="content-header">
             <h2>Kelola Menu Makanan & Minuman</h2>
        </div>
        <div class="container-fluid">
            <?php if (!empty($message)) echo $message; ?>
            <div class="row">
                <div class="col-lg-12">
                    <div class="card-admin">
                        <div class="card-header-admin">
                            Daftar Item Menu
                            <a href="admin_menu_form.php?action=add" class="btn btn-admin-action"><i class="fa fa-plus"></i> Tambah Item Baru</a>
                        </div>
                        <div class="card-body-admin table-responsive"> 
                            <table class="table-admin">
                                <thead>
                                    <tr>
                                        <th>Foto</th>
                                        <th>Kode</th>
                                        <th>Nama</th>
                                        <th>Harga</th>
                                        <th>Jenis</th>
                                        <th>Kategori</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sql = "SELECT id, KD_BRNG, NM_BRNG, HARGA_BRNG, FT_BRNG, JENIS, KATEGORI_MENU FROM makanan_minuman ORDER BY JENIS, NM_BRNG";
                                    $result = $conn->query($sql);
                                    if ($result && $result->num_rows > 0) {
                                        while($row = $result->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>";
                                            if (!empty($row['FT_BRNG']) && file_exists($row['FT_BRNG'])) {
                                                echo "<img src='" . htmlspecialchars($row['FT_BRNG']) . "' alt='" . htmlspecialchars($row['NM_BRNG']) . "' class='item-image'>";
                                            } else {
                                                echo "<img src='images/default_food.png' alt='Default Image' class='item-image'>"; 
                                            }
                                            echo "</td>";
                                            echo "<td>" . htmlspecialchars($row['KD_BRNG']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['NM_BRNG']) . "</td>";
                                            echo "<td>Rp " . number_format($row['HARGA_BRNG'], 0, ',', '.') . "</td>";
                                            echo "<td>" . htmlspecialchars($row['JENIS']) . "</td>";
                                            echo "<td>" . htmlspecialchars($row['KATEGORI_MENU'] ?? '-') . "</td>";
                                            echo "<td>
                                                    <a href='admin_menu_form.php?action=edit&kd=" . htmlspecialchars($row['KD_BRNG']) . "' class='btn btn-sm btn-admin-action btn-admin-action-edit' title='Edit'><i class='fa fa-pencil'></i></a>
                                                    <a href='admin_menu.php?action=delete&kd=" . htmlspecialchars($row['KD_BRNG']) . "' 
                                                       class='btn btn-sm btn-admin-action btn-admin-action-danger' 
                                                       title='Hapus'
                                                       onclick='return confirm(\"Apakah Anda yakin ingin menghapus item menu: " . htmlspecialchars(addslashes($row['NM_BRNG']), ENT_QUOTES) . " (" . htmlspecialchars($row['KD_BRNG']) . ")?\")'>
                                                       <i class='fa fa-trash'></i>
                                                    </a>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='7' class='text-center'>Belum ada data menu.</td></tr>";
                                    }
                                    // $conn->close(); // Pindahkan penutupan koneksi ke akhir skrip jika hanya ada satu blok query utama
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
        $('#sidebarCollapse').on('click', function () {
            $('#adminSidebar').toggleClass('active');
        });
    });
</script>
</body>
</html>