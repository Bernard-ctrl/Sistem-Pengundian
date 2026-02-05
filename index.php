<?php

$host = 'localhost';
$db = 'pengundian';
$user = 'root';
$pass = '';

// Connect to MySQL server
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die('Gagal sambungan ke pelayan MySQL.');
}

// Create database if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS $db");
$conn->select_db($db);

// Create PENGGUNA table (Users)
$conn->query("CREATE TABLE IF NOT EXISTS PENGGUNA (
    id_Pengguna VARCHAR(10) PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Migration: Add password column if it doesn't exist (for existing tables)
$result = $conn->query("SHOW COLUMNS FROM PENGGUNA LIKE 'password'");
if ($result && $result->num_rows == 0) {
    $conn->query("ALTER TABLE PENGGUNA ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT ''");
}

// Migration: Add is_admin column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM PENGGUNA LIKE 'is_admin'");
if ($result && $result->num_rows == 0) {
    $conn->query("ALTER TABLE PENGGUNA ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0");
}

// Create JAWATAN table (Positions)
$conn->query("CREATE TABLE IF NOT EXISTS JAWATAN (
    id_Jawatan VARCHAR(10) PRIMARY KEY,
    nama_Jawatan VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create CALON table (Candidates)
$conn->query("CREATE TABLE IF NOT EXISTS CALON (
    id_Calon VARCHAR(10) PRIMARY KEY,
    nama_Calon VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Create UNDIAN table (Votes)
$conn->query("CREATE TABLE IF NOT EXISTS UNDIAN (
    id_Undi INT AUTO_INCREMENT PRIMARY KEY,
    id_Pengguna VARCHAR(10) NOT NULL,
    id_Calon VARCHAR(10) NOT NULL,
    id_Jawatan VARCHAR(10) NOT NULL,
    voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_Pengguna) REFERENCES PENGGUNA(id_Pengguna) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_Calon) REFERENCES CALON(id_Calon) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_Jawatan) REFERENCES JAWATAN(id_Jawatan) ON DELETE CASCADE ON UPDATE CASCADE,
    UNIQUE KEY unique_vote (id_Pengguna, id_Jawatan),
    INDEX idx_pengguna (id_Pengguna),
    INDEX idx_calon (id_Calon),
    INDEX idx_jawatan (id_Jawatan)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Insert default positions if not exist
$defaultPositions = [
    ['J01', 'Pengerusi'],
    ['J02', 'Setiausaha'],
    ['J03', 'Bendahari']
];
foreach ($defaultPositions as $pos) {
    $stmt = $conn->prepare("INSERT IGNORE INTO JAWATAN (id_Jawatan, nama_Jawatan) VALUES (?, ?)");
    $stmt->bind_param("ss", $pos[0], $pos[1]);
    $stmt->execute();
    $stmt->close();
}

// Insert default candidates if not exist
$defaultCandidates = [
    ['C01', 'Omar'],
    ['C02', 'Hassan'],
    ['C03', 'Aiman']
];
foreach ($defaultCandidates as $cand) {
    $stmt = $conn->prepare("INSERT IGNORE INTO CALON (id_Calon, nama_Calon) VALUES (?, ?)");
    $stmt->bind_param("ss", $cand[0], $cand[1]);
    $stmt->execute();
    $stmt->close();
}

// Ensure default admin user exists (force username/password 'admin' for local testing)
$defaultAdmin = 'admin';
$defaultPassword = 'admin';
$nama = 'Administrator';

// Compute password hash for the desired admin password
$hash = password_hash($defaultPassword, PASSWORD_DEFAULT);

// Insert admin if missing (safe no-op if already exists)
$ins = $conn->prepare("INSERT IGNORE INTO PENGGUNA (id_Pengguna, nama, password, is_admin) VALUES (?, ?, ?, 1)");
$ins->bind_param("sss", $defaultAdmin, $nama, $hash);
$ins->execute();
$ins->close();

// Force-reset admin password and admin flag to ensure credentials are exactly 'admin' for this project
$upd = $conn->prepare("UPDATE PENGGUNA SET password = ?, is_admin = 1, nama = ? WHERE id_Pengguna = ?");
$upd->bind_param("sss", $hash, $nama, $defaultAdmin);
$upd->execute();
$upd->close();


session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Signup
    if (isset($_POST['action']) && $_POST['action'] === 'signup') {
        $id_pengguna = $_POST['username']; // Use username as ID
        $nama = $_POST['nama'] ?? $_POST['username']; // Get name from form
        $password = $_POST['password'];
        $is_admin = 0; // Regular users are not admin by default
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT id_Pengguna FROM PENGGUNA WHERE id_Pengguna = ?");
        $stmt->bind_param("s", $id_pengguna);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo "ID Pengguna sudah wujud.";
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->close();
        
        // Insert new user
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO PENGGUNA (id_Pengguna, nama, password, is_admin) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $id_pengguna, $nama, $hash, $is_admin);
        $stmt->execute();
        $stmt->close();
        echo "Pendaftaran berjaya. Sila log masuk.";
        $conn->close();
        exit;
    }
    
    // Sign in
    if (isset($_POST['action']) && $_POST['action'] === 'signin') {
        $id_pengguna = $_POST['username'];
        $password = $_POST['password'];
        $stmt = $conn->prepare("SELECT password, is_admin, nama FROM PENGGUNA WHERE id_Pengguna = ?");
        $stmt->bind_param("s", $id_pengguna);
        $stmt->execute();
        $stmt->bind_result($hash, $is_admin, $nama);
        if ($stmt->fetch() && password_verify($password, $hash)) {
            $_SESSION['id_pengguna'] = $id_pengguna;
            $_SESSION['nama'] = $nama;
            $_SESSION['is_admin'] = $is_admin;
            echo $is_admin ? "Log masuk sebagai admin." : "Log masuk berjaya.";
        } else {
            echo "Log masuk gagal. Sila semak ID pengguna dan kata laluan.";
        }
        $stmt->close();
        $conn->close();
        exit;
    }
    
    // Voting (only for logged-in users)
    if (isset($_POST['id_jawatan']) && isset($_POST['id_calon'])) {
        if (!isset($_SESSION['id_pengguna'])) {
            $sid = session_id();
            $cookie = isset($_COOKIE[session_name()]) ? $_COOKIE[session_name()] : '(none)';
            echo "Anda mesti log masuk untuk mengundi. SESSION_ID=" . $sid . " COOKIE=" . $cookie;
            $conn->close();
            exit;
        }
        
        $id_pengguna = $_SESSION['id_pengguna'];
        $id_jawatan = $_POST['id_jawatan'];
        $id_calon = $_POST['id_calon'];
        
        // Validate jawatan exists
        $stmt = $conn->prepare("SELECT nama_Jawatan FROM JAWATAN WHERE id_Jawatan = ?");
        $stmt->bind_param("s", $id_jawatan);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            echo "Jawatan tidak sah.";
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->bind_result($nama_jawatan);
        $stmt->fetch();
        $stmt->close();
        
        // Validate calon exists
        $stmt = $conn->prepare("SELECT nama_Calon FROM CALON WHERE id_Calon = ?");
        $stmt->bind_param("s", $id_calon);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows === 0) {
            echo "Calon tidak sah.";
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->bind_result($nama_calon);
        $stmt->fetch();
        $stmt->close();
        
        // Start transaction
        $conn->begin_transaction();
        try {
            // Check if user already voted for this position
            $chk = $conn->prepare("SELECT id_Undi FROM UNDIAN WHERE id_Pengguna = ? AND id_Jawatan = ?");
            $chk->bind_param("ss", $id_pengguna, $id_jawatan);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $chk->close();
                $conn->rollback();
                echo "Anda telah mengundi untuk jawatan ini sebelum ini.";
                $conn->close();
                exit;
            }
            $chk->close();
            
            // Record vote
            $ins = $conn->prepare("INSERT INTO UNDIAN (id_Pengguna, id_Calon, id_Jawatan) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $id_pengguna, $id_calon, $id_jawatan);
            if (!$ins->execute()) {
                $err = $ins->error;
                $ins->close();
                $conn->rollback();
                echo "Ralat DB: " . $err;
                $conn->close();
                exit;
            }
            $ins->close();
            
            // Commit
            $conn->commit();
            
            // Get vote count for admin
            if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM UNDIAN WHERE id_Calon = ? AND id_Jawatan = ?");
                $stmt->bind_param("ss", $id_calon, $id_jawatan);
                $stmt->execute();
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();
                echo "Undian untuk $nama_calon ($nama_jawatan) telah direkodkan. Jumlah undian: " . $count;
            } else {
                echo "Undian untuk $nama_calon ($nama_jawatan) telah direkodkan.";
            }
            $conn->close();
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            echo "Ralat semasa mengundi: " . $e->getMessage();
            $conn->close();
            exit;
        }
    }
    
    // Add user (admin only)
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
            echo "Akses ditolak.";
            $conn->close();
            exit;
        }
        $id_pengguna = $_POST['username'];
        $nama = $_POST['nama'];
        $password = $_POST['password'];
        $is_admin = isset($_POST['is_admin']) ? 1 : 0;
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT id_Pengguna FROM PENGGUNA WHERE id_Pengguna = ?");
        $stmt->bind_param("s", $id_pengguna);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo "ID Pengguna sudah wujud.";
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->close();
        
        // Insert new user
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO PENGGUNA (id_Pengguna, nama, password, is_admin) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $id_pengguna, $nama, $hash, $is_admin);
        $stmt->execute();
        $stmt->close();
        echo "Pengguna berjaya ditambah.";
        $conn->close();
        exit;
    }
}

// Admin endpoints (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    // Get positions (jawatan) - public endpoint
    if ($_GET['action'] === 'get_jawatan') {
        $result = $conn->query("SELECT id_Jawatan, nama_Jawatan FROM JAWATAN ORDER BY id_Jawatan");
        $jawatan = [];
        while ($row = $result->fetch_assoc()) {
            $jawatan[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode($jawatan);
        $conn->close();
        exit;
    }
    
    // Get candidates (calon) - public endpoint
    if ($_GET['action'] === 'get_calon') {
        $result = $conn->query("SELECT id_Calon, nama_Calon FROM CALON ORDER BY id_Calon");
        $calon = [];
        while ($row = $result->fetch_assoc()) {
            $calon[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode($calon);
        $conn->close();
        exit;
    }
    
    // Allow users to request their own votes
    if ($_GET['action'] === 'my_votes') {
        if (!isset($_SESSION['id_pengguna'])) {
            header('Content-Type: application/json');
            echo json_encode([]);
            $conn->close();
            exit;
        }
        $id_pengguna = $_SESSION['id_pengguna'];
        $stmt = $conn->prepare("SELECT u.id_Jawatan, j.nama_Jawatan, u.id_Calon, c.nama_Calon FROM UNDIAN u JOIN JAWATAN j ON u.id_Jawatan = j.id_Jawatan JOIN CALON c ON u.id_Calon = c.id_Calon WHERE u.id_Pengguna = ?");
        $stmt->bind_param("s", $id_pengguna);
        $stmt->execute();
        $res = $stmt->get_result();
        $votes = [];
        while ($row = $res->fetch_assoc()) {
            $votes[] = $row;
        }
        header('Content-Type: application/json');
        echo json_encode($votes);
        $stmt->close();
        $conn->close();
        exit;
    }
    
    // Admin-only endpoints
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        echo "Akses ditolak. Anda bukan admin.";
        $conn->close();
        exit;
    }
    
    if ($_GET['action'] === 'view_users') {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $query = "SELECT id_Pengguna, nama, is_admin FROM PENGGUNA";
        $params = [];
        if ($search) {
            $query .= " WHERE id_Pengguna LIKE ? OR nama LIKE ?";
            $params = ["%$search%", "%$search%"];
        }
        $query .= " ORDER BY id_Pengguna";
        $stmt = $conn->prepare($query);
        if ($params) {
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h3>Senarai Pengguna</h3><table style='width:100%;border-collapse:collapse;'><thead><tr><th style='border:1px solid #ddd;padding:8px;text-align:left;'>ID Pengguna</th><th style='border:1px solid #ddd;padding:8px;text-align:left;'>Nama</th><th style='border:1px solid #ddd;padding:8px;text-align:left;'>Status</th><th style='border:1px solid #ddd;padding:8px;text-align:left;'>Tindakan</th></tr></thead><tbody>";
        while ($row = $result->fetch_assoc()) {
            $adminLabel = $row['is_admin'] ? "Admin" : "Pengguna Biasa";
            $deleteBtn = $row['id_Pengguna'] !== $_SESSION['id_pengguna'] ? "<button class=\"btn\" style=\"background:#e74c3c;color:#fff;border:0;padding:4px 8px;border-radius:4px;cursor:pointer;\" onclick=\"deleteUser('" . htmlspecialchars($row['id_Pengguna']) . "')\">Padam</button>" : "-";
            echo "<tr><td style='border:1px solid #ddd;padding:8px;'>" . htmlspecialchars($row['id_Pengguna']) . "</td><td style='border:1px solid #ddd;padding:8px;'>" . htmlspecialchars($row['nama']) . "</td><td style='border:1px solid #ddd;padding:8px;'>" . $adminLabel . "</td><td style='border:1px solid #ddd;padding:8px;'>" . $deleteBtn . "</td></tr>";
        }
        echo "</tbody></table>";
        $stmt->close();
        $conn->close();
        exit;
    }
    
    if ($_GET['action'] === 'view_votes') {
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $query = "
            SELECT 
                j.nama_Jawatan,
                c.nama_Calon,
                COUNT(*) AS jumlah_undian
            FROM UNDIAN u
            JOIN CALON c ON u.id_Calon = c.id_Calon
            JOIN JAWATAN j ON u.id_Jawatan = j.id_Jawatan";
        $params = [];
        if ($search) {
            $query .= " WHERE j.nama_Jawatan LIKE ? OR c.nama_Calon LIKE ?";
            $params = ["%$search%", "%$search%"];
        }
        $query .= " GROUP BY j.nama_Jawatan, c.nama_Calon
            ORDER BY j.nama_Jawatan, jumlah_undian DESC";
        $stmt = $conn->prepare($query);
        if ($params) {
            $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        echo "<h3>Senarai Undian</h3>";
        $currentPos = null;
        echo "<div>";
        while ($row = $result->fetch_assoc()) {
            if ($currentPos !== $row['nama_Jawatan']) {
                if ($currentPos !== null) echo "</ul>";
                $currentPos = $row['nama_Jawatan'];
                echo "<h4>" . htmlspecialchars($currentPos) . "</h4><ul>";
            }
            echo "<li>" . htmlspecialchars($row['nama_Calon']) . ": " . $row['jumlah_undian'] . " undian</li>";
        }
        if ($currentPos !== null) echo "</ul>";
        echo "</div>";
        $stmt->close();
        $conn->close();
        exit;
    }
    
    if ($_GET['action'] === 'delete_user') {
        $user_id = $_GET['user_id'];
        // Prevent deleting self
        if ($user_id === $_SESSION['id_pengguna']) {
            echo "Anda tidak boleh padam akaun sendiri.";
            $conn->close();
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM PENGGUNA WHERE id_Pengguna = ?");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            echo "Pengguna berjaya dipadam.";
        } else {
            echo "Pengguna tidak dijumpai.";
        }
        $stmt->close();
        $conn->close();
        exit;
    }
    
    if ($_GET['action'] === 'view_candidates') {
        $result = $conn->query("SELECT id_Calon, nama_Calon FROM CALON ORDER BY id_Calon");
        echo "<h3>Senarai Calon</h3><ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['id_Calon']) . " - " . htmlspecialchars($row['nama_Calon']) . "</li>";
        }
        echo "</ul>";
        $conn->close();
        exit;
    }
    
    if ($_GET['action'] === 'view_positions') {
        $result = $conn->query("SELECT id_Jawatan, nama_Jawatan FROM JAWATAN ORDER BY id_Jawatan");
        echo "<h3>Senarai Jawatan</h3><ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['id_Jawatan']) . " - " . htmlspecialchars($row['nama_Jawatan']) . "</li>";
        }
        echo "</ul>";
        $conn->close();
        exit;
    }
}

// If accessed directly, show nothing or redirect
header('Location: index.html');
$conn->close();
exit;
?>