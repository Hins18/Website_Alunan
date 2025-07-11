<?php
// alunantest/admin_order_detail.php
session_start();
require_once 'db_config.php';

// 1. Cek apakah pengguna sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit;
}

// 2. Cek apakah pengguna adalah admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['page_message'] = ['type' => 'danger', 'text' => 'Anda tidak memiliki hak akses.'];
    header("Location: index.php");
    exit;
}

$current_page = 'admin_orders.php'; // Menandai "Riwayat Pesanan" sebagai aktif di sidebar
$order_id = isset($_GET['order_id']) ? trim($_GET['order_id']) : '';
$order_data = null;
$order_items_data = [];
$page_error_message = ''; // Untuk error spesifik halaman ini
$feedback_message = '';   // Untuk pesan dari session (misal setelah update status)

// Ambil pesan dari session jika ada
if (isset($_SESSION['admin_order_detail_message'])) {
    $message_type = $_SESSION['admin_order_detail_message']['type'] ?? 'info';
    $message_text = $_SESSION['admin_order_detail_message']['text'] ?? 'Tidak ada pesan.';
    $feedback_message = "<div class='alert alert-" . htmlspecialchars($message_type) . " alert-dismissible fade show' role='alert'>" .
                       htmlspecialchars($message_text) .
                       "<button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button></div>";
    unset($_SESSION['admin_order_detail_message']);
}


if (!empty($order_id)) {
    // Ambil data pesanan utama
    $stmt_order = $conn->prepare("SELECT o.*, u.username 
                                  FROM orders o 
                                  LEFT JOIN users u ON o.user_id = u.id 
                                  WHERE o.id = ?");
    if ($stmt_order) {
        $stmt_order->bind_param("s", $order_id);
        $stmt_order->execute();
        $result_order = $stmt_order->get_result();
        if ($result_order->num_rows === 1) {
            $order_data = $result_order->fetch_assoc();

            // Ambil item-item dalam pesanan tersebut
            // Kita juga bisa JOIN dengan makanan_minuman untuk mendapatkan foto terbaru jika perlu,
            // tapi untuk riwayat, nm_brng_saat_pesan dan harga_satuan_saat_pesan lebih penting.
            // Untuk foto, kita bisa ambil dari FT_BRNG di makanan_minuman jika masih ada, atau gunakan yang tersimpan jika ada.
            $stmt_items = $conn->prepare("SELECT oi.*, mm.FT_BRNG 
                                          FROM order_items oi
                                          LEFT JOIN makanan_minuman mm ON oi.kd_brng = mm.KD_BRNG
                                          WHERE oi.order_id = ?");
            if ($stmt_items) {
                $stmt_items->bind_param("s", $order_id);
                $stmt_items->execute();
                $result_items = $stmt_items->get_result();
                while ($row_item = $result_items->fetch_assoc()) {
                    $order_items_data[] = $row_item;
                }
                $stmt_items->close();
            } else {
                $page_error_message = "Gagal mengambil item pesanan: " . $conn->error;
            }
        } else {
            $page_error_message = "Pesanan dengan ID #" . htmlspecialchars($order_id) . " tidak ditemukan.";
        }
        $stmt_order->close();
    } else {
        $page_error_message = "Gagal menyiapkan data pesanan: " . $conn->error;
    }
} else {
    $page_error_message = "ID Pesanan tidak valid atau tidak diberikan.";
}

// Logika untuk update status pesanan
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status_pesanan']) && $order_data) {
    $new_status = $_POST['status_pesanan_baru'] ?? '';
    $allowed_statuses = ['pending', 'diproses', 'dikirim', 'selesai', 'dibatalkan'];

    if (in_array($new_status, $allowed_statuses)) {
        $stmt_update_status = $conn->prepare("UPDATE orders SET status_pesanan = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt_update_status) {
            $stmt_update_status->bind_param("ss", $new_status, $order_id);
            if ($stmt_update_status->execute()) {
                $_SESSION['admin_order_detail_message'] = ['type' => 'success', 'text' => 'Status pesanan berhasil diperbarui menjadi ' . ucfirst($new_status) . '.'];
            } else {
                $_SESSION['admin_order_detail_message'] = ['type' => 'error', 'text' => 'Gagal memperbarui status pesanan: ' . $stmt_update_status->error];
            }
            $stmt_update_status->close();
        } else {
            $_SESSION['admin_order_detail_message'] = ['type' => 'error', 'text' => 'Gagal menyiapkan update status: ' . $conn->error];
        }
        // Redirect untuk refresh halaman dan menampilkan pesan dari session
        header("Location: admin_order_detail.php?order_id=" . $order_id);
        exit;
    } else {
        $error_message_form = "Status pesanan tidak valid."; // Pesan error untuk form update status
    }
}


?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>Detail Pesanan #<?php echo htmlspecialchars($order_id); ?> - Admin Alunan</title>
  <link rel="shortcut icon" href="images/favicon.png" type="">
  <link rel="stylesheet" type="text/css" href="css/bootstrap.css" />
  <link href="css/font-awesome.min.css" rel="stylesheet" />
  <link href="css/style.css" rel="stylesheet" />
  <link href="css/responsive.css" rel="stylesheet" />
  <style>
    /* Salin CSS dari admin.php/admin_orders.php untuk konsistensi */
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
    .card-admin .card-body-admin p, .order-details-list li { color: #333; line-height: 1.7; font-size: 0.95rem;}
    .btn-admin-action { padding: 6px 12px; font-size: 0.875rem; background-color: #ffbe33; color: #ffffff; border-radius: 5px; text-decoration: none; transition: background-color 0.3s ease; border: none; cursor: pointer; font-weight: 500; margin-left: 5px; }
    .btn-admin-action:hover { background-color: #e69c00; color: #ffffff; }
    .btn-admin-action-view { background-color: #17a2b8; } 
    .btn-admin-action-view:hover { background-color: #117a8b; }
    .table-admin { width: 100%; margin-bottom: 1rem; color: #212529; background-color: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .table-admin th, .table-admin td { padding: 0.8rem 1rem; vertical-align: middle; border-top: 1px solid #dee2e6; font-family: 'Open Sans', sans-serif; font-size:0.9rem; }
    .table-admin thead th { vertical-align: bottom; border-bottom: 2px solid #ffbe33; background-color: #222831; color: #ffffff; font-weight: 600; }
    .table-admin tbody tr:hover { background-color: #f8f9fa; }
    .table-admin td .fa { margin-right: 5px; }
    .table-admin .item-order-image { max-width: 50px; max-height: 50px; border-radius: 3px; object-fit: cover; }
    .status-pending { background-color: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; display: inline-block; }
    .status-diproses { background-color: #d1ecf1; color: #0c5460; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; display: inline-block; }
    .status-dikirim { background-color: #cfe2ff; color: #004085; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; display: inline-block; }
    .status-selesai { background-color: #d4edda; color: #155724; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; display: inline-block; }
    .status-dibatalkan { background-color: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 4px; font-size: 0.8em; display: inline-block; }
    .message { margin-top: 0; margin-bottom: 15px; padding: 10px; border-radius: 5px; font-size: 0.9em; } 
    #sidebarCollapse { display: none; padding: 10px; background-color: #ffbe33; color: #222831; border: none; border-radius: 5px; font-size: 1.2rem; cursor: pointer; position: fixed; top: 15px; left: 15px; z-index: 1031; }
    @media (max-width: 768px) { .admin-sidebar { width: 0; } .admin-sidebar.active { width: 260px; } .admin-content { margin-left: 0; width: 100%; } #sidebarCollapse { display: block; } /* ... sisa media query ... */ .table-admin th, .table-admin td {font-size: 0.85rem; padding: 0.6rem;} .btn-admin-action {margin-bottom:5px; display: block; width: fit-content;} }
    .order-details-list { list-style: none; padding-left: 0; }
    .order-details-list li { margin-bottom: 8px; border-bottom: 1px dotted #eee; padding-bottom: 8px;}
    .order-details-list li:last-child { border-bottom: none; }
    .order-details-list strong { display: inline-block; min-width: 180px; color: #555;}
    .form-update-status { margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;}
    .form-update-status label {font-weight: 600;}
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
        <div class="content-header">
            <h2>Detail Pesanan #<?php echo htmlspecialchars($order_id); ?></h2>
            <a href="admin_orders.php" class="btn btn-sm btn-outline-secondary"><i class="fa fa-arrow-left"></i> Kembali ke Riwayat</a>
        </div>
        <div class="container-fluid">
            <?php if (!empty($feedback_message)) echo $feedback_message; ?>
            <?php if (!empty($page_error_message)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($page_error_message); ?></div>
            <?php elseif ($order_data): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card-admin">
                            <div class="card-header-admin">Informasi Pelanggan & Pesanan</div>
                            <div class="card-body-admin">
                                <ul class="order-details-list">
                                    <li><strong>ID Pesanan:</strong> #<?php echo htmlspecialchars($order_data['id']); ?></li>
                                    <li><strong>Nama Pemesan:</strong> <?php echo htmlspecialchars($order_data['nama_pemesan']); ?></li>
                                    <li><strong>Username Akun:</strong> <?php echo htmlspecialchars($order_data['username'] ?? '<i>Tamu</i>'); ?></li>
                                    <li><strong>Nomor Telepon:</strong> <?php echo htmlspecialchars($order_data['nomor_telepon'] ?? '-'); ?></li>
                                    <li><strong>Alamat Pengiriman:</strong> <?php echo nl2br(htmlspecialchars($order_data['alamat_pengiriman'] ?? '-')); ?></li>
                                    <li><strong>Total Harga:</strong> Rp <?php echo number_format($order_data['total_harga'], 0, ',', '.'); ?></li>
                                    <li><strong>Status Saat Ini:</strong> <span class="status-<?php echo htmlspecialchars(strtolower($order_data['status_pesanan'])); ?>"><?php echo htmlspecialchars(ucfirst($order_data['status_pesanan'])); ?></span></li>
                                    <li><strong>Metode Pembayaran:</strong> <?php echo htmlspecialchars($order_data['metode_pembayaran'] ?? '-'); ?></li>
                                    <li><strong>Tanggal Pesan:</strong> <?php echo date('d M Y, H:i:s', strtotime($order_data['created_at'])); ?></li>
                                    <li><strong>Update Terakhir:</strong> <?php echo date('d M Y, H:i:s', strtotime($order_data['updated_at'])); ?></li>
                                    <li><strong>Catatan Pesanan:</strong> <?php echo nl2br(htmlspecialchars($order_data['catatan_pesanan'] ?? 'Tidak ada catatan.')); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                         <div class="card-admin">
                            <div class="card-header-admin">Update Status Pesanan</div>
                            <div class="card-body-admin">
                                <?php if (isset($error_message_form)): ?>
                                    <div class="alert alert-danger"><?php echo htmlspecialchars($error_message_form); ?></div>
                                <?php endif; ?>
                                <form method="POST" action="admin_order_detail.php?order_id=<?php echo $order_id; ?>" class="form-update-status">
                                    <div class="form-group">
                                        <label for="status_pesanan_baru">Status Baru:</label>
                                        <select name="status_pesanan_baru" id="status_pesanan_baru" class="form-control form-control-sm">
                                            <option value="pending" <?php echo ($order_data['status_pesanan'] == 'pending' ? 'selected' : ''); ?>>Pending</option>
                                            <option value="diproses" <?php echo ($order_data['status_pesanan'] == 'diproses' ? 'selected' : ''); ?>>Diproses</option>
                                            <option value="dikirim" <?php echo ($order_data['status_pesanan'] == 'dikirim' ? 'selected' : ''); ?>>Dikirim</option>
                                            <option value="selesai" <?php echo ($order_data['status_pesanan'] == 'selesai' ? 'selected' : ''); ?>>Selesai</option>
                                            <option value="dibatalkan" <?php echo ($order_data['status_pesanan'] == 'dibatalkan' ? 'selected' : ''); ?>>Dibatalkan</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_status_pesanan" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Update Status</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-lg-12">
                        <div class="card-admin">
                            <div class="card-header-admin">Item dalam Pesanan Ini</div>
                            <div class="card-body-admin table-responsive">
                                <?php if (!empty($order_items_data)): ?>
                                    <table class="table-admin">
                                        <thead>
                                            <tr>
                                                <th>Foto</th>
                                                <th>Kode Barang</th>
                                                <th>Nama Barang (saat pesan)</th>
                                                <th class="text-right">Harga Satuan</th>
                                                <th class="text-center">Kuantitas</th>
                                                <th class="text-right">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($order_items_data as $item_detail): ?>
                                                <tr>
                                                    <td>
                                                        <img src="<?php echo (!empty($item_detail['FT_BRNG']) && file_exists($item_detail['FT_BRNG'])) ? htmlspecialchars($item_detail['FT_BRNG']) : 'images/default_food.png'; ?>" alt="<?php echo htmlspecialchars($item_detail['nm_brng_saat_pesan']); ?>" class="item-order-image">
                                                    </td>
                                                    <td><?php echo htmlspecialchars($item_detail['kd_brng']); ?></td>
                                                    <td><?php echo htmlspecialchars($item_detail['nm_brng_saat_pesan']); ?></td>
                                                    <td class="text-right">Rp <?php echo number_format($item_detail['harga_satuan_saat_pesan'], 0, ',', '.'); ?></td>
                                                    <td class="text-center"><?php echo $item_detail['kuantitas']; ?></td>
                                                    <td class="text-right">Rp <?php echo number_format($item_detail['subtotal'], 0, ',', '.'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="text-center">Tidak ada item detail untuk pesanan ini.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <?php // Jika $order_data kosong tapi tidak ada $page_error_message sebelumnya ?>
                <?php if (empty($page_error_message)): ?>
                    <p class="text-center">Data pesanan tidak dapat dimuat atau tidak ditemukan.</p>
                <?php endif; ?>
            <?php endif; ?>
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