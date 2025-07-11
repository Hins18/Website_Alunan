<?php
// alunantest/admin_orders.php
session_start();
require_once 'db_config.php';

// 1. Cek apakah pengguna sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 2. Cek apakah pengguna adalah admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['page_message'] = ['type' => 'danger', 'text' => 'Anda tidak memiliki hak akses ke halaman admin.'];
    header("Location: index.php");
    exit;
}

$current_page = basename($_SERVER['PHP_SELF']);
$feedback_message = '';

// Ambil pesan dari session jika ada
if (isset($_SESSION['admin_message'])) {
    $message_type = $_SESSION['admin_message']['type'] ?? 'info';
    $message_text = $_SESSION['admin_message']['text'] ?? 'Tidak ada pesan.';
    $feedback_message = "<div class='container-fluid mt-3'><div class='alert alert-" . htmlspecialchars($message_type) . " alert-dismissible fade show' role='alert'>" .
                       htmlspecialchars($message_text) .
                       "<button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button></div></div>";
    unset($_SESSION['admin_message']);
}

// --- PENGAMBILAN SEMUA DATA RIWAYAT PESANAN ---
$semua_riwayat_pesanan = [];
// Tidak ada parameter yang di-bind di query ini, jadi tidak ada bind_param
$sql_all_orders = "SELECT o.id, o.nama_pemesan, o.total_harga, o.status_pesanan, o.created_at, u.username 
                   FROM orders o
                   LEFT JOIN users u ON o.user_id = u.id
                   ORDER BY o.created_at DESC";
$result_all_orders = $conn->query($sql_all_orders); // Langsung eksekusi query

if ($result_all_orders === false) {
    // Tangani error query jika ada
    $feedback_message = "<div class='container-fluid mt-3'><div class='alert alert-danger'>Error mengambil data pesanan: " . htmlspecialchars($conn->error) . "</div></div>";
} elseif ($result_all_orders->num_rows > 0) {
    while ($row_order = $result_all_orders->fetch_assoc()) {
        $semua_riwayat_pesanan[] = $row_order; // Ini adalah baris 44 (atau sekitar itu)
    }
}
// --- AKHIR PENGAMBILAN SEMUA DATA RIWAYAT PESANAN ---
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>Riwayat Pesanan - Admin Alunan</title>
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
    .btn-admin-action-view { background-color: #17a2b8; } 
    .btn-admin-action-view:hover { background-color: #117a8b; }
    .table-admin { width: 100%; margin-bottom: 1rem; color: #212529; background-color: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .table-admin th, .table-admin td { padding: 0.8rem 1rem; vertical-align: middle; border-top: 1px solid #dee2e6; font-family: 'Open Sans', sans-serif; font-size:0.95rem; }
    .table-admin thead th { vertical-align: bottom; border-bottom: 2px solid #ffbe33; background-color: #222831; color: #ffffff; font-weight: 600; }
    .table-admin tbody tr:hover { background-color: #f8f9fa; }
    .table-admin td .fa { margin-right: 5px; }
    .status-pending { background-color: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; display: inline-block; }
    .status-diproses { background-color: #d1ecf1; color: #0c5460; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; display: inline-block; }
    .status-dikirim { background-color: #cfe2ff; color: #004085; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; display: inline-block; }
    .status-selesai { background-color: #d4edda; color: #155724; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; display: inline-block; }
    .status-dibatalkan { background-color: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; display: inline-block; }
    .message { margin-top: 0; margin-bottom: 15px; padding: 10px; border-radius: 5px; font-size: 0.9em; } 
    #sidebarCollapse { display: none; padding: 10px; background-color: #ffbe33; color: #222831; border: none; border-radius: 5px; font-size: 1.2rem; cursor: pointer; position: fixed; top: 15px; left: 15px; z-index: 1031; }
    @media (max-width: 768px) { .admin-sidebar { width: 0; } .admin-sidebar.active { width: 260px; } .admin-content { margin-left: 0; width: 100%; } #sidebarCollapse { display: block; } .admin-sidebar .sidebar-header h3 { font-size: 1.8rem; } .admin-sidebar ul li a { padding: 10px 15px; font-size: 1em; } .table-admin th, .table-admin td {font-size: 0.85rem; padding: 0.6rem;} .btn-admin-action {margin-bottom:5px; display: block; width: fit-content;} }
  </style>
</head>
<body>
<div class="admin-wrapper">
    <nav class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header"><h3>Alunan</h3><div class="admin-welcome">Admin: <?php echo htmlspecialchars($_SESSION['username']); ?></div></div>
        <ul class="components">
            <li class="<?php echo ($current_page == 'admin.php') ? 'active' : ''; ?>"><a href="admin.php"><i class="fa fa-tachometer"></i> Dashboard</a></li>
            <li class="<?php echo ($current_page == 'admin_menu.php' || $current_page == 'admin_menu_form.php') ? 'active' : ''; ?>"><a href="admin_menu.php"><i class="fa fa-cutlery"></i> Kelola Menu</a></li>
            <li class="<?php echo ($current_page == 'admin_users.php' || $current_page == 'admin_user_form.php') ? 'active' : ''; ?>"><a href="admin_users.php"><i class="fa fa-users"></i> Kelola Pengguna</a></li>
            <li class="<?php echo ($current_page == 'admin_orders.php' || $current_page == 'admin_order_detail.php') ? 'active' : ''; ?>"><a href="admin_orders.php"><i class="fa fa-history"></i> Riwayat Pesanan</a></li>
            <li><a href="#"><i class="fa fa-calendar"></i> Kelola Reservasi</a></li>
        </ul>
        <div class="logout-link-container"><a href="logout.php" class="btn_logout_admin"><i class="fa fa-sign-out"></i> Logout</a></div>
    </nav>
    <button type="button" id="sidebarCollapse" class="btn"><i class="fa fa-bars"></i></button>
    <div class="admin-content" id="adminContent">
        <div class="content-header"><h2>Riwayat Semua Pesanan</h2></div>
        <div class="container-fluid">
            <?php if (!empty($feedback_message)) echo $feedback_message; ?>
            <div class="row">
                <div class="col-lg-12">
                    <div class="card-admin">
                        <div class="card-header-admin">Daftar Semua Pesanan</div>
                        <div class="card-body-admin table-responsive">
                            <?php if (!empty($semua_riwayat_pesanan)): ?>
                                <table class="table-admin">
                                    <thead>
                                        <tr>
                                            <th>ID Pesanan</th>
                                            <th>Nama Pemesan</th>
                                            <th>Username</th>
                                            <th>Total Harga</th>
                                            <th>Status</th>
                                            <th>Tanggal Pesan</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($semua_riwayat_pesanan as $pesanan): ?>
                                            <tr>
                                                <td>#<?php echo $pesanan['id']; ?></td>
                                                <td><?php echo htmlspecialchars($pesanan['nama_pemesan']); ?></td>
                                                <td><?php echo htmlspecialchars($pesanan['username'] ?? 'Tamu'); ?></td>
                                                <td>Rp <?php echo number_format($pesanan['total_harga'], 0, ',', '.'); ?></td>
                                                <td><span class="status-<?php echo htmlspecialchars(strtolower($pesanan['status_pesanan'])); ?>"><?php echo htmlspecialchars(ucfirst($pesanan['status_pesanan'])); ?></span></td>
                                                <td><?php echo date('d M Y, H:i', strtotime($pesanan['created_at'])); ?></td>
                                                <td>
                                                    <a href="admin_order_detail.php?order_id=<?php echo $pesanan['id']; ?>" class="btn btn-sm btn-admin-action btn-admin-action-view" title="Lihat Detail"><i class="fa fa-eye"></i> Detail</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-center">Belum ada riwayat pesanan.</p>
                            <?php endif; ?>
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