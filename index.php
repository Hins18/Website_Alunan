<?php
// alunantest/index.php
session_start();
require_once 'db_config.php'; 

$page_message_checkout = ''; 
if (isset($_SESSION['checkout_message'])) {
    $msg_type = $_SESSION['checkout_message']['type'] === 'success' ? 'success' : 'danger';
    $page_message_checkout = "<div class='container mt-3'><div class='alert alert-{$msg_type} alert-dismissible fade show' role='alert' style='z-index: 2000; position: relative;'>" .
                             htmlspecialchars($_SESSION['checkout_message']['text']) .
                             "<button type='button' class='close' data-dismiss='alert' aria-label='Close'><span aria-hidden='true'>×</span></button></div></div>";
    unset($_SESSION['checkout_message']);
}

// --- PENGAMBILAN DATA MENU ---
$all_menu_items = [];
$unique_jenis_for_filter = []; 
$sql_menu_all = "SELECT KD_BRNG, NM_BRNG, DESC_BRNG, HARGA_BRNG, FT_BRNG, JENIS, KATEGORI_MENU 
                 FROM makanan_minuman 
                 ORDER BY FIELD(JENIS, 'Makanan', 'Minuman', 'Snack', 'Dessert', ''), NM_BRNG";
$result_menu_all = $conn->query($sql_menu_all);
if ($result_menu_all && $result_menu_all->num_rows > 0) {
    while ($row = $result_menu_all->fetch_assoc()) {
        $all_menu_items[] = $row; 
        if (!empty($row['JENIS']) && !in_array($row['JENIS'], $unique_jenis_for_filter)) {
            $unique_jenis_for_filter[] = $row['JENIS']; 
        }
    }
}
$order_jenis = ['Makanan', 'Minuman', 'Snack', 'Dessert']; 
$filters_to_display = [];
foreach($order_jenis as $oj) { if (in_array($oj, $unique_jenis_for_filter)) { $filters_to_display[] = $oj; } }
foreach($unique_jenis_for_filter as $uj) { if (!in_array($uj, $filters_to_display)) { $filters_to_display[] = $uj; } }
if (!isset($_SESSION['keranjang'])) { $_SESSION['keranjang'] = []; }
function countCartItemsSession() {
    $total_items = 0;
    if (isset($_SESSION['keranjang'])) {
        foreach ($_SESSION['keranjang'] as $item) {
            if (isset($item['kuantitas'])) { $total_items += $item['kuantitas']; }
        }
    }
    return $total_items;
}

// --- PENGAMBILAN DATA REVIEW YANG DISETUJUI ---
$approved_reviews = [];
$sql_get_reviews = "SELECT pr.nama_reviewer, pr.rating, pr.komentar, pr.tanggal_review, 
                           u.username as user_login_name, 
                           mm.NM_BRNG as nama_produk_review, 
                           mm.FT_BRNG as foto_produk_review
                    FROM product_reviews pr
                    LEFT JOIN users u ON pr.user_id = u.id
                    LEFT JOIN makanan_minuman mm ON pr.kd_brng = mm.KD_BRNG
                    WHERE pr.status_review = 'approved'  /* <--- Pastikan ini benar */
                    ORDER BY pr.tanggal_review DESC LIMIT 6"; // Ambil 6 review terbaru yang disetujui
$result_get_reviews = $conn->query($sql_get_reviews);


if ($result_get_reviews && $result_get_reviews->num_rows > 0) {
    while($review_item_row = $result_get_reviews->fetch_assoc()){
        $approved_reviews[] = $review_item_row;
    }
}
// --- AKHIR PENGAMBILAN DATA REVIEW ---
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <title> Alunan </title>
  <link rel="shortcut icon" href="images/favicon.png" type="">
  <link rel="stylesheet" type="text/css" href="css/bootstrap.css" />
  <link rel="stylesheet" type="text/css" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/css/nice-select.min.css" />
  <link href="css/font-awesome.min.css" rel="stylesheet" />
  <link href="css/style.css" rel="stylesheet" /> 
  <link href="css/responsive.css" rel="stylesheet" />
  <script type="text/javascript"
  src="https://app.sandbox.midtrans.com/snap/snap.js"
  data-client-key="SB-Mid-client-NUDI5e5OFrEYBwNk"></script>
  <style>
    /* CSS Modal & User Dropdown */
    .modal { display: none; position: fixed; z-index: 1070; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.6); backdrop-filter: blur(5px); -webkit-backdrop-filter: blur(5px); }
    .modal-content { background-color: #ffffff; margin: 8% auto; padding: 30px; border: none; border-radius: 15px; width: 90%; box-shadow: 0 8px 25px rgba(0,0,0,0.15); animation: slideDown 0.5s ease-out; }
    #loginModal .modal-content, #registerModal .modal-content { max-width: 400px; }
    #productDetailModal .modal-content { max-width: 550px; } 
    #cartDetailModal .modal-content { max-width: 750px; } 

    @keyframes slideDown { from { opacity: 0; transform: translateY(-30px); } to { opacity: 1; transform: translateY(0); } }
    .modal-header-text { text-align: center; margin-bottom: 25px; }
    .modal-header-text h2 { color: #333; font-family: 'Open Sans', sans-serif; font-size: 24px; font-weight: 600; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; color: #555; font-weight: 500; }
    .form-control-modal { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 8px; box-sizing: border-box; font-family: 'Open Sans', sans-serif; font-size: 16px; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
    .form-control-modal:focus { border-color: #ffbe33; box-shadow: 0 0 8px rgba(255, 190, 51, 0.3); outline: none; }
    .btn-login, .btn-add-to-cart-modal, #submitReviewBtnModal { width: 100%; padding: 12px; background-color: #ffbe33; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; transition: background-color 0.3s ease, transform 0.2s ease; }
    .btn-login:hover, .btn-add-to-cart-modal:hover, #submitReviewBtnModal:hover { background-color: #e69c00; transform: translateY(-2px); }
    .btn-login:active, .btn-add-to-cart-modal:active, #submitReviewBtnModal:active { transform: translateY(0); }
    #submitReviewBtnModal { background-color: #28a745; margin-top:10px; width:auto; padding: 8px 15px;} 
    #submitReviewBtnModal:hover { background-color: #218838; }

    .close-btn { color: #aaa; float: right; font-size: 30px; font-weight: bold; line-height: 1; cursor: pointer; transition: color 0.2s ease; }
    .close-btn:hover, .close-btn:focus { color: #333; text-decoration: none; }
    .modal-footer-text { text-align: center; margin-top: 20px; font-size: 14px; color: #555; }
    .modal-footer-text a { color: #ffbe33; text-decoration: none; font-weight: 500; }
    .modal-footer-text a:hover { text-decoration: underline; }
    .message { margin-bottom: 15px; padding: 10px 15px; border-radius: 5px; font-size: 0.9em; text-align: left; font-family: 'Open Sans', sans-serif; display: none; }
    .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .success-message a { color: #007bff; font-weight: bold; }
    @media (max-width: 600px) { #loginModal .modal-content, #registerModal .modal-content, #productDetailModal .modal-content { margin: 5% auto; width: 95%; padding: 20px; } .modal-header-text h2 { font-size: 20px; } .form-control-modal, .btn-login { font-size: 15px; } }
    
    .user_option { display: flex; align-items: center; }
    .user_option .user_link, .user_option .user_link_container, .user_option .cart_link_container { margin: 0 10px; display: flex; align-items: center; justify-content: center; padding: 0; width: 37px; height: 42px; box-sizing: border-box; position: relative; }
    .user_option .user_link i, .user_option .user_icon_link i, .user_option .cart_link i { color: #ffffff; font-size: 20px; transition: color 0.3s ease; }
    .user_option .user_link:hover i, .user_option .user_icon_link:hover i, .user_option .cart_link:hover i { color: #ffbe33; }
    .user_option .user_link_container .user_icon_link { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; text-decoration: none; }
    .user_option .user_dropdown_content { display: none; position: absolute; top: calc(100% + 5px); right: 0; background-color: #222831; color: #ffffff; border-radius: 8px; padding: 15px 20px; width: max-content; min-width: 220px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.25); z-index: 1100; border: 1px solid #4b5158; text-align: left; }
    .user_option .user_link_container:hover .user_dropdown_content { display: block; }
    .user_option .user_dropdown_content .username_display { display: block; margin-bottom: 12px; font-size: 15px; font-weight: 500; white-space: nowrap; border-bottom: 1px solid #444; padding-bottom: 10px; }
    .user_option .user_dropdown_content .btn_dashboard_dropdown, .user_option .user_dropdown_content .logout_link { display: block; padding: 8px 12px; border-radius: 5px; text-align: center; text-decoration: none; font-size: 14px; font-weight: 600; transition: background-color 0.3s ease, color 0.3s ease; width: 100%; box-sizing: border-box; }
    .user_option .user_dropdown_content .btn_dashboard_dropdown { background-color: #17a2b8; color: #ffffff; margin-bottom: 8px; }
    .user_option .user_dropdown_content .btn_dashboard_dropdown:hover { background-color: #117a8b; }
    .user_option .user_dropdown_content .logout_link { background-color: #ffbe33; color: #222831; }
    .user_option .user_dropdown_content .logout_link:hover { background-color: #e69c00; color: #ffffff; }
    .navbar-nav .nav-item .nav-link-dashboard { padding: 5px 20px; color: #ffffff; text-align: center; text-transform: uppercase; border-radius: 5px; transition: all 0.3s; background-color: #ffbe33; border: 1px solid #ffbe33; margin-left: 10px; }
    .navbar-nav .nav-item .nav-link-dashboard:hover { color: #222831; background-color: #e69c00; border-color: #e69c00; }
    
    .food_section { padding-top: 70px; }
    .food_section .row.grid { display: flex; flex-wrap: wrap; }
    .food_section .col-sm-6.col-lg-4 { display: flex; flex-direction: column; margin-bottom: 30px; }
    .food_section .box { position: relative; background-color: #ffffff; color: #ffffff; border-radius: 15px; overflow: hidden; background: linear-gradient(to bottom, #f1f2f3 25px, #222831 25px); display: flex; flex-direction: column; height: 100%; }
    .food_section .box .img-box { background: #f1f2f3; display: flex; justify-content: center; align-items: center; height: 215px; border-radius: 0 0 0 45px; margin: -1px; padding: 25px; }
    .food_section .box .img-box img { max-width: 100%; max-height: 160px; transition: all .2s; object-fit: contain; }
    .food_section .box .detail-box { padding: 25px; flex-grow: 1; display: flex; flex-direction: column; }
    .food_section .box .detail-box h5 { font-weight: 600; }
    .food_section .box .detail-box p { font-size: 15px; flex-grow: 1; margin-bottom: 15px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.4em; max-height: calc(1.4em * 3); min-height: calc(1.4em * 3); }
    .food_section .box .detail-box h6 { margin-top: 10px; }
    .food_section .box .options { display: flex; justify-content: space-between; margin-top: auto; }
    .food_section .box:hover .img-box img { transform: scale(1.1); }

    .user_option .cart_link_container .cart_badge { position: absolute; top: 0px; right: -2px; background-color: red; color: white; border-radius: 50%; padding: 1px 5px; font-size: 0.7rem; font-weight: bold; line-height: 1; min-width: 16px; text-align: center; display: none; }
    .cart_hover_popup { display: none; position: absolute; top: calc(100% + 5px); right: 0; width: 320px; background-color: #222831; color: #ffffff; border: 1px solid #4b5158; border-radius: 8px; padding: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.25); z-index: 1101; font-size: 0.9em; }
    .cart_hover_popup #cartHoverContent .cart-hover-item { display: flex; align-items: center; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px solid #444; }
    .cart_hover_popup #cartHoverContent .cart-hover-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .cart_hover_popup #cartHoverContent .cart-hover-item img { width: 40px; height: 40px; object-fit: cover; border-radius: 4px; margin-right: 10px; }
    .cart_hover_popup #cartHoverContent .cart-hover-item-details { flex-grow: 1; }
    .cart_hover_popup #cartHoverContent .cart-hover-item-name { display: block; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px; }
    .cart_hover_popup #cartHoverContent .cart-hover-item-price-qty { font-size: 0.85em; color: #ccc; }
    .cart_hover_popup .btn-view-cart { display: block; width: auto; min-width: 120px; padding: 6px 15px; margin-top: 15px; margin-left: auto; margin-right: auto; background-color: #ffbe33; color: #222831; text-align: center; border-radius: 5px; text-decoration: none; font-weight: 600; font-size: 0.85em; transition: background-color 0.3s ease, color 0.3s ease; }
    .cart_hover_popup .btn-view-cart:hover { background-color: #e69c00; color: #ffffff; }
    #cartDetailModal .modal-content { max-width: 750px; }
    #cartDetailContent table { width: 100%; margin-top: 15px; border-collapse: collapse; }
    #cartDetailContent th, #cartDetailContent td { text-align: left; padding: 10px 12px; border-bottom: 1px solid #eee; vertical-align: middle; font-size: 0.95rem;}
    #cartDetailContent th { background-color: #f8f9fa; font-weight: 600; }
    #cartDetailContent td img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin-right: 10px; }
    #cartDetailContent .quantity-controls { display: flex; align-items: center; }
    #cartDetailContent .quantity-controls button { padding: 3px 8px; font-size: 1em; margin: 0 5px; cursor: pointer; border: 1px solid #ccc; background-color: #f0f0f0; line-height: 1; height: 30px; }
    #cartDetailContent .quantity-controls button:hover { background-color: #e0e0e0;}
    #cartDetailContent .item-quantity { min-width: 20px; text-align: center; padding: 0 5px;}
    #cartDetailContent .item-remove-btn { color: red; cursor: pointer; font-size: 1.2em; background: none; border: none; padding: 0 5px; }
    #cartDetailSummary { margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee; }
    #cartDetailSummary .btn-primary { background-color: #ffbe33; border-color: #ffbe33; color: #212529; padding: 10px 20px; font-size: 1rem;}
    #cartDetailSummary .btn-primary:hover { background-color: #e69c00; border-color: #e69c00; }
    #cartDetailSummary .btn-primary:disabled { background-color: #cccccc; border-color: #cccccc; }

    #productDetailModal .product-image-lg { width: 100%; max-height: 250px; object-fit: contain; margin-bottom: 20px; border-radius: 8px;}
    #productDetailModal .product-description { font-size: 0.95rem; color: #555; margin-bottom: 15px; max-height: 100px; overflow-y: auto; text-align: left;}
    #productDetailModal .product-price-lg { font-size: 1.5rem; font-weight: bold; color: #222831; margin-bottom: 20px;}
    #productDetailModal .quantity-control-modal { display: flex; align-items: center; justify-content: center; margin-bottom: 25px;}
    #productDetailModal .quantity-control-modal button.btn-qty { background-color: #f0f0f0; border: 1px solid #ccc; color: #333; font-size: 1.2rem; padding: 5px 12px; cursor: pointer; line-height: 1; border-radius: 4px; }
    #productDetailModal .quantity-control-modal button.btn-qty:hover { background-color: #e0e0e0; }
    #productDetailModal .quantity-control-modal input[type="number"]#productDetailKuantitas { width: 60px; text-align: center; font-size: 1.1rem; margin: 0 10px; border: 1px solid #ccc; border-radius: 4px; padding: 6px; -moz-appearance: textfield; }
    #productDetailModal input[type="number"]::-webkit-outer-spin-button,
    #productDetailModal input[type="number"]::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    #productDetailModal .modal-message { margin-top: 15px; text-align: center; }
    
   /* ... (CSS Anda yang sudah ada) ... */

/* === CSS UNTUK BINTANG REVIEW (PERBAIKAN FINAL) === */
.product-review-form-container .form-group label[for="review_rating_modal"] {
    display: block; 
    margin-bottom: 5px;
}

.rating-stars-input { 
    display: flex; 
    flex-direction: row-reverse; 
    justify-content: center; /* Atau center jika Anda lebih suka */
    line-height: 1;
    margin-bottom: 10px; 
    padding: 0;
    width: auto; 
}

/* Targetkan label secara lebih spesifik dan naikkan font-size di sini */
.rating-stars-input label {
    color: #ddd; 
    cursor: pointer;
    padding: 0 0.08em; /* Sedikit jarak antar bintang, bisa disesuaikan */
    transition: color 0.2s ease-in-out;
    display: inline; /* Pastikan inline */
    font-size: 4em !important; /* <<-- PERBESAR NILAI INI & TAMBAHKAN !important */
                                 /* Coba nilai seperti 2em, 2.2em, 2.5em, 3em */
}

.rating-stars-input input[type="radio"] {
    display: none; 
}

.rating-stars-input input[type="radio"]:checked ~ label, 
.rating-stars-input label:hover, 
.rating-stars-input label:hover ~ label {
    color: #ffbe33; 
}
/* === AKHIR CSS BINTANG REVIEW === */

/* ... (CSS Anda yang lain tetap sama) ... */* Label untuk bintang ke-5 harus pertama di HTML, lalu bintang ke-4, dst. */


/* Untuk tampilan bintang di client review section (jika masih menggunakan Font Awesome) */
.rating-stars-display .fa { 
    font-size: 0.9em; /* Ukuran bintang yang ditampilkan di review, sesuaikan */
    margin-right: 2px; 
    color: #ffbe33; /* Warna bintang terisi */
}
.rating-stars-display .fa.fa-star-o { /* Untuk bintang kosong jika ada */
    color: #ccc; 
}
.client_section .box .detail-box .rating-stars-display { 
    margin-bottom: 8px; /* Jarak bawah yang lebih pas */
}
/* === AKHIR CSS BINTANG REVIEW === */

/* CSS untuk Modal Detail Produk & Tambah Kuantitas (Pastikan sudah ada) */
#productDetailModal .product-image-lg { /* ... */ }
#productDetailModal .product-description { /* ... */ }
#productDetailModal .product-price-lg { /* ... */ }
#productDetailModal .quantity-control-modal { /* ... */ }
#productDetailModal .quantity-control-modal button.btn-qty { /* ... */ }
#productDetailModal .quantity-control-modal input[type="number"]#productDetailKuantitas { /* ... */ }
#productDetailModal input[type="number"]::-webkit-outer-spin-button,
#productDetailModal input[type="number"]::-webkit-inner-spin-button { /* ... */ }
#productDetailModal .modal-message { /* ... */ }

/* CSS untuk Form Review di dalam Modal Produk */
.product-review-form-container {
    margin-top: 25px; /* Jarak dari elemen di atasnya */
    padding-top: 15px;
    border-top: 1px solid #eee;
}
.product-review-form-container h4 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 15px;
    text-align: center;
    color: #333;
}
/* Pastikan form-group di dalam modal review juga memiliki margin yang cukup */
.product-review-form-container .form-group {
    margin-bottom: 15px;
}
.product-review-form-container .form-group label {
    font-size: 0.9rem; /* Ukuran label sedikit lebih kecil */
}
.product-review-form-container textarea#review_komentar_modal {
    min-height: 80px; /* Tinggi minimal textarea komentar */
}
/* Tombol kirim review */
#submitReviewBtnModal {
    background-color: #28a745; /* Warna hijau */
    border-color: #28a745;
    width: auto; /* Lebar menyesuaikan konten */
    padding: 8px 20px; /* Padding tombol */
    font-size: 0.95em; /* Ukuran font tombol */
    display: block; /* Agar bisa di-margin auto untuk ke tengah */
    margin: 10px auto 0; /* Tengah dan beri jarak atas */
}
#submitReviewBtnModal:hover {
    background-color: #218838;
    border-color: #1e7e34;
}

/* Pesan di bawah form review */
#reviewFormMessageModal {
    margin-top:15px; /* Jarak atas */
    font-size: 0.85em; /* Ukuran font pesan */
}
  </style>
</head>
<body>
  <?php if (!empty($page_message_checkout)) echo $page_message_checkout; // Menampilkan pesan checkout global ?>
  <div class="hero_area">
    <div class="bg-box"><img src="images/hero-bg.jpg" alt=""></div>
    <header class="header_section">
      <div class="container">
        <nav class="navbar navbar-expand-lg custom_nav-container ">
          <a class="navbar-brand" href="index.php"><span>Alunan</span></a>
          <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><span class=""> </span></button>
          <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav mx-auto ">
              <li class="nav-item active"><a class="nav-link" href="#home">Home <span class="sr-only">(current)</span></a></li>
              <li class="nav-item"><a class="nav-link" href="#menu">Menu</a></li>
              <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
              <li class="nav-item"><a class="nav-link" href="#book" id="bookLink">Book Table</a></li>
            </ul>
            <div class="user_option">
              <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                <div class="user_link_container">
                    <a href="#" class="user_icon_link"><i class="fa fa-user" aria-hidden="true"></i></a>
                    <div class="user_dropdown_content">
                        <span class="username_display">Halo, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                        <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                            <a href="admin.php" class="btn_dashboard_dropdown">Dashboard</a>
                        <?php endif; ?>
                        <a href="logout.php" class="logout_link">Logout</a>
                    </div>
                </div>
              <?php else: ?>
                <a href="#" class="user_link" id="loginBtn"><i class="fa fa-user" aria-hidden="true"></i></a>
              <?php endif; ?>
              
              <div class="cart_link_container">
                <a class="cart_link" href="#" id="cartIcon">
                  <i class="fa fa-shopping-cart" aria-hidden="true" style="color:#ffff;"></i>
                  <span class="cart_badge" id="cartBadge"><?php echo countCartItemsSession(); ?></span>
                </a>
                <div class="cart_hover_popup" id="cartHoverPopup">
                  <div id="cartHoverContent">Memuat keranjang...</div>
                  <div id="cartHoverTotal" style="font-weight:bold; margin-top:5px;"></div>
                  <a href="#" id="viewCartModalLink" class="btn-view-cart">Checkout</a>
                </div>
              </div>
              <a href="#menu" class="order_online">Pesan Sekarang</a>
            </div>
          </div>
        </nav>
      </div>
    </header>
    <section class="slider_section " id="home">
      <div id="customCarousel1" class="carousel slide" data-ride="carousel"><div class="carousel-inner"><div class="carousel-item active"><div class="container "><div class="row"><div class="col-md-7 col-lg-6 "><div class="detail-box"><h1>Fast Food Restaurant 1</h1><p>Doloremque, itaque aperiam facilis rerum, commodi, temporibus sapiente ad mollitia laborum quam quisquam esse error unde. Tempora ex doloremque, labore, sunt repellat dolore, iste magni quos nihil ducimus libero ipsam.</p><div class="btn-box"><a href="" class="btn1">Order Now</a></div></div></div></div></div></div><div class="carousel-item "><div class="container "><div class="row"><div class="col-md-7 col-lg-6 "><div class="detail-box"><h1>Fast Food Restaurant 2</h1><p>Doloremque, itaque aperiam facilis rerum, commodi, temporibus sapiente ad mollitia laborum quam quisquam esse error unde. Tempora ex doloremque, labore, sunt repellat dolore, iste magni quos nihil ducimus libero ipsam.</p><div class="btn-box"><a href="" class="btn1">Order Now</a></div></div></div></div></div></div><div class="carousel-item"><div class="container "><div class="row"><div class="col-md-7 col-lg-6 "><div class="detail-box"><h1>Fast Food Restaurant 3</h1><p>Doloremque, itaque aperiam facilis rerum, commodi, temporibus sapiente ad mollitia laborum quam quisquam esse error unde. Tempora ex doloremque, labore, sunt repellat dolore, iste magni quos nihil ducimus libero ipsam.</p><div class="btn-box"><a href="" class="btn1">Order Now</a></div></div></div></div></div></div></div>
        <div class="container"><ol class="carousel-indicators"><li data-target="#customCarousel1" data-slide-to="0" class="active"></li><li data-target="#customCarousel1" data-slide-to="1"></li><li data-target="#customCarousel1" data-slide-to="2"></li></ol></div>
      </div>
    </section>
    </div>

  <section class="food_section layout_padding-bottom" id="menu">
    <div class="container">
      <div class="heading_container heading_center"><h2>Our Menu</h2></div>
      <ul class="filters_menu">
        <li class="active" data-filter="*">All</li>
        <?php
        if (!empty($filters_to_display)) {
            foreach ($filters_to_display as $jenis_filter_item) {
                $data_filter_class = '.' . htmlspecialchars(trim(strtolower(str_replace(' ', '-', $jenis_filter_item))));
                echo '<li data-filter="' . $data_filter_class . '">' . htmlspecialchars(ucfirst($jenis_filter_item)) . '</li>';
            }
        }
        ?>
      </ul>
      <div class="filters-content">
        <div class="row grid"> 
          <?php if (!empty($all_menu_items)): ?>
            <?php foreach ($all_menu_items as $item): ?>
              <?php
              $filter_classes_item = 'all'; 
              if (!empty($item['JENIS'])) {
                  $filter_classes_item .= ' ' . htmlspecialchars(trim(strtolower(str_replace(' ', '-', $item['JENIS']))));
              }
              ?>
              <div class="col-sm-6 col-lg-4 <?php echo $filter_classes_item; ?>" style="display: flex;">
                <div class="box"> 
                  <div>
                    <div class="img-box">
                      <img src="<?php echo (!empty($item['FT_BRNG']) && file_exists($item['FT_BRNG'])) ? htmlspecialchars($item['FT_BRNG']) : 'images/default_food.png'; ?>" alt="<?php echo htmlspecialchars($item['NM_BRNG']); ?>">
                    </div>
                    <div class="detail-box">
                      <h5><?php echo htmlspecialchars($item['NM_BRNG']); ?></h5>
                      <p><?php echo nl2br(htmlspecialchars($item['DESC_BRNG'] ?? 'Deskripsi belum tersedia.')); ?></p>
                      <div class="options">
                        <h6>Rp <?php echo number_format($item['HARGA_BRNG'], 0, ',', '.'); ?></h6>
                        <a href="#" class="open-product-detail-modal-btn" 
                           data-kd="<?php echo htmlspecialchars($item['KD_BRNG']); ?>" 
                           data-nama="<?php echo htmlspecialchars($item['NM_BRNG']); ?>" 
                           data-harga="<?php echo htmlspecialchars($item['HARGA_BRNG']); ?>" 
                           data-foto="<?php echo htmlspecialchars((!empty($item['FT_BRNG']) && file_exists($item['FT_BRNG'])) ? $item['FT_BRNG'] : 'images/default_food.png'); ?>"
                           data-deskripsi="<?php echo htmlspecialchars($item['DESC_BRNG'] ?? ''); ?>">
                          <svg version="1.1" viewBox="0 0 456.029 456.029" style="enable-background:new 0 0 456.029 456.029;"><g><g><path d="M345.6,338.862c-29.184,0-53.248,23.552-53.248,53.248c0,29.184,23.552,53.248,53.248,53.248 c29.184,0,53.248-23.552,53.248-53.248C398.336,362.926,374.784,338.862,345.6,338.862z" /></g></g><g><g><path d="M439.296,84.91c-1.024,0-2.56-0.512-4.096-0.512H112.64l-5.12-34.304C104.448,27.566,84.992,10.67,61.952,10.67H20.48 C9.216,10.67,0,19.886,0,31.15c0,11.264,9.216,20.48,20.48,20.48h41.472c2.56,0,4.608,2.048,5.12,4.608l31.744,216.064 c4.096,27.136,27.648,47.616,55.296,47.616h212.992c26.624,0,49.664-18.944,55.296-45.056l33.28-166.4 C457.728,97.71,450.56,86.958,439.296,84.91z" /></g></g><g><g><path d="M215.04,389.55c-1.024-28.16-24.576-50.688-52.736-50.688c-29.696,1.536-52.224,26.112-51.2,55.296 c1.024,28.16,24.064,50.688,52.224,50.688h1.024C193.536,443.31,216.576,418.734,215.04,389.55z" /></g></g></svg>
                        </a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12 text-center mt-5">
              <p style="font-size: 1.2rem; color: #777;">Menu belum tersedia saat ini.</p>
            </div>
          <?php endif; ?>
        </div> 
      </div> 
    </div>
  </section>

  <section class="about_section layout_padding" id="about"><div class="container"><div class="row"><div class="col-md-6 "><div class="img-box"><img src="images/about-img.png" alt=""></div></div><div class="col-md-6"><div class="detail-box"><div class="heading_container"><h2>We Are Feane</h2></div><p>There are many variations of passages of Lorem Ipsum available, but the majority have suffered alteration in some form, by injected humour, or randomised words which don't look even slightly believable. If you are going to use a passage of Lorem Ipsum, you need to be sure there isn't anything embarrassing hidden in the middle of text. All</p><a href="">Read More</a></div></div></div></div></section>
  <section class="book_section layout_padding" id="book"><div class="container"><div class="row"><div class="col-md-6"><h2>Book A Table</h2></div><div class="col-md-6"><h2 class="alamat">Alamat</h2></div></div><div class="row"><div class="col-md-6"><div class="form_container"><form action=""><div><input type="text" class="form-control" placeholder="Your Name" /></div><div><input type="text" class="form-control" placeholder="Phone Number" /></div><div><input type="email" class="form-control" placeholder="Your Email" /></div><div><select class="form-control nice-select wide"><option value="" disabled selected>How many persons?</option><option value="">2</option><option value="">3</option><option value="">4</option><option value="">5</option></select></div><div><input type="date" class="form-control"></div><div class="btn_box"><button>Book Now</button></div></form></div></div><div class="col-md-6"><div class="map_container "><div id="googleMap">Jl. Sersan Idris No.1, RT.003/RW.004, Marga Jaya, Kec. Bekasi Sel., Kota Bks, Jawa Barat 17141</div></div></div></div></div></section>
  
  <section class="client_section layout_padding-bottom">
    <div class="container">
      <div class="heading_container heading_center psudo_white_primary mb_45">
        <h2>Apa Kata Mereka?</h2>
      </div>
      <div class="carousel-wrap row ">
        <div class="owl-carousel client_owl-carousel">
          <?php if (!empty($approved_reviews)): ?>
            <?php foreach($approved_reviews as $review_item): ?>
              <?php
                  $reviewer_display_name = !empty($review_item['user_login_name']) ? htmlspecialchars($review_item['user_login_name']) : (!empty($review_item['nama_reviewer']) ? htmlspecialchars($review_item['nama_reviewer']) : 'Pelanggan');
                  $rating_stars_display = '';
                  for ($i = 1; $i <= 5; $i++) {
                      $rating_stars_display .= ($i <= $review_item['rating']) ? '<i class="fa fa-star" style="color: #ffbe33;"></i>' : '<i class="fa fa-star-o" style="color: #ffbe33;"></i>';
                  }
              ?>
              <div class="item">
                <div class="box">
                  <div class="img-box">
                    <img src="<?php echo (!empty($review_item['foto_produk_review']) && file_exists($review_item['foto_produk_review'])) ? htmlspecialchars($review_item['foto_produk_review']) : 'images/client_default.png'; ?>" alt="Review Produk <?php echo htmlspecialchars($review_item['nama_produk_review']); ?>" class="box-img" style="object-fit: cover; width:80px; height:80px; border-radius:50%;">
                  </div>
                  <div class="detail-box">
                    <div class="rating-stars-display mb-2">
                      <?php echo $rating_stars_display; ?>
                      <small class="text-muted ml-2" style="font-size:0.8em;"><?php echo date('d M Y', strtotime($review_item['tanggal_review'])); ?></small>
                    </div>
                    <p style="font-style: italic;">"<?php echo nl2br(htmlspecialchars($review_item['komentar'])); ?>"</p>
                    <h6><?php echo $reviewer_display_name; ?></h6>
                    <p style="font-size:0.9em; color: #777;">Tentang: <?php echo htmlspecialchars($review_item['nama_produk_review']); ?></p>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="item">
              <div class="box">
                <div class="detail-box" style="text-align: center; width:100%;">
                  <p>Belum ada review untuk ditampilkan.</p>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <footer class="footer_section"><div class="container"><div class="row"><div class="col-md-4 footer-col"><div class="footer_contact"><h4>Kontak Kami</h4><div class="contact_link_box"><a href="https://www.google.com/maps/place/Kedai+Alunan/@-6.2394906,106.9992501,21z/data=!4m6!3m5!1s0x2e698d05a7c75cf7:0x164e561e0cfef4e!8m2!3d-6.2395455!4d106.999487!16s%2Fg%2F11lg2s_06y?entry=ttu&g_ep=EgoyMDI1MDUxMi4wIKXMDSoASAFQAw%3D%3D"><i class="fa fa-map-marker" aria-hidden="true"></i><span>Jl.Sersan Idris No.1 Bekasi Selatan</span></a><a href="https://wa.me/6281280535246"><i class="fa fa-whatsapp" aria-hidden="true"></i><span>081280535246</span></a></div></div></div><div class="col-md-4 footer-col"><div class="footer_detail"><a href="" class="footer-logo">Alunan</a><p>Kunjungi Social Media Kami</p><div class="footer_social"><a href="https://www.tiktok.com/@hei.alunan" target="_blank" rel="noopener noreferrer"><img src="images/tiktok.svg" alt="TikTok" width="20" height="20"/></a><a href="https://www.instagram.com/kedaialunan/"><i class="fa fa-instagram" aria-hidden="true"></i></a></div></div></div><div class="col-md-4 footer-col"><h4>Jam Buka</h4><p>Setiap Hari</p><p>11.00 - 23.00</p></div></div><div class="footer-info"><p>© <span id="displayYear"></span></p></div></div></footer>

  <script src="js/jquery-3.4.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js"></script>
  <script src="js/bootstrap.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
  <script src="https://unpkg.com/isotope-layout@3.0.6/dist/isotope.pkgd.min.js"></script>
  <script src="https://unpkg.com/imagesloaded@5/imagesloaded.pkgd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-nice-select/1.1.0/js/jquery.nice-select.min.js"></script>
  
  <div id="loginModal" class="modal"><div class="modal-content"><span class="close-btn login-close-btn">×</span><div class="modal-header-text"><h2>Login Akun</h2></div><div id="loginMessage" class="message"></div><form id="loginForm" method="POST" action="login.php"><div class="form-group"><label for="username">Username:</label><input type="text" id="username" name="username" class="form-control-modal" required></div><div class="form-group"><label for="password">Password:</label><input type="password" id="password" name="password" class="form-control-modal" required></div><div class="form-group"><input type="submit" value="Login" class="btn-login"></div></form><div class="modal-footer-text"><p>Belum punya akun? <a href="#" id="switchToRegisterLink">Daftar di sini</a></p></div></div></div>
  <div id="registerModal" class="modal"><div class="modal-content"><span class="close-btn register-close-btn">×</span><div class="modal-header-text"><h2>Daftar Akun Baru</h2></div><div id="registerMessage" class="message"></div><form id="registerForm"><div class="form-group"><label for="reg_username">Username:</label><input type="text" id="reg_username" name="username" class="form-control-modal" placeholder="Minimal 4 karakter" required></div><div class="form-group"><label for="reg_password">Password:</label><input type="password" id="reg_password" name="password" class="form-control-modal" placeholder="Minimal 6 karakter" required></div><div class="form-group"><label for="reg_confirm_password">Konfirmasi Password:</label><input type="password" id="reg_confirm_password" name="confirm_password" class="form-control-modal" required></div><div class="form-group"><input type="submit" value="Daftar" class="btn-login"></div></form><div class="modal-footer-text"><p>Sudah punya akun? <a href="#" id="switchToLoginLink">Login di sini</a></p></div></div></div>
  <div id="cartDetailModal" class="modal"><div class="modal-content"><span class="close-btn cart-detail-close-btn">×</span><div class="modal-header-text"><h2>Keranjang Belanja Anda</h2></div><div id="cartDetailContent"><p class="text-center">Keranjang Anda kosong.</p></div><div id="checkoutFormContainer" style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; display: none;"><h4 style="text-align:center; margin-bottom:20px;">Detail Pengiriman & Pembayaran</h4><form id="formCheckout"><div class="form-row"><div class="form-group col-md-6"><label for="checkout_nama_pemesan">Nama Pemesan <span style="color:red;">*</span></label><input type="text" class="form-control-modal" id="checkout_nama_pemesan" name="nama_pemesan" required></div><div class="form-group col-md-6"><label for="checkout_nomor_telepon">Nomor Telepon <span style="color:red;">*</span></label><input type="tel" class="form-control-modal" id="checkout_nomor_telepon" name="nomor_telepon" required></div></div><div class="form-group"><label for="checkout_alamat_pengiriman">Alamat Pengiriman <span style="color:red;">*</span></label><textarea class="form-control-modal" id="checkout_alamat_pengiriman" name="alamat_pengiriman" rows="3" required></textarea></div><div class="form-group"><label for="checkout_catatan_pesanan">Catatan Pesanan (Opsional)</label><textarea class="form-control-modal" id="checkout_catatan_pesanan" name="catatan_pesanan" rows="2"></textarea></div><div id="checkoutMessage" class="message" style="margin-top:15px;"></div></form></div><div id="cartDetailSummary" style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;"><h5 class="text-right">Total Harga: <span id="cartDetailTotalAmount">Rp 0</span></h5><div class="text-right mt-3"><button class="btn btn-primary" id="processCheckoutBtn" disabled>Proses Pesanan</button></div></div></div></div>
  <div id="productDetailModal" class="modal"><div class="modal-content"><span class="close-btn product-detail-close-btn">×</span><div class="modal-header-text"><h2 id="productDetailNama">Nama Produk</h2></div><div style="text-align:center;"><img src="" id="productDetailFoto" alt="Foto Produk" class="product-image-lg"></div><p id="productDetailDeskripsi" class="product-description">Deskripsi produk.</p><p class="product-price-lg">Harga: Rp <span id="productDetailHarga">0</span></p><div class="quantity-control-modal"><button type="button" id="productDetailDecreaseQty" class="btn-qty">-</button><input type="number" id="productDetailKuantitas" value="1" min="1" class="form-control-modal" style="width: 70px; text-align: center;"><button type="button" id="productDetailIncreaseQty" class="btn-qty">+</button></div><input type="hidden" id="productDetailKdBrng"><input type="hidden" id="productDetailHargaSatuan"><input type="hidden" id="productDetailFotoPath"><button type="button" id="addProductToCartFinalBtn" class="btn-add-to-cart-modal">Tambahkan ke Keranjang</button><div id="productDetailModalMessage" class="message modal-message" style="margin-top:10px;"></div>
    <div class="product-review-form-container" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
        <h4>Beri Review untuk Produk Ini</h4>
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
            <form id="formProductReview">
                <input type="hidden" name="kd_brng_review" id="kd_brng_review" value="">
                <input type="hidden" name="nm_brng_review" id="nm_brng_review" value="">
                <div class="form-group">
                    <label for="review_rating_modal">Rating Anda:</label>
                    <div class="rating-stars-input">
                        <input type="radio" id="star5_modal" name="review_rating" value="5" required /><label for="star5_modal" title="Bintang 5">☆</label>
                        <input type="radio" id="star4_modal" name="review_rating" value="4" /><label for="star4_modal" title="Bintang 4">☆</label>
                        <input type="radio" id="star3_modal" name="review_rating" value="3" /><label for="star3_modal" title="Bintang 3">☆</label>
                        <input type="radio" id="star2_modal" name="review_rating" value="2" /><label for="star2_modal" title="Bintang 2">☆</label>
                        <input type="radio" id="star1_modal" name="review_rating" value="1" /><label for="star1_modal" title="Bintang 1">☆</label>
                    </div>
                </div>
                <div class="form-group">
                    <label for="review_komentar_modal">Komentar Anda:</label>
                    <textarea name="review_komentar" id="review_komentar_modal" class="form-control-modal" rows="3" placeholder="Tulis review Anda di sini..."></textarea>
                </div>
                <button type="submit" class="btn btn-sm btn-primary" id="submitReviewBtnModal" style="background-color: #28a745; border-color: #28a745; width:auto; padding: 8px 15px;">Kirim Review</button>
                <div id="reviewFormMessageModal" class="message modal-message" style="margin-top:10px;"></div>
            </form>
        <?php else: ?>
            <p><a href="#" class="open-login-from-review">Login</a> untuk memberi review.</p>
        <?php endif; ?>
    </div>
    </div></div>

<script>
$(document).ready(function() {
    // --- FUNGSI GLOBAL ---
    function getYear() {
        var currentDate = new Date();
        var currentYear = currentDate.getFullYear();
        var displayYearEl = document.querySelector("#displayYear");
        if (displayYearEl) displayYearEl.innerHTML = currentYear;
    }
    getYear();

    function number_format(number, decimals = 0, dec_point = ',', thousands_sep = '.') {
        number = (number + '').replace(/[^0-9+\-Ee.]/g, ''); var n = !isFinite(+number) ? 0 : +number, prec = !isFinite(+decimals) ? 0 : Math.abs(decimals), sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep, dec = (typeof dec_point === 'undefined') ? '.' : dec_point, s = '', toFixedFix = function (n, prec) { var k = Math.pow(10, prec); return '' + Math.round(n * k) / k; };
        s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.'); if (s[0].length > 3) { s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep); } if ((s[1] || '').length < prec) { s[1] = s[1] || ''; s[1] += new Array(prec - s[1].length + 1).join('0');} return s.join(dec);
    }

    function nl2br (str, is_xhtml) {
        if (typeof str === 'undefined' || str === null) { return ''; }
        var breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
        return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
    }
    
    function htmlspecialchars(str) { 
        if (typeof(str) == "string") {
            str = str.replace(/&/g, "&amp;"); 
            str = str.replace(/"/g, "&quot;");
            str = str.replace(/'/g, "&#039;");
            str = str.replace(/</g, "&lt;");
            str = str.replace(/>/g, "&gt;");
        }
        return str;
    }
    
    // --- INISIALISASI PLUGIN ---
    var $grid = $('.grid'); 
    if ($grid.length) {
        $grid.imagesLoaded(function() { 
            $grid.isotope({
                itemSelector: ".col-sm-6.col-lg-4", 
                percentPosition: false,
                masonry: { columnWidth: ".col-sm-6.col-lg-4" }
            });
        });
    }

    $('.filters_menu li').click(function () {
        $('.filters_menu li').removeClass('active');
        $(this).addClass('active');
        var filterValue = $(this).attr('data-filter');
        if ($grid.length) { $grid.isotope({ filter: filterValue }); }
    });
    
    if ($('.book_section select.form-control').length && typeof $.fn.niceSelect === 'function') {
        $('.book_section select.form-control').niceSelect();
    }

    if ($(".client_owl-carousel").length && typeof $.fn.owlCarousel === 'function') {
        $(".client_owl-carousel").owlCarousel({
            loop: true, margin: 0, dots: false, nav: true, autoplay: true, autoplayHoverPause: true,
            navText: ['<i class="fa fa-angle-left" aria-hidden="true"></i>', '<i class="fa fa-angle-right" aria-hidden="true"></i>'],
            responsive: { 0: { items: 1 }, 768: { items: 2 }, 1000: { items: 2 } }
        });
    }

    // --- FUNGSI MODAL GLOBAL ---
    function openModal(modalId) {
        $('#' + modalId + ' .message').hide().removeClass('success-message error-message').text('');
        if (modalId === 'registerModal' && $('#registerForm').length) { $('#registerForm')[0].reset(); }
        if (modalId === 'productDetailModal') { 
            $('#productDetailKuantitas').val(1); 
            if ($('#formProductReview').length) { // Reset form review jika ada
                $('#formProductReview')[0].reset();
                $('#reviewFormMessageModal').hide().removeClass('success-message error-message').text('');
            }
        }
        $('#' + modalId).css('display', 'block').scrollTop(0);
        $('body').css('overflow', 'hidden');
    }
    function closeModal(modalId) {
        $('#' + modalId).css('display', 'none');
        $('body').css('overflow', 'auto');
        $('#' + modalId + ' .message').hide().removeClass('success-message error-message').text('');
        if (modalId === 'registerModal' && $('#registerForm').length) { $('#registerForm')[0].reset(); }
    }
    $('body').on('click', '.close-btn', function() { 
        var modalId = $(this).closest('.modal').attr('id');
        if(modalId) { closeModal(modalId); }
    });
    $(window).on('click', function(event) { 
        $('.modal').each(function() { if (event.target == this) { closeModal(this.id); } });
    });


    // --- LOGIKA USER (LOGIN, REGISTER, DROPDOWN) ---
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
    $('.user_link_container').hover(
        function() { $(this).find('.user_dropdown_content').stop(true, true).slideDown(200); },
        function() { $(this).find('.user_dropdown_content').stop(true, true).slideUp(200); }
    );
    <?php else: ?>
    $('#loginBtn').on('click', function(event) { event.preventDefault(); openModal('loginModal'); });
    $('body').on('click', '#switchToRegisterLink', function(e) { e.preventDefault(); closeModal('loginModal'); openModal('registerModal'); });
    $('body').on('click', '#switchToLoginLink', function(e) { e.preventDefault(); closeModal('registerModal'); openModal('loginModal'); });
    $('#registerForm').on('submit', function(e) { e.preventDefault(); var formData = $(this).serialize(); $('#registerMessage').hide().removeClass('success-message error-message').text(''); $.ajax({ type: 'POST', url: 'register.php', data: formData, dataType: 'json', success: function(response) { $('#registerMessage').text(response.message); if (response.success) { $('#registerMessage').addClass('success-message').show(); $('#registerForm')[0].reset(); setTimeout(function() { closeModal('registerModal'); openModal('loginModal'); }, 2000); } else { $('#registerMessage').addClass('error-message').show(); } }, error: function() { $('#registerMessage').text('Terjadi kesalahan koneksi.').addClass('error-message').show(); } }); });
    $('#loginForm').on('submit', function(e) { e.preventDefault(); var formData = $(this).serialize(); $('#loginMessage').hide().removeClass('error-message success-message').text(''); $.ajax({ type: 'POST', url: $(this).attr('action'), data: formData, dataType: 'json', success: function(response) { if (response.success) { window.location.reload(); } else { $('#loginMessage').text(response.message).addClass('error-message').show(); } }, error: function(jqXHR, textStatus, errorThrown) { $('#loginMessage').text('Terjadi kesalahan: ' + textStatus + '.').addClass('error-message').show(); console.error("AJAX Error:", textStatus, errorThrown, jqXHR.responseText); } }); });
    <?php endif; ?>
    
    
    // --- LOGIKA KERANJANG ---
    var cartHoverTimer; 
    function updateCartBadge() { $.ajax({ url: 'keranjang_aksi.php?action=count', type: 'GET', dataType: 'json', success: function(response) { if (response.success && response.count > 0) { $('#cartBadge').text(response.count).show(); } else { $('#cartBadge').text('0').hide(); } }, error: function(){ $('#cartBadge').text('0').hide(); } }); }
    
    function loadCartHoverPopup() {
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
        $.ajax({
            url: 'keranjang_aksi.php?action=get_cart_summary', type: 'GET', dataType: 'json',
            success: function(response) {
                var cartContentHtml = '';
                if (response.success && response.cart_items && response.cart_items.length > 0) {
                    response.cart_items.slice(0, 3).forEach(function(item) { cartContentHtml += `<div class="cart-hover-item"><img src="${item.foto}" alt="${item.nama}"><div class="cart-hover-item-details"><span class="cart-hover-item-name">${item.nama}</span><span class="cart-hover-item-price-qty">${item.kuantitas} x Rp ${number_format(item.harga_satuan)}</span></div></div>`; });
                    if (response.cart_items.length > 3) { cartContentHtml += `<div style="text-align:center; font-size:0.8em; margin-top:5px;">dan ${response.cart_items.length - 3} item lainnya...</div>`; }
                    $('#cartHoverTotal').html(`Total: Rp ${number_format(response.total_harga_keseluruhan)}`);
                } else { cartContentHtml = '<p style="text-align:center; margin:10px 0;">Keranjang Anda kosong.</p>'; $('#cartHoverTotal').html(''); }
                $('#cartHoverContent').html(cartContentHtml);
            }, error: function(){ $('#cartHoverContent').html('<p style="text-align:center; margin:10px 0;">Gagal memuat keranjang.</p>'); $('#cartHoverTotal').html('');}
        });
        <?php else: ?>
        $('#cartHoverContent').html('<p style="text-align:center; margin:10px 0;">Login untuk melihat keranjang.</p>'); 
        $('#cartHoverTotal').html('');
        <?php endif; ?>
    }
    updateCartBadge(); 
    
    $('.cart_link_container').hover(
        function() { 
            clearTimeout(cartHoverTimer);
            loadCartHoverPopup(); 
            $('#cartHoverPopup').stop(true, true).fadeIn(200); 
        }, 
        function() { 
            cartHoverTimer = setTimeout(function() {
                if (!$('#cartHoverPopup:hover').length) { 
                    $('#cartHoverPopup').stop(true, true).fadeOut(200);
                }
            }, 200); 
        }
    );
    $('#cartHoverPopup').hover(
        function() { clearTimeout(cartHoverTimer); },
        function() { cartHoverTimer = setTimeout(function() { $('#cartHoverPopup').stop(true, true).fadeOut(200); }, 200); }
    );

    // Event untuk membuka modal detail produk dan mengisi data review
    $('.food_section').on('click', '.open-product-detail-modal-btn', function(e) {
        e.preventDefault();
        var isLoggedIn = <?php echo json_encode(isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true); ?>;
        
        var kd = $(this).data('kd');
        var nama = $(this).data('nama');
        var harga = parseFloat($(this).data('harga')); 
        var foto = $(this).data('foto');
        var deskripsi = $(this).data('deskripsi');

        // Isi detail produk untuk ditampilkan di modal
        $('#productDetailNama').text(nama);
        $('#productDetailFoto').attr('src', foto).attr('alt', nama);
        $('#productDetailDeskripsi').html(nl2br(deskripsi)); 
        $('#productDetailHarga').text(number_format(harga));
        $('#productDetailKuantitas').val(1); 
        $('#productDetailKdBrng').val(kd); 
        $('#productDetailHargaSatuan').val(harga); 
        $('#productDetailFotoPath').val(foto); 
        
        // Isi data untuk form review
        if (isLoggedIn) {
            $('#kd_brng_review').val(kd);      
            $('#nm_brng_review').val(nama);    
            $('#formProductReview').show();    
            $('.product-review-form-container').find('p:has(.open-login-from-review)').hide(); 
        } else {
            $('#formProductReview').hide();    
            $('.product-review-form-container').find('p:has(.open-login-from-review)').show(); 
        }
        $('#reviewFormMessageModal').hide().removeClass('success-message error-message').text('');
        if ($('#formProductReview').length && isLoggedIn) { 
             $('#formProductReview')[0].reset();
             $('#formProductReview input[name="review_rating"]').prop('checked', false);
        }
        
        openModal('productDetailModal');
    });

    // Link "Login" dari form review (jika pengguna belum login dan mencoba review)
    $('body').on('click', '.open-login-from-review', function(e){
        e.preventDefault();
        closeModal('productDetailModal'); 
        openModal('loginModal');
        $('#loginMessage').text('Anda harus login untuk memberi review.').addClass('error-message').show();
    });


    $('#productDetailIncreaseQty').on('click', function() { var qtyInput = $('#productDetailKuantitas'); qtyInput.val(parseInt(qtyInput.val()) + 1); });
    $('#productDetailDecreaseQty').on('click', function() { var qtyInput = $('#productDetailKuantitas'); if (parseInt(qtyInput.val()) > 1) { qtyInput.val(parseInt(qtyInput.val()) - 1); } });

    $('#addProductToCartFinalBtn').on('click', function() {
        var kd_brng = $('#productDetailKdBrng').val(); var nm_brng = $('#productDetailNama').text(); var harga_brng = $('#productDetailHargaSatuan').val(); var ft_brng = $('#productDetailFotoPath').val(); var kuantitas = parseInt($('#productDetailKuantitas').val());
        if (kuantitas < 1) { $('#productDetailModalMessage').text('Kuantitas minimal 1.').addClass('error-message').show(); return; }
        $('#productDetailModalMessage').hide().removeClass('success-message error-message').text('');
        $.ajax({
            url: 'keranjang_aksi.php?action=add', type: 'POST', data: { kd_brng: kd_brng, nm_brng: nm_brng, harga_brng: harga_brng, ft_brng: ft_brng, kuantitas: kuantitas }, dataType: 'json',
            success: function(response) {
                $('#productDetailModalMessage').text(response.message);
                if (response.success) {
                    $('#productDetailModalMessage').removeClass('error-message').addClass('success-message').show();
                    updateCartBadge(); 
                    setTimeout(function() { closeModal('productDetailModal'); }, 1500);
                } else { $('#productDetailModalMessage').removeClass('success-message').addClass('error-message').show(); }
            },
            error: function() { $('#productDetailModalMessage').text('Gagal menambah ke keranjang.').addClass('error-message').show(); }
        });
    });
    
    function loadCartDetailModal() {
        $.ajax({
            url: 'keranjang_aksi.php?action=get_cart_detail', type: 'GET', dataType: 'json',
            success: function(response) {
                var cartDetailHtml = '';
                if (response.success && response.cart_items && response.cart_items.length > 0) {
                    cartDetailHtml += `<table class="table-admin"><thead><tr><th colspan="2">Produk</th><th>Harga Satuan</th><th>Kuantitas</th><th>Subtotal</th><th>Aksi</th></tr></thead><tbody>`;
                    response.cart_items.forEach(function(item) {
                        cartDetailHtml += `<tr data-kd="${item.kd_brng}"><td><img src="${item.foto}" alt="${item.nama}"></td><td>${item.nama}</td><td>Rp ${number_format(item.harga_satuan)}</td><td class="quantity-controls"><button class="btn btn-sm btn-outline-secondary decrease-qty-btn">-</button><span class="item-quantity">${item.kuantitas}</span><button class="btn btn-sm btn-outline-secondary increase-qty-btn">+</button></td><td>Rp ${number_format(item.harga_total)}</td><td><button class="item-remove-btn" title="Hapus item"><i class="fa fa-trash"></i></button></td></tr>`;
                    });
                    cartDetailHtml += `</tbody></table>`;
                    $('#cartDetailTotalAmount').text('Rp ' + number_format(response.total_harga_keseluruhan));
                    $('#processCheckoutBtn').prop('disabled', false).html('<i class="fa fa-shopping-basket"></i> Proses Pesanan'); 
                    $('#checkoutFormContainer').show(); 
                    $('#cartDetailSummary').show(); 
                } else {
                    cartDetailHtml = '<p class="text-center">Keranjang Anda kosong.</p>';
                    $('#cartDetailTotalAmount').text('Rp 0');
                    $('#processCheckoutBtn').prop('disabled', true).html('<i class="fa fa-shopping-basket"></i> Proses Pesanan'); 
                    $('#checkoutFormContainer').hide(); 
                    $('#cartDetailSummary').show(); 
                }
                $('#cartDetailContent').html(cartDetailHtml);
                openModal('cartDetailModal'); 
            },
            error: function() { 
                $('#cartDetailContent').html('<p class="text-center text-danger">Gagal memuat keranjang.</p>'); 
                $('#processCheckoutBtn').prop('disabled', true); 
                $('#checkoutFormContainer').hide(); 
                $('#cartDetailSummary').show();
                openModal('cartDetailModal'); 
            }
        });
    }
    
    $('body').on('click', '#cartDetailContent .increase-qty-btn, #cartDetailContent .decrease-qty-btn, #cartDetailContent .item-remove-btn', function() {
        var kd_brng = $(this).closest('tr').data('kd'); var action_type = ''; var newQuantity = 0; var currentQuantityElement = $(this).closest('tr').find('.item-quantity'); var currentQuantity = parseInt(currentQuantityElement.text());
        if ($(this).hasClass('increase-qty-btn')) { action_type = 'update_quantity'; newQuantity = currentQuantity + 1;
        } else if ($(this).hasClass('decrease-qty-btn')) { action_type = 'update_quantity'; newQuantity = currentQuantity - 1; if (newQuantity < 1) { if (!confirm("Kuantitas akan menjadi 0. Hapus item ini dari keranjang?")) { return; } action_type = 'remove_item'; newQuantity = 0; }
        } else if ($(this).hasClass('item-remove-btn')) { action_type = 'remove_item'; if (!confirm("Hapus item '" + $(this).closest('tr').find('td:nth-child(2)').text() + "' dari keranjang?")) { return; } }
        if(action_type){ $.ajax({ url: 'keranjang_aksi.php', type: 'POST', data: { action: action_type, kd_brng: kd_brng, kuantitas: (action_type === 'update_quantity' ? newQuantity : undefined ) }, dataType: 'json', success: function(response) { if (response.success) { loadCartDetailModal(); updateCartBadge(); } else { alert('Error: ' + response.message); } }, error: function() { alert('Terjadi kesalahan saat memperbarui keranjang.'); } }); }
    });

    $('body').on('click', '#cartIcon, #viewCartModalLink', function(e) { 
        e.preventDefault();
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
            $('#cartHoverPopup').stop(true, true).fadeOut(100); 
            loadCartDetailModal(); 
        <?php else: ?>
            openModal('loginModal'); $('#loginMessage').text('Login untuk melihat keranjang Anda.').addClass('error-message').show();
        <?php endif; ?>
    });
    
    // --- JAVASCRIPT PROSES CHECKOUT ---
    $('body').on('click', '#processCheckoutBtn', function(e) {
    e.preventDefault();
    var $checkoutButton = $(this); 
    $('#checkoutMessage').hide().removeClass('success-message error-message').text('');

    var isValid = true;
    var namaPemesan = $('#checkout_nama_pemesan').val().trim();
    var nomorTelepon = $('#checkout_nomor_telepon').val().trim();
    var alamatPengiriman = $('#checkout_alamat_pengiriman').val().trim();

    if (namaPemesan === '' || nomorTelepon === '' || alamatPengiriman === '') {
        $('#checkoutMessage').text('Nama, Telepon, dan Alamat wajib diisi.').addClass('error-message').show();
        isValid = false; 
    }

    if (!isValid) { return; }

    var checkoutData = {
        nama_pemesan: namaPemesan, 
        nomor_telepon: nomorTelepon, 
        alamat_pengiriman: alamatPengiriman,
        catatan_pesanan: $('#checkout_catatan_pesanan').val().trim(),
        action: 'process_order' 
    };

    $checkoutButton.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Membuat Sesi...');

    $.ajax({
        url: 'checkout_midtrans_proses.php', // URL diubah ke skrip Midtrans
        type: 'POST', 
        data: checkoutData, 
        dataType: 'json',
        success: function(response) {
            if (response && response.success === true && response.snap_token) {
                closeModal('cartDetailModal'); 
                snap.pay(response.snap_token, {
                    onSuccess: function(result){
                        console.log('Payment Success:', result);
                        alert("Pembayaran berhasil! ID Pesanan Anda: " + result.order_id + ". Halaman akan dimuat ulang.");
                        window.location.reload(); 
                    },
                    onPending: function(result){
                        console.log('Payment Pending:', result);
                        alert("Pesanan Anda #" + result.order_id + " menunggu pembayaran. Silakan selesaikan pembayaran Anda.");
                        window.location.reload(); 
                    },
                    onError: function(result){
                        console.error('Payment Error:', result);
                        alert("Pembayaran gagal. Silakan coba lagi.");
                    },
                    onClose: function(){
                        console.log('Popup pembayaran ditutup.');
                        alert('Anda menutup popup pembayaran. Pesanan Anda telah dibuat dengan status menunggu pembayaran.');
                        window.location.reload();
                    }
                });
            } else {
                alert("Gagal memproses pesanan: " + (response.message || 'Error tidak diketahui dari server.'));
                $checkoutButton.prop('disabled', false).html('Proses Pesanan'); 
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            alert('Terjadi kesalahan koneksi atau server: ' + textStatus);
            $checkoutButton.prop('disabled', false).html('Proses Pesanan'); 
        }
    });
});

    // --- JAVASCRIPT FORM REVIEW ---
    $('body').on('click', '.open-login-from-review', function(e){
        e.preventDefault();
        closeModal('productDetailModal'); 
        openModal('loginModal');
        $('#loginMessage').text('Anda harus login untuk memberi review.').addClass('error-message').show();
    });

    $('body').on('submit', '#formProductReview', function(e) {
        e.preventDefault();
        $('#reviewFormMessageModal').hide().removeClass('success-message error-message').text('');
        var formData = $(this).serializeArray(); 
        var actionData = { name: "action", value: "submit_review" }; 
        formData.push(actionData);

        var rating = $('#formProductReview input[name="review_rating"]:checked').val();
        if (!rating) {
            $('#reviewFormMessageModal').text('Silakan pilih rating bintang.').addClass('error-message').show();
            return;
        }

        var $submitButtonReview = $('#submitReviewBtnModal');
        $submitButtonReview.prop('disabled', true).text('Mengirim...');

        $.ajax({
            type: 'POST', url: 'review_aksi.php', data: $.param(formData), dataType: 'json',
            success: function(response) {
                $('#reviewFormMessageModal').text(response.message);
                if (response.success) {
                    $('#reviewFormMessageModal').removeClass('error-message').addClass('success-message').show();
                    $('#formProductReview')[0].reset(); 
                    $('#formProductReview input[name="review_rating"]').prop('checked', false);
                } else {
                    $('#reviewFormMessageModal').removeClass('success-message').addClass('error-message').show();
                }
            },
            error: function() { $('#reviewFormMessageModal').text('Terjadi kesalahan saat mengirim review. Coba lagi.').addClass('error-message').show(); },
            complete: function() { $submitButtonReview.prop('disabled', false).text('Kirim Review'); }
        });
    });
    
    if ($("#bookLink").length) { $("#bookLink").on("click", function (e) { /* ... smooth scroll ... */ }); }
});
</script>

</body>
</html>