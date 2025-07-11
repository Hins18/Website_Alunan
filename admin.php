<?php
// alunantest/admin.php
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

$total_menu = 0;
$total_pengguna = 0;
$total_pesanan_selesai = 0; // Untuk contoh, Anda perlu query yang sesuai

$sql_menu_count = "SELECT COUNT(*) as total_menu FROM makanan_minuman";
$result_menu_count = $conn->query($sql_menu_count);
if ($result_menu_count && $result_menu_count->num_rows > 0) {
    $row_menu_count = $result_menu_count->fetch_assoc();
    $total_menu = $row_menu_count['total_menu'];
}

$sql_users_count = "SELECT COUNT(*) as total_users FROM users";
$result_users_count = $conn->query($sql_users_count);
if ($result_users_count && $result_users_count->num_rows > 0) {
    $row_users_count = $result_users_count->fetch_assoc();
    $total_pengguna = $row_users_count['total_users'];
}

// Query untuk mengambil data pesanan (misalnya, 10 terbaru)
$riwayat_pesanan_terbaru = [];
$sql_orders_recent = "SELECT o.id, o.nama_pemesan, o.total_harga, o.status_pesanan, o.created_at, u.username 
                      FROM orders o
                      LEFT JOIN users u ON o.user_id = u.id
                      ORDER BY o.created_at DESC LIMIT 10"; // Ambil 10 terbaru
$result_orders_recent = $conn->query($sql_orders_recent);
if ($result_orders_recent && $result_orders_recent->num_rows > 0) {
    while ($row_order = $result_orders_recent->fetch_assoc()) {
        $riwayat_pesanan_terbaru[] = $row_order;
    }
}

// Query untuk menghitung pesanan selesai (contoh)
$sql_orders_selesai = "SELECT COUNT(*) as total_selesai FROM orders WHERE status_pesanan = 'selesai'";
$result_orders_selesai = $conn->query($sql_orders_selesai);
if ($result_orders_selesai && $result_orders_selesai->num_rows > 0) {
    $row_orders_selesai = $result_orders_selesai->fetch_assoc();
    $total_pesanan_selesai = $row_orders_selesai['total_selesai'];
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>Admin Dashboard - Alunan</title>
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
    #sidebarCollapse { display: none; padding: 10px; background-color: #ffbe33; color: #222831; border: none; border-radius: 5px; font-size: 1.2rem; cursor: pointer; position: fixed; top: 15px; left: 15px; z-index: 1031; }
    @media (max-width: 768px) { .admin-sidebar { width: 0; } .admin-sidebar.active { width: 260px; } .admin-content { margin-left: 0; width: 100%; } #sidebarCollapse { display: block; } .admin-sidebar .sidebar-header h3 { font-size: 1.8rem; } .admin-sidebar ul li a { padding: 10px 15px; font-size: 1em; } .table-admin th, .table-admin td {font-size: 0.85rem; padding: 0.6rem;} .btn-admin-action {margin-bottom:5px; display: block; width: fit-content;} }
  </style>
</head>
<body>
<div class="admin-wrapper">
    <nav class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header"><h3>Alunan</h3><div class="admin-welcome">Selamat Datang, <?php echo htmlspecialchars($_SESSION['username']); ?>!<br><small>(Role: <?php echo htmlspecialchars($_SESSION['user_role']); ?>)</small></div></div>
        <ul class="components">
            <li class="<?php echo ($current_page == 'admin.php') ? 'active' : ''; ?>"><a href="admin.php"><i class="fa fa-tachometer"></i> Dashboard</a></li>
            <li class="<?php echo ($current_page == 'admin_menu.php') ? 'active' : ''; ?>"><a href="admin_menu.php"><i class="fa fa-cutlery"></i> Kelola Menu</a></li>
            <li class="<?php echo ($current_page == 'admin_users.php') ? 'active' : ''; ?>"><a href="admin_users.php"><i class="fa fa-users"></i> Kelola Pengguna</a></li>
            <li class="<?php echo (strpos($current_page, 'admin_orders.php') !== false || $current_page == 'admin_order_detail.php') ? 'active' : ''; ?>"><a href="admin_orders.php"><i class="fa fa-history"></i> Riwayat Pesanan</a></li>
            <li class="<?php echo ($current_page == 'admin_reviews.php') ? 'active' : ''; ?>"><a href="admin_reviews.php"><i class="fa fa-comments-o"></i> Moderasi Review</a></li>
            <li><a href="#"><i class="fa fa-calendar"></i> Kelola Reservasi</a></li>
        </ul>
        <div class="logout-link-container"><a href="logout.php" class="btn_logout_admin"><i class="fa fa-sign-out"></i> Logout</a></div>
    </nav>
    <button type="button" id="sidebarCollapse" class="btn"><i class="fa fa-bars"></i></button>

    <div class="admin-content" id="adminContent">
        <div class="content-header"><h2>Dashboard Utama</h2></div>
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card-admin">
                        <div class="card-header-admin">Selamat Datang di Dashboard Admin</div>
                        <div class="card-body-admin">
                            <p>Ini adalah halaman utama dashboard Anda. Dari sini Anda dapat mengelola berbagai aspek website Alunan.</p>
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <div class="card-admin" style="background-color: #ffbe33; color: #222831;">
                                        <div class="card-body-admin text-center">
                                            <i class="fa fa-cutlery fa-3x mb-2"></i><h4>Total Menu</h4>
                                            <p class="h2"><?php echo $total_menu; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                     <div class="card-admin" style="background-color: #222831; color: #ffffff;">
                                        <div class="card-body-admin text-center">
                                            <i class="fa fa-users fa-3x mb-2"></i><h4>Total Pengguna</h4>
                                            <p class="h2"><?php echo $total_pengguna; ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                     <div class="card-admin" style="background-color: #e9ecef; color: #222831;">
                                        <div class="card-body-admin text-center">
                                            <i class="fa fa-check-circle-o fa-3x mb-2"></i> <h4>Pesanan Selesai</h4>
                                            <p class="h2"><?php echo $total_pesanan_selesai; ?></p> 
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-lg-12">
                    <div class="card-admin">
                        <div class="card-header-admin">
                            Riwayat Pesanan Terbaru (Maks. 10)
                            <a href="admin_orders.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                        </div>
                        <div class="card-body-admin table-responsive">
                            <?php if (!empty($riwayat_pesanan_terbaru)): ?>
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
                                        <?php foreach ($riwayat_pesanan_terbaru as $pesanan): ?>
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
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
  <script src="js/bootstrap.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    $(document).ready(function () {
        $('#sidebarCollapse').on('click', function () {
            $('#adminSidebar').toggleClass('active');
        });
        // (Kode Chart.js Anda yang sudah ada)
    });
  </script>
</body>
</html>