<?php
// alunantest/admin_reviews.php
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

$current_page = basename($_SERVER['PHP_SELF']);
$feedback_message = ''; // Untuk pesan dari session atau proses

// Ambil pesan dari session jika ada
if (isset($_SESSION['admin_review_message'])) {
    $message_type = $_SESSION['admin_review_message']['type'] ?? 'info';
    $message_text = $_SESSION['admin_review_message']['text'] ?? 'Tidak ada pesan.';
    $feedback_message = "<div class='alert alert-" . htmlspecialchars($message_type) . " alert-dismissible fade show' role='alert'>" .
                       htmlspecialchars($message_text) .
                       "<button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>&times;</span></button></div>";
    unset($_SESSION['admin_review_message']);
}

// Logika untuk update status review
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_id']) && isset($_POST['new_status'])) {
    $review_id_to_update = intval($_POST['review_id']);
    $new_status_to_set = $_POST['new_status'];
    $allowed_statuses = ['approved', 'rejected', 'pending']; // Status yang diizinkan

    if (in_array($new_status_to_set, $allowed_statuses)) {
        $stmt_update_status = $conn->prepare("UPDATE product_reviews SET status_review = ? WHERE id = ?");
        if ($stmt_update_status) {
            $stmt_update_status->bind_param("si", $new_status_to_set, $review_id_to_update);
            if ($stmt_update_status->execute()) {
                if ($stmt_update_status->affected_rows > 0) {
                    $_SESSION['admin_review_message'] = ['type' => 'success', 'text' => 'Status review #' . $review_id_to_update . ' berhasil diperbarui menjadi ' . ucfirst($new_status_to_set) . '.'];
                } else {
                    $_SESSION['admin_review_message'] = ['type' => 'warning', 'text' => 'Status review #' . $review_id_to_update . ' tidak berubah atau review tidak ditemukan.'];
                }
            } else {
                $_SESSION['admin_review_message'] = ['type' => 'error', 'text' => 'Gagal memperbarui status review: ' . $stmt_update_status->error];
            }
            $stmt_update_status->close();
        } else {
             $_SESSION['admin_review_message'] = ['type' => 'error', 'text' => 'Gagal menyiapkan statement update status: ' . $conn->error];
        }
        // Redirect untuk mencegah resubmit form dan refresh data
        header("Location: admin_reviews.php"); 
        exit;
    } else {
        $_SESSION['admin_review_message'] = ['type' => 'error', 'text' => 'Status baru tidak valid.'];
        header("Location: admin_reviews.php"); 
        exit;
    }
}


// --- PENGAMBILAN DATA REVIEW ---
$reviews_pending = [];
$reviews_approved = [];
$reviews_rejected = [];

// Ambil Review Pending
$sql_pending = "SELECT pr.*, u.username as reviewer_username, mm.NM_BRNG as product_name 
                FROM product_reviews pr 
                LEFT JOIN users u ON pr.user_id = u.id 
                LEFT JOIN makanan_minuman mm ON pr.kd_brng = mm.KD_BRNG 
                WHERE pr.status_review = 'pending' 
                ORDER BY pr.tanggal_review DESC";
$result_pending = $conn->query($sql_pending);
if ($result_pending) {
    while($row = $result_pending->fetch_assoc()) $reviews_pending[] = $row;
} else {
    $feedback_message .= "<div class='alert alert-danger'>Error mengambil review pending: " . htmlspecialchars($conn->error) . "</div>";
}

// Ambil Review Approved (opsional, bisa ditampilkan di tab terpisah)
$sql_approved = "SELECT pr.*, u.username as reviewer_username, mm.NM_BRNG as product_name 
                 FROM product_reviews pr 
                 LEFT JOIN users u ON pr.user_id = u.id 
                 LEFT JOIN makanan_minuman mm ON pr.kd_brng = mm.KD_BRNG 
                 WHERE pr.status_review = 'approved' 
                 ORDER BY pr.tanggal_review DESC LIMIT 10"; // Ambil 10 terbaru
$result_approved = $conn->query($sql_approved);
if ($result_approved) {
    while($row = $result_approved->fetch_assoc()) $reviews_approved[] = $row;
} else {
    $feedback_message .= "<div class='alert alert-danger'>Error mengambil review disetujui: " . htmlspecialchars($conn->error) . "</div>";
}

// Anda bisa juga mengambil review yang 'rejected' jika ingin menampilkannya
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title>Moderasi Review - Admin Alunan</title>
  <link rel="shortcut icon" href="images/favicon.png" type="">
  <link rel="stylesheet" type="text/css" href="css/bootstrap.css" />
  <link href="css/font-awesome.min.css" rel="stylesheet" />
  <link href="css/style.css" rel="stylesheet" />
  <link href="css/responsive.css" rel="stylesheet" />
  <style>
    /* Salin CSS admin dari admin.php untuk konsistensi */
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
    .btn-admin-action { padding: 5px 10px; font-size: 0.8rem; margin-right: 5px; }
    .table-admin { width: 100%; margin-bottom: 1rem; color: #212529; background-color: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .table-admin th, .table-admin td { padding: 0.75rem; vertical-align: top; border-top: 1px solid #dee2e6; font-size:0.9rem; }
    .table-admin thead th { vertical-align: bottom; border-bottom: 2px solid #ffbe33; background-color: #222831; color: #ffffff; font-weight: 600; }
    .table-admin tbody tr:hover { background-color: #f8f9fa; }
    .table-admin .rating-stars-display .fa { font-size: 0.9em; margin-right: 1px;}
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
            <li class="<?php echo (strpos($current_page, 'admin_menu') !== false) ? 'active' : ''; ?>"><a href="admin_menu.php"><i class="fa fa-cutlery"></i> Kelola Menu</a></li>
            <li class="<?php echo (strpos($current_page, 'admin_users') !== false) ? 'active' : ''; ?>"><a href="admin_users.php"><i class="fa fa-users"></i> Kelola Pengguna</a></li>
            <li class="<?php echo (strpos($current_page, 'admin_orders') !== false) ? 'active' : ''; ?>"><a href="admin_orders.php"><i class="fa fa-history"></i> Riwayat Pesanan</a></li>
            <li class="<?php echo ($current_page == 'admin_reviews.php') ? 'active' : ''; ?>"><a href="admin_reviews.php"><i class="fa fa-comments-o"></i> Moderasi Review</a></li>
            <li><a href="#"><i class="fa fa-calendar"></i> Kelola Reservasi</a></li>
        </ul>
        <div class="logout-link-container"><a href="logout.php" class="btn_logout_admin"><i class="fa fa-sign-out"></i> Logout</a></div>
    </nav>
    <button type="button" id="sidebarCollapse" class="btn"><i class="fa fa-bars"></i></button>

    <div class="admin-content" id="adminContent">
        <div class="content-header"><h2>Moderasi Review Produk</h2></div>
        <div class="container-fluid">
            <?php if (!empty($feedback_message)) echo $feedback_message; ?>

            <div class="row">
                <div class="col-lg-12">
                    <div class="card-admin">
                        <div class="card-header-admin">Review Menunggu Persetujuan</div>
                        <div class="card-body-admin table-responsive">
                            <?php if (!empty($reviews_pending)): ?>
                            <table class="table-admin table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Produk</th>
                                        <th>Pengguna</th>
                                        <th>Rating</th>
                                        <th>Komentar</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($reviews_pending as $review): 
                                        $reviewer_name = !empty($review['reviewer_username']) ? htmlspecialchars($review['reviewer_username']) : (!empty($review['nama_reviewer']) ? htmlspecialchars($review['nama_reviewer']) : 'Anonim');
                                    ?>
                                    <tr>
                                        <td><?php echo $review['id']; ?></td>
                                        <td><?php echo htmlspecialchars($review['product_name'] ?? $review['kd_brng']); ?></td>
                                        <td><?php echo $reviewer_name; ?></td>
                                        <td>
                                            <?php 
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo ($i <= $review['rating']) ? '<i class="fa fa-star" style="color: #ffbe33;"></i>' : '<i class="fa fa-star-o" style="color: #ccc;"></i>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo nl2br(htmlspecialchars($review['komentar'])); ?></td>
                                        <td><?php echo date('d M Y H:i', strtotime($review['tanggal_review'])); ?></td>
                                        <td>
                                            <form method="POST" style="display:inline-block; margin-bottom: 5px;">
                                                <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                <input type="hidden" name="new_status" value="approved">
                                                <button type="submit" name="update_status_pesanan" class="btn btn-success btn-xs"><i class="fa fa-check"></i> Setujui</button>
                                            </form>
                                            <form method="POST" style="display:inline-block;">
                                                <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                <input type="hidden" name="new_status" value="rejected">
                                                <button type="submit" name="update_status_pesanan" class="btn btn-danger btn-xs"><i class="fa fa-times"></i> Tolak</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <p class="text-center">Tidak ada review yang menunggu persetujuan saat ini.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-admin mt-4">
                        <div class="card-header-admin">Review yang Sudah Disetujui (Terbaru)</div>
                        <div class="card-body-admin table-responsive">
                            <?php if (!empty($reviews_approved)): ?>
                            <table class="table-admin table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Produk</th>
                                        <th>Pengguna</th>
                                        <th>Rating</th>
                                        <th>Komentar</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($reviews_approved as $review): 
                                         $reviewer_name = !empty($review['reviewer_username']) ? htmlspecialchars($review['reviewer_username']) : (!empty($review['nama_reviewer']) ? htmlspecialchars($review['nama_reviewer']) : 'Anonim');
                                    ?>
                                    <tr>
                                        <td><?php echo $review['id']; ?></td>
                                        <td><?php echo htmlspecialchars($review['product_name'] ?? $review['kd_brng']); ?></td>
                                        <td><?php echo $reviewer_name; ?></td>
                                        <td>
                                            <?php 
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo ($i <= $review['rating']) ? '<i class="fa fa-star" style="color: #ffbe33;"></i>' : '<i class="fa fa-star-o" style="color: #ccc;"></i>';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo nl2br(htmlspecialchars($review['komentar'])); ?></td>
                                        <td><?php echo date('d M Y H:i', strtotime($review['tanggal_review'])); ?></td>
                                        <td>
                                             <form method="POST" style="display:inline-block;">
                                                <input type="hidden" name="review_id" value="<?php echo $review['id']; ?>">
                                                <input type="hidden" name="new_status" value="rejected">
                                                <button type="submit" name="update_status_pesanan" class="btn btn-warning btn-xs"><i class="fa fa-ban"></i> Batalkan Persetujuan</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <p class="text-center">Belum ada review yang disetujui.</p>
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