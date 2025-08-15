<?php
// index.php

$host = 'localhost';
$db = 'pengundian';
$user = 'root';
$pass = '';
$allowed = ['Pengerusi', 'Naib Pengerusi', 'Setiausaha', 'Bendahari'];

// Connect to MySQL server (no DB yet)
$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die('Gagal sambungan ke pelayan MySQL.');
}

// Create database if not exists
$conn->query("CREATE DATABASE IF NOT EXISTS $db");

// Select the database
$conn->select_db($db);

// Create table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student VARCHAR(50) NOT NULL,
    count INT NOT NULL DEFAULT 0,
    UNIQUE KEY(student)
)");

// Create users table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0
)");


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
    if (isset($_POST['student'])) {
        // Debug: include session id/cookie info when not logged in
        if (!isset($_SESSION['username'])) {
            $sid = session_id();
            $cookie = isset($_COOKIE[session_name()]) ? $_COOKIE[session_name()] : '(none)';
            echo "Anda mesti log masuk untuk mengundi. SESSION_ID=" . $sid . " COOKIE=" . $cookie;
            $conn->close();
            exit;
        }
        $student = $_POST['student'];
        if (!in_array($student, $allowed)) {
            echo "Pilihan tidak sah.";
            $conn->close();
            exit;
        }
        // Insert or update vote
        $stmt = $conn->prepare("INSERT INTO votes (student, count) VALUES (?, 1)
            ON DUPLICATE KEY UPDATE count = count + 1");
        $stmt->bind_param("s", $student);
        if (!$stmt->execute()) {
            echo "DB error during insert: " . $stmt->error;
            $stmt->close();
            $conn->close();
            exit;
        }
        // Get updated count
        $stmt = $conn->prepare("SELECT count FROM votes WHERE student = ?");
        $stmt->bind_param("s", $student);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        if ($count === null) {
            echo "DB error: could not retrieve count for " . htmlspecialchars($student);
            $stmt->close();
            $conn->close();
            exit;
        }
        $stmt->close();
        echo "Undian untuk $student telah direkodkan. Jumlah undian: " . $count;
        $conn->close();
        exit;
    }

    // Other POST actions...
}

// Admin endpoints (GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
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
        $result = $conn->query("SELECT student, count FROM votes");
        echo "<h3>Senarai Undian</h3><ul>";
        while ($row = $result->fetch_assoc()) {
            echo "<li>" . htmlspecialchars($row['student']) . ": " . $row['count'] . " undian</li>";
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