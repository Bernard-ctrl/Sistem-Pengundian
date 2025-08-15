<?php
// index.php

$host = 'localhost';
$db = 'pengundian';
$user = 'root';
$pass = '';
$allowed = ['Pengerusi', 'Naib Pengerusi', 'Setiausaha', 'Bendahari'];
// allowed candidates per position (optional server-side validation)
$candidates = [
    'Pengerusi' => ['Ali', 'Aminah', 'Farid'],
    'Naib Pengerusi' => ['Siti', 'Hassan'],
    'Setiausaha' => ['Lina', 'Zulkifli'],
    'Bendahari' => ['Hadi', 'Nurul']
];

// Connect to MySQL server (no DB yet)
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die('Gagal sambungan ke pelayan MySQL.');
}

// Create database if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS $db");

// Select the database
$conn->select_db($db);

// Create table for votes if not exists (position + candidate)
$conn->query("CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    position VARCHAR(50) NOT NULL,
    candidate VARCHAR(50) NOT NULL,
    count INT NOT NULL DEFAULT 0,
    UNIQUE KEY(position, candidate)
)");

// Migration: if the table existed with old schema, ensure new columns & index exist
$res = $conn->query("SHOW COLUMNS FROM votes LIKE 'position'");
if ($res && $res->num_rows == 0) {
    $conn->query("ALTER TABLE votes ADD COLUMN position VARCHAR(50) NOT NULL DEFAULT '' AFTER id");
}
$res = $conn->query("SHOW COLUMNS FROM votes LIKE 'candidate'");
if ($res && $res->num_rows == 0) {
    $conn->query("ALTER TABLE votes ADD COLUMN candidate VARCHAR(50) NOT NULL DEFAULT '' AFTER position");
}
// If old `student` column exists, copy values into candidate then keep the column (safe)
$res = $conn->query("SHOW COLUMNS FROM votes LIKE 'student'");
if ($res && $res->num_rows > 0) {
    // copy student -> candidate where candidate empty
    $conn->query("UPDATE votes SET candidate = student WHERE (candidate = '' OR candidate IS NULL) AND (student <> '' AND student IS NOT NULL)");
    // drop old student index if present
    $resIdx = $conn->query("SHOW INDEX FROM votes WHERE Key_name = 'student'");
    if ($resIdx && $resIdx->num_rows > 0) {
        $conn->query("ALTER TABLE votes DROP INDEX student");
    }
}
// Ensure unique index on (position,candidate)
$resIdx = $conn->query("SHOW INDEX FROM votes WHERE Key_name = 'uniq_pos_cand'");
if (!($resIdx && $resIdx->num_rows > 0)) {
    // create unique index if not exists
    $conn->query("ALTER TABLE votes ADD UNIQUE KEY uniq_pos_cand (position, candidate)");
}

// Create table to track which user voted for which position (one vote per user per position)
$conn->query("CREATE TABLE IF NOT EXISTS user_votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    position VARCHAR(50) NOT NULL,
    candidate VARCHAR(50) NOT NULL,
    voted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY(username, position)
)");

// Create users table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0
)");

// Ensure a default admin account exists (username: admin)
$defaultAdmin = 'admin';
$defaultPassword = 'admin'; // change this after first login
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $defaultAdmin);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows === 0) {
    $stmt->close();
    $hash = password_hash($defaultPassword, PASSWORD_DEFAULT);
    $ins = $conn->prepare("INSERT INTO users (username, password, is_admin) VALUES (?, ?, 1)");
    $ins->bind_param("ss", $defaultAdmin, $hash);
    $ins->execute();
    $ins->close();
} else {
    $stmt->close();
}


session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Signup
    if (isset($_POST['action']) && $_POST['action'] === 'signup') {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $is_admin = ($username === 'admin') ? 1 : 0; // Simple admin check
        // Check if user exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            echo "Username sudah wujud.";
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->close();
        // Insert new user
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, is_admin) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $username, $hash, $is_admin);
        $stmt->execute();
        $stmt->close();
        echo "Pendaftaran berjaya. Sila log masuk.";
        $conn->close();
        exit;
    }
    // Sign in
    if (isset($_POST['action']) && $_POST['action'] === 'signin') {
        $username = $_POST['username'];
        $password = $_POST['password'];
        $stmt = $conn->prepare("SELECT password, is_admin FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($hash, $is_admin);
        if ($stmt->fetch() && password_verify($password, $hash)) {
            $_SESSION['username'] = $username;
            $_SESSION['is_admin'] = $is_admin;
            // Debug: echo session id and cookie to help client-side troubleshooting
            $sid = session_id();
            $cookie = isset($_COOKIE[session_name()]) ? $_COOKIE[session_name()] : '(none)';
            echo ($is_admin ? "Log masuk sebagai admin." : "Log masuk berjaya.") . " SESSION_ID=" . $sid . " COOKIE=" . $cookie;
        } else {
            echo "Log masuk gagal. Sila semak nama pengguna dan kata laluan.";
        }
        $stmt->close();
        $conn->close();
        exit;
    }
    // Voting (only for logged-in users)
    if (isset($_POST['position']) && isset($_POST['student'])) {
        // Debug: include session id/cookie info when not logged in
        if (!isset($_SESSION['username'])) {
            $sid = session_id();
            $cookie = isset($_COOKIE[session_name()]) ? $_COOKIE[session_name()] : '(none)';
            echo "Anda mesti log masuk untuk mengundi. SESSION_ID=" . $sid . " COOKIE=" . $cookie;
            $conn->close();
            exit;
        }
        $position = $_POST['position'];
        $student = $_POST['student'];
        if (!in_array($position, $allowed)) {
            echo "Pilihan jawatan tidak sah.";
            $conn->close();
            exit;
        }
        if (isset($candidates[$position]) && !in_array($student, $candidates[$position])) {
            echo "Pilihan calon tidak sah.";
            $conn->close();
            exit;
        }
            // Enforce one vote per user per position using user_votes table and transaction
            $username = $_SESSION['username'];
            // Start transaction
            $conn->begin_transaction();
            try {
                // Check if user already voted for this position
                $chk = $conn->prepare("SELECT id FROM user_votes WHERE username = ? AND position = ?");
                $chk->bind_param("ss", $username, $position);
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

                // Record user's vote
                $ins = $conn->prepare("INSERT INTO user_votes (username, position, candidate) VALUES (?, ?, ?)");
                $ins->bind_param("sss", $username, $position, $student);
                if (!$ins->execute()) {
                    $err = $ins->error;
                    $ins->close();
                    $conn->rollback();
                    echo "DB error recording user vote: " . $err;
                    $conn->close();
                    exit;
                }
                $ins->close();

                // Insert or update aggregated votes table
                $stmt = $conn->prepare("INSERT INTO votes (position, candidate, count) VALUES (?, ?, 1)
                    ON DUPLICATE KEY UPDATE count = count + 1");
                $stmt->bind_param("ss", $position, $student);
                if (!$stmt->execute()) {
                    $err = $stmt->error;
                    $stmt->close();
                    $conn->rollback();
                    echo "DB error during insert: " . $err;
                    $conn->close();
                    exit;
                }
                $stmt->close();

                // Commit
                $conn->commit();

                // Get updated count
                $stmt = $conn->prepare("SELECT count FROM votes WHERE position = ? AND candidate = ?");
                $stmt->bind_param("ss", $position, $student);
                $stmt->execute();
                $stmt->bind_result($count);
                $stmt->fetch();
                $stmt->close();

                // Only reveal count to admins
                if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1) {
                    echo "Undian untuk $student ($position) telah direkodkan. Jumlah undian: " . $count;
                } else {
                    echo "Undian untuk $student ($position) telah direkodkan.";
                }
                $conn->close();
                exit;
            } catch (Exception $e) {
                $conn->rollback();
                echo "Exception during voting: " . $e->getMessage();
                $conn->close();
                exit;
            }
    }

    // Other POST actions...
}

// Admin endpoints (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    // Allow non-admin users to request their own votes
    if ($_GET['action'] === 'my_votes') {
        if (!isset($_SESSION['username'])) {
            header('Content-Type: application/json');
            echo json_encode([]);
            $conn->close();
            exit;
        }
        $username = $_SESSION['username'];
        $stmt = $conn->prepare("SELECT position FROM user_votes WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $res = $stmt->get_result();
        $positions = [];
        while ($row = $res->fetch_assoc()) {
            $positions[] = $row['position'];
        }
        header('Content-Type: application/json');
        echo json_encode($positions);
        $stmt->close();
        $conn->close();
        exit;
    }
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
        echo "Akses ditolak. Anda bukan admin.";
        $conn->close();
        exit;
    }
    if ($_GET['action'] === 'view_users') {
        $result = $conn->query("SELECT username, is_admin FROM users");
        echo "<h3>Senarai Pengguna</h3><ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['username']) . ($row['is_admin'] ? " (Admin)" : "") . "</li>";
        }
        echo "</ul>";
        $conn->close();
        exit;
    }
    if ($_GET['action'] === 'view_votes') {
        $result = $conn->query("SELECT position, candidate, count FROM votes ORDER BY position, candidate");
        echo "<h3>Senarai Undian</h3>";
        $currentPos = null;
        echo "<div>";
        while ($row = $result->fetch_assoc()) {
            if ($currentPos !== $row['position']) {
                if ($currentPos !== null) echo "</ul>";
                $currentPos = $row['position'];
                echo "<h4>" . htmlspecialchars($currentPos) . "</h4><ul>";
            }
            echo "<li>" . htmlspecialchars($row['candidate']) . ": " . $row['count'] . " undian</li>";
        }
        if ($currentPos !== null) echo "</ul>";
        echo "</div>";
        $conn->close();
        exit;
    }
}


// If accessed directly, show nothing or redirect
header('Location: index.html');
$conn->close();
exit;
?>