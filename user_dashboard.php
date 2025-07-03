<?php
// user_dashboard.php
require 'config.php';

// Lindungi halaman, hanya untuk user yang sudah login
if (!isset($_SESSION['user_id'])) {
    header("location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';

// Proses pemesanan baru
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pesan'])) {
    $galon_id = $_POST['galon_id'];
    $jumlah = $_POST['jumlah'];

    // Ambil alamat default pengguna dari database
    $user_query = $conn->query("SELECT Alamat FROM pengguna WHERE UserID = $user_id");
    $user_data = $user_query->fetch_assoc();
    $alamat_pengiriman = $user_data['Alamat'];

    $stmt = $conn->prepare("INSERT INTO pesanan (UserID, GalonID, Alamat_Pengiriman, Jumlah_Galon, Status_Pesanan) VALUES (?, ?, ?, ?, 'Menunggu')");
    $stmt->bind_param("iisi", $user_id, $galon_id, $alamat_pengiriman, $jumlah);

    if ($stmt->execute()) {
        $message = "success|Pesanan berhasil dibuat! Mohon tunggu konfirmasi dari admin.";
        header("Location: user_dashboard.php"); // Redirect ke halaman yang sama
        exit();
    } else {
        $message = "error|Gagal membuat pesanan. Silakan coba lagi.";
    }
    $stmt->close();
}

// Ambil data galon yang tersedia (stok > 0)
$galon_result = $conn->query("SELECT GalonID, Tipe_Galon, Harga, Stok FROM galon WHERE Stok > 0");

// Ambil riwayat pesanan pengguna
$history_result = $conn->query("
    SELECT p.PesananID, p.Tanggal_Pesan, g.Tipe_Galon, p.Jumlah_Galon, p.Status_Pesanan, pay.Status_Pembayaran, (g.Harga * p.Jumlah_Galon) as Total_Harga
    FROM pesanan p
    JOIN galon g ON p.GalonID = g.GalonID
    LEFT JOIN pembayaran pay ON p.PesananID = pay.PesananID
    WHERE p.UserID = $user_id
    ORDER BY p.Tanggal_Pesan DESC
");

// Ambil statistik user
$stats_query = $conn->query("
    SELECT 
        COUNT(*) as total_pesanan,
        SUM(CASE WHEN Status_Pesanan = 'Selesai' THEN 1 ELSE 0 END) as pesanan_selesai,
        SUM(CASE WHEN Status_Pesanan = 'Menunggu' THEN 1 ELSE 0 END) as pesanan_menunggu,
        SUM(CASE WHEN Status_Pesanan = 'Diproses' THEN 1 ELSE 0 END) as pesanan_diproses
    FROM pesanan 
    WHERE UserID = $user_id
");
$stats = $stats_query->fetch_assoc();

// Ambil data user
$user_query = $conn->query("SELECT Nama, Email, Nomor_Telepon, Alamat FROM pengguna WHERE UserID = $user_id");
$user_info = $user_query->fetch_assoc();

// Di bagian atas file, sebelum HTML:
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']); // Hapus pesan setelah ditampilkan
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RefillGo</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/user_styles.css" rel="stylesheet">
</head>

<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <img src="img/refillgologo.webp" alt="RefillGo Logo" class="logo">
                    <h2>RefillGo</h2>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item active" data-section="dashboard">
                        <a href="#dashboard">
                            <i class="fas fa-home"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item" data-section="pesan">
                        <a href="#pesan">
                            <i class="fas fa-plus-circle"></i>
                            <span>Buat Pesanan</span>
                        </a>
                    </li>
                    <li class="nav-item" data-section="riwayat">
                        <a href="#riwayat">
                            <i class="fas fa-history"></i>
                            <span>Riwayat Pesanan</span>
                        </a>
                    </li>
                    <li class="nav-item" data-section="profil">
                        <a href="#profil">
                            <i class="fas fa-user"></i>
                            <span>Profil Saya</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile">
                    <img src="img/pp.jpg" alt="User" class="user-avatar">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <span class="user-role">Pelanggan</span>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="main-header">
                <div class="header-left">
                    <button class="sidebar-toggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1 class="page-title">Dashboard</h1>
                </div>
                <div class="header-right">
                    <div class="header-actions">
                        <button class="notification-btn">
                            <i class="fas fa-bell"></i>
                            <span class="notification-badge">2</span>
                        </button>
                        <div class="user-menu">
                            <img src="img/pp.jpg?height=32&width=32" alt="User" class="user-avatar-header">
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content Wrapper -->
            <div class="content-wrapper">
                <?php if ($message): ?>
                    <?php
                    $msg_parts = explode('|', $message);
                    $msg_type = $msg_parts[0];
                    $msg_text = $msg_parts[1];
                    ?>
                    <div class="alert alert-<?php echo $msg_type; ?>">
                        <i class="fas fa-<?php echo $msg_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <?php echo $msg_text; ?>
                    </div>
                <?php endif; ?>

                <!-- Dashboard Section -->
                <section id="dashboard" class="content-section active">
                    <div class="welcome-banner">
                        <div class="welcome-content">
                            <h2>Selamat Datang, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h2>
                            <p>Kelola pesanan air galon Anda dengan mudah</p>
                        </div>
                        <div class="welcome-image">
                            <img src="img/pp.jpg?height=120&width=120" alt="Welcome">
                        </div>
                    </div>

                    <!-- Stats Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $stats['total_pesanan'] ?? 0; ?></h3>
                                <p>Total Pesanan</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $stats['pesanan_selesai'] ?? 0; ?></h3>
                                <p>Pesanan Selesai</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $stats['pesanan_menunggu'] ?? 0; ?></h3>
                                <p>Menunggu Konfirmasi</p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div class="stat-content">
                                <h3><?php echo $stats['pesanan_diproses'] ?? 0; ?></h3>
                                <p>Sedang Diproses</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="quick-actions">
                        <h3>Aksi Cepat</h3>
                        <div class="action-buttons">
                            <button class="action-btn" onclick="showSection('pesan')">
                                <i class="fas fa-plus"></i>
                                <span>Buat Pesanan Baru</span>
                            </button>
                            <button class="action-btn" onclick="showSection('riwayat')">
                                <i class="fas fa-history"></i>
                                <span>Lihat Riwayat</span>
                            </button>
                            <button class="action-btn" onclick="showSection('profil')">
                                <i class="fas fa-user-edit"></i>
                                <span>Edit Profil</span>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Buat Pesanan Section -->
                <section id="pesan" class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-plus-circle"></i> Buat Pesanan Baru</h2>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Pilih Produk</h3>
                            <p>Pilih jenis galon dan jumlah yang Anda inginkan</p>
                        </div>
                        <div class="card-body">
                            <form action="user_dashboard.php" method="post" class="order-form">
                                <div class="form-group">
                                    <label for="galon_id">
                                        <i class="fas fa-tint"></i> Pilih Tipe Galon
                                    </label>
                                    <select name="galon_id" id="galon_id" required class="form-control">
                                        <option value="">-- Pilih Galon --</option>
                                        <?php while ($galon = $galon_result->fetch_assoc()): ?>
                                            <option value="<?php echo $galon['GalonID']; ?>" data-price="<?php echo $galon['Harga']; ?>">
                                                <?php echo htmlspecialchars($galon['Tipe_Galon']) . " - Rp " . number_format($galon['Harga']); ?> (Stok: <?php echo $galon['Stok']; ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="jumlah">
                                        <i class="fas fa-sort-numeric-up"></i> Jumlah
                                    </label>
                                    <input type="number" name="jumlah" id="jumlah" value="1" min="1" required class="form-control">
                                </div>

                                <div class="order-summary">
                                    <div class="summary-item">
                                        <span>Total Estimasi:</span>
                                        <span class="total-price">Rp 0</span>
                                    </div>
                                </div>

                                <div class="form-actions">
                                    <button type="submit" name="pesan" class="btn btn-primary">
                                        <i class="fas fa-shopping-cart"></i> Pesan Sekarang
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

                <!-- Riwayat Pesanan Section -->
                <section id="riwayat" class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-history"></i> Riwayat Pesanan</h2>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Daftar Pesanan Anda</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($history_result && $history_result->num_rows > 0): ?>
                                <div class="orders-list">
                                    <?php while ($pesanan = $history_result->fetch_assoc()): ?>
                                        <div class="order-item">
                                            <div class="order-header">
                                                <div class="order-id">
                                                    <strong>Pesanan #<?php echo $pesanan['PesananID']; ?></strong>
                                                </div>
                                                <div class="order-date">
                                                    <?php echo date('d M Y, H:i', strtotime($pesanan['Tanggal_Pesan'])); ?>
                                                </div>
                                            </div>
                                            <div class="order-details">
                                                <div class="order-info">
                                                    <div class="info-item">
                                                        <i class="fas fa-tint"></i>
                                                        <span><?php echo htmlspecialchars($pesanan['Tipe_Galon']); ?></span>
                                                    </div>
                                                    <div class="info-item">
                                                        <i class="fas fa-sort-numeric-up"></i>
                                                        <span><?php echo $pesanan['Jumlah_Galon']; ?> unit</span>
                                                    </div>
                                                    <div class="info-item">
                                                        <i class="fas fa-money-bill"></i>
                                                        <span>Rp <?php echo number_format($pesanan['Total_Harga'], 0, ',', '.'); ?></span>
                                                    </div>
                                                </div>
                                                <div class="order-status">
                                                    <span class="status-badge status-<?php echo strtolower($pesanan['Status_Pesanan']); ?>">
                                                        <?php echo $pesanan['Status_Pesanan']; ?>
                                                    </span>
                                                    <span class="payment-badge payment-<?php echo strtolower($pesanan['Status_Pembayaran'] ?? 'belum'); ?>">
                                                        <?php echo $pesanan['Status_Pembayaran'] ?? 'Belum Bayar'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-shopping-cart"></i>
                                    <h3>Belum Ada Pesanan</h3>
                                    <p>Anda belum memiliki riwayat pesanan. Mulai pesan sekarang!</p>
                                    <button class="btn btn-primary" onclick="showSection('pesan')">
                                        <i class="fas fa-plus"></i> Buat Pesanan
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- Profil Section -->
                <section id="profil" class="content-section">
                    <div class="section-header">
                        <h2><i class="fas fa-user"></i> Profil Saya</h2>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Informasi Akun</h3>
                        </div>
                        <div class="card-body">
                            <div class="profile-info">
                                <div class="profile-avatar">
                                    <img src="img/pp.jpg?height=80&width=80" alt="Profile" class="avatar-large">
                                    <button class="btn btn-sm btn-secondary">
                                        <i class="fas fa-camera"></i> Ubah Foto
                                    </button>
                                </div>
                                <div class="profile-details">
                                    <div class="detail-item">
                                        <label><i class="fas fa-user"></i> Nama Lengkap</label>
                                        <span><?php echo htmlspecialchars($user_info['Nama']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <label><i class="fas fa-envelope"></i> Email</label>
                                        <span><?php echo htmlspecialchars($user_info['Email']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <label><i class="fas fa-phone"></i> Nomor Telepon</label>
                                        <span><?php echo htmlspecialchars($user_info['Nomor_Telepon']); ?></span>
                                    </div>
                                    <div class="detail-item">
                                        <label><i class="fas fa-map-marker-alt"></i> Alamat</label>
                                        <span><?php echo htmlspecialchars($user_info['Alamat']); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="profile-actions">
                                <button class="btn btn-primary">
                                    <i class="fas fa-edit"></i> Edit Profil
                                </button>
                                <button class="btn btn-secondary">
                                    <i class="fas fa-key"></i> Ubah Password
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script src="js/user_script.js"></script>
</body>

</html>
