<?php
// alunantest/admin_menu_form.php
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


$current_page = 'admin_menu.php'; 
$error_message = '';
$success_message = ''; 

if (isset($_SESSION['admin_message'])) {
    if ($_SESSION['admin_message']['type'] === 'success') {
        $success_message = $_SESSION['admin_message']['text'];
    } else {
        $error_message = $_SESSION['admin_message']['text'];
    }
    unset($_SESSION['admin_message']);
}

$action = $_GET['action'] ?? 'add'; 
$kd_brng_url = $_GET['kd'] ?? null;

$item = [
    'KD_BRNG' => '',
    'NM_BRNG' => '',
    'DESC_BRNG' => '',
    'HARGA_BRNG' => '',
    'FT_BRNG' => '',
    'JENIS' => '',
    'KATEGORI_MENU' => ''
];
$form_action_url = "admin_menu_form.php?action=" . htmlspecialchars($action);
$form_title = "Tambah Item Menu Baru";
$submit_button_text = "Tambah Item";
$is_editing = false;

if ($action === 'edit' && $kd_brng_url) {
    $is_editing = true;
    $form_action_url .= "&kd=" . htmlspecialchars($kd_brng_url);
    $stmt_select = $conn->prepare("SELECT KD_BRNG, NM_BRNG, DESC_BRNG, HARGA_BRNG, FT_BRNG, JENIS, KATEGORI_MENU FROM makanan_minuman WHERE KD_BRNG = ?");
    if ($stmt_select) {
        $stmt_select->bind_param("s", $kd_brng_url);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        if ($result->num_rows === 1) {
            $item = $result->fetch_assoc();
            $form_title = "Edit Item Menu: " . htmlspecialchars($item['NM_BRNG']);
            $submit_button_text = "Update Item";
        } else {
            $_SESSION['admin_message'] = ['type' => 'error', 'text' => 'Item menu tidak ditemukan.'];
            header("Location: admin_menu.php");
            exit;
        }
        $stmt_select->close();
    } else {
        $error_message = "Gagal menyiapkan data untuk diedit: " . $conn->error;
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $kd_brng_post = trim($_POST['KD_BRNG'] ?? '');
    $nm_brng_post = trim($_POST['NM_BRNG'] ?? '');
    $desc_brng_post = trim($_POST['DESC_BRNG'] ?? '');
    $harga_brng_input = trim($_POST['HARGA_BRNG'] ?? '');
    $jenis_input = $_POST['JENIS'] ?? '';
    $kategori_menu_input = $_POST['KATEGORI_MENU'] ?? '';
    $current_ft_brng = $_POST['current_FT_BRNG'] ?? ($is_editing ? $item['FT_BRNG'] : '');

    $item['KD_BRNG'] = $kd_brng_post;
    $item['NM_BRNG'] = $nm_brng_post;
    $item['DESC_BRNG'] = $desc_brng_post;
    $item['HARGA_BRNG'] = $harga_brng_input;
    $item['JENIS'] = $jenis_input;
    $item['KATEGORI_MENU'] = $kategori_menu_input;
    $item['FT_BRNG'] = $current_ft_brng;

    if (empty($kd_brng_post) || empty($nm_brng_post) || $harga_brng_input === '' || empty($jenis_input)) {
        $error_message = "Kode Barang, Nama Barang, Harga, dan Jenis wajib diisi.";
    } elseif (!is_numeric($harga_brng_input) || (float)$harga_brng_input < 0) {
        $error_message = "Harga harus berupa angka numerik positif.";
    } else {
        $ft_brng_path_to_db = $current_ft_brng;
        if (isset($_FILES['FT_BRNG']) && $_FILES['FT_BRNG']['error'] == UPLOAD_ERR_OK && $_FILES['FT_BRNG']['size'] > 0) {
            $upload_dir = 'images/';
            if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
            $file_extension = strtolower(pathinfo($_FILES['FT_BRNG']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($file_extension, $allowed_extensions)) {
                if ($_FILES['FT_BRNG']['size'] <= 2000000) {
                    $new_filename = $upload_dir . uniqid('menu_', true) . '.' . $file_extension;
                    if (move_uploaded_file($_FILES['FT_BRNG']['tmp_name'], $new_filename)) {
                        if ($is_editing && !empty($current_ft_brng) && $current_ft_brng !== $new_filename && file_exists($current_ft_brng)) {
                            @unlink($current_ft_brng);
                        }
                        $ft_brng_path_to_db = $new_filename;
                        $item['FT_BRNG'] = $new_filename;
                    } else {
                        $error_message = "Gagal mengupload file gambar baru.";
                    }
                } else {
                    $error_message = "Ukuran file gambar maksimal 2MB.";
                }
            } else {
                $error_message = "Hanya file JPG, JPEG, PNG, dan GIF yang diizinkan.";
            }
        } elseif ($action === 'add' && (!isset($_FILES['FT_BRNG']) || $_FILES['FT_BRNG']['error'] != UPLOAD_ERR_OK || $_FILES['FT_BRNG']['size'] == 0)) {
            $ft_brng_path_to_db = null;
            $item['FT_BRNG'] = null;
        }

        if (empty($error_message)) {
            $harga_brng_to_db = (float)$harga_brng_input;
            $kategori_menu_to_db = empty(trim($kategori_menu_input)) ? null : trim($kategori_menu_input);
            
            $kd_brng_safe = $conn->real_escape_string($kd_brng_post); // KD barang yang disubmit dari form
            $nm_brng_safe = $conn->real_escape_string($nm_brng_post);
            $desc_brng_safe = $conn->real_escape_string($desc_brng_post);
            $ft_brng_sql_val = $ft_brng_path_to_db ? "'" . $conn->real_escape_string($ft_brng_path_to_db) . "'" : "NULL";
            $jenis_safe = $conn->real_escape_string($jenis_input);
            $kategori_menu_sql_val = $kategori_menu_to_db ? "'" . $conn->real_escape_string($kategori_menu_to_db) . "'" : "NULL";

            if ($action === 'add') {
                $stmt_check = $conn->prepare("SELECT KD_BRNG FROM makanan_minuman WHERE KD_BRNG = ?");
                if ($stmt_check) {
                    $stmt_check->bind_param("s", $kd_brng_safe);
                    $stmt_check->execute();
                    $stmt_check->store_result();
                    if ($stmt_check->num_rows > 0) {
                        $error_message = "Kode Barang ('" . htmlspecialchars($kd_brng_safe) . "') sudah ada.";
                    }
                    $stmt_check->close();
                } else { $error_message = "Error cek KD: " . $conn->error; }

                if (empty($error_message)) {
                    $sql_insert = "INSERT INTO makanan_minuman (KD_BRNG, NM_BRNG, DESC_BRNG, HARGA_BRNG, FT_BRNG, JENIS, KATEGORI_MENU) 
                                   VALUES ('$kd_brng_safe', '$nm_brng_safe', '$desc_brng_safe', $harga_brng_to_db, $ft_brng_sql_val, '$jenis_safe', $kategori_menu_sql_val)";
                    if ($conn->query($sql_insert) === TRUE) {
                        $_SESSION['admin_message'] = ['type' => 'success', 'text' => 'Item menu berhasil ditambahkan!'];
                        header("Location: admin_menu.php");
                        exit;
                    } else {
                        $error_message = "Gagal menambahkan item: " . $conn->error;
                    }
                }
            } elseif ($action === 'edit' && $kd_brng_url) {
                // $kd_brng_url adalah KD asli dari item yang diedit (dari URL)
                $kd_brng_url_safe = $conn->real_escape_string($kd_brng_url);

                // Karena KD_BRNG di form adalah readonly, $kd_brng_safe akan sama dengan $kd_brng_url_safe
                // Tidak perlu cek duplikasi KD_BRNG baru karena tidak bisa diubah.
                
                $sql_update = "UPDATE makanan_minuman SET 
                                NM_BRNG = '$nm_brng_safe', 
                                DESC_BRNG = '$desc_brng_safe', 
                                HARGA_BRNG = $harga_brng_to_db, 
                                FT_BRNG = $ft_brng_sql_val, 
                                JENIS = '$jenis_safe', 
                                KATEGORI_MENU = $kategori_menu_sql_val 
                               WHERE KD_BRNG = '$kd_brng_url_safe'"; // Gunakan KD asli dari URL untuk WHERE clause
                
                if ($conn->query($sql_update) === TRUE) {
                    // Cek apakah ada baris yang benar-benar terupdate
                    if ($conn->affected_rows > 0) {
                        $_SESSION['admin_message'] = ['type' => 'success', 'text' => 'Item menu berhasil diperbarui!'];
                    } else {
                         // Tidak ada baris yang terupdate, mungkin karena data yang diinput sama dengan data yang sudah ada
                        $_SESSION['admin_message'] = ['type' => 'info', 'text' => 'Tidak ada perubahan data pada item menu.'];
                    }
                    header("Location: admin_menu.php");
                    exit;
                } else {
                    $error_message = "Gagal memperbarui item: " . $conn->error . " <br>Query: " . $sql_update;
                }
            }
        }
    }
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
    .card-admin .card-header-admin { font-family: 'Open Sans', sans-serif; font-size: 1.25rem; font-weight: 600; color: #222831; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
    .card-admin .card-body-admin .form-group { margin-bottom: 1.5rem; } 
    .card-admin .card-body-admin label { font-weight: 500; color: #333; }
    .form-control-admin { 
        display: block;
        width: 100%;
        padding: .575rem .95rem; 
        font-size: 1rem;
        font-weight: 400;
        line-height: 1.5;
        color: #495057;
        background-color: #fff;
        background-clip: padding-box;
        border: 1px solid #ced4da;
        border-radius: .25rem; 
        transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;
    }
    .form-control-admin:focus {
        color: #495057;
        background-color: #fff;
        border-color: #ffbe33; 
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(255, 190, 51, .25); 
    }
    textarea.form-control-admin { height: auto; min-height: 100px; }
    .btn-admin-action-form { 
        padding: 10px 25px; font-size: 1rem; background-color: #ffbe33; color: #ffffff; border-radius: 5px; text-decoration: none; transition: background-color 0.3s ease; border: none; cursor: pointer; font-weight: 500; 
    }
    .btn-admin-action-form:hover { background-color: #e69c00; color: #ffffff; }
    .btn-cancel { background-color: #6c757d; margin-left: 10px; }
    .btn-cancel:hover { background-color: #5a6268; }
    .current-image { max-width: 150px; max-height: 150px; margin-top: 10px; border: 1px solid #ddd; padding: 5px; border-radius: 4px;}
    .message { margin-bottom: 15px; padding: 10px; border-radius: 5px; font-size: 0.9em; }
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
     }
  </style>
</head>
<body>
<div class="admin-wrapper">
    <nav class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <h3>Alunan</h3>
            <div class="admin-welcome">Admin: <?php echo htmlspecialchars($_SESSION['username']); ?></div>
        </div>
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
        <div class="content-header">
            <h2><?php echo htmlspecialchars($form_title); ?></h2>
        </div>
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="card-admin">
                        <div class="card-body-admin">
                            <?php if ($error_message): ?>
                                <div class="alert alert-danger message error-message" role="alert"><?php echo htmlspecialchars($error_message); ?></div>
                            <?php elseif ($success_message): // Ditampilkan jika ada pesan sukses dari session ?>
                                <div class="alert alert-success message success-message" role="alert"><?php echo $success_message; ?></div>
                            <?php endif; ?>

                            <form action="<?php echo htmlspecialchars($form_action_url); ?>" method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="KD_BRNG">Kode Barang <span style="color:red;">*</span></label>
                                    <input type="text" name="KD_BRNG" id="KD_BRNG" class="form-control-admin" value="<?php echo htmlspecialchars($item['KD_BRNG']); ?>" <?php echo ($is_editing ? 'readonly' : 'required'); ?>>
                                    <?php if ($is_editing): ?>
                                        <small class="form-text text-muted">Kode barang tidak dapat diubah.</small>
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label for="NM_BRNG">Nama Barang <span style="color:red;">*</span></label>
                                    <input type="text" name="NM_BRNG" id="NM_BRNG" class="form-control-admin" value="<?php echo htmlspecialchars($item['NM_BRNG']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="DESC_BRNG">Deskripsi</label>
                                    <textarea name="DESC_BRNG" id="DESC_BRNG" class="form-control-admin" rows="4"><?php echo htmlspecialchars($item['DESC_BRNG']); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="HARGA_BRNG">Harga <span style="color:red;">*</span> (Contoh: 25000)</label>
                                    <input type="text" name="HARGA_BRNG" id="HARGA_BRNG" class="form-control-admin" value="<?php echo htmlspecialchars($item['HARGA_BRNG']); ?>" placeholder="Hanya angka, misal 20000" required>
                                </div>
                                 <div class="form-group">
                                    <label for="FT_BRNG">Foto Barang <?php echo ($is_editing ? '(Kosongkan jika tidak ingin mengubah)' : ''); ?></label>
                                    <input type="file" name="FT_BRNG" id="FT_BRNG" class="form-control-admin" accept="image/jpeg, image/png, image/gif">
                                    <?php if ($is_editing && !empty($item['FT_BRNG']) && file_exists($item['FT_BRNG'])): ?>
                                        <p class="mt-2">Foto saat ini: <br><img src="<?php echo htmlspecialchars($item['FT_BRNG']); ?>" alt="Foto <?php echo htmlspecialchars($item['NM_BRNG']); ?>" class="current-image"></p>
                                        <input type="hidden" name="current_FT_BRNG" value="<?php echo htmlspecialchars($item['FT_BRNG']); ?>">
                                    <?php elseif ($is_editing && !empty($item['FT_BRNG'])): ?>
                                        <p class="mt-2 text-warning">File foto saat ini tidak ditemukan (<?php echo htmlspecialchars($item['FT_BRNG']); ?>). Upload baru jika ingin mengganti.</p>
                                        <input type="hidden" name="current_FT_BRNG" value="<?php echo htmlspecialchars($item['FT_BRNG']); ?>">
                                    <?php endif; ?>
                                    <small class="form-text text-muted">Maks 2MB (JPG, JPEG, PNG, GIF).</small>
                                </div>
                                <div class="form-group">
                                    <label for="JENIS">Jenis <span style="color:red;">*</span></label>
                                    <select name="JENIS" id="JENIS" class="form-control-admin" required>
                                        <option value="">-- Pilih Jenis --</option>
                                        <option value="Makanan" <?php echo (isset($item['JENIS']) && $item['JENIS'] === 'Makanan' ? 'selected' : ''); ?>>Makanan</option>
                                        <option value="Minuman" <?php echo (isset($item['JENIS']) && $item['JENIS'] === 'Minuman' ? 'selected' : ''); ?>>Minuman</option>
                                        <option value="Snack" <?php echo (isset($item['JENIS']) && $item['JENIS'] === 'Snack' ? 'selected' : ''); ?>>Snack</option>
                                        <option value="Dessert" <?php echo (isset($item['JENIS']) && $item['JENIS'] === 'Dessert' ? 'selected' : ''); ?>>Dessert</option>
                                    </select>
                                </div>
                               <div class="form-group">
                                    <label for="KATEGORI_MENU">Kategori Menu (untuk filter)</label>
                                    <select name="KATEGORI_MENU" id="KATEGORI_MENU" class="form-control-admin">
                                        <option value="">-- Tidak Ada Kategori --</option>
                                        <option value="burger" <?php echo (isset($item['KATEGORI_MENU']) && $item['KATEGORI_MENU'] === 'burger' ? 'selected' : ''); ?>>Burger</option>
                                        <option value="pizza" <?php echo (isset($item['KATEGORI_MENU']) && $item['KATEGORI_MENU'] === 'pizza' ? 'selected' : ''); ?>>Pizza</option>
                                        <option value="pasta" <?php echo (isset($item['KATEGORI_MENU']) && $item['KATEGORI_MENU'] === 'pasta' ? 'selected' : ''); ?>>Pasta</option>
                                        <option value="fries" <?php echo (isset($item['KATEGORI_MENU']) && $item['KATEGORI_MENU'] === 'fries' ? 'selected' : ''); ?>>Fries</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-admin-action-form"><?php echo htmlspecialchars($submit_button_text); ?></button>
                                <a href="admin_menu.php" class="btn btn-admin-action-form btn-cancel">Batal</a>
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