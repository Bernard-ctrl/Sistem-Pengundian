<?php
// index.php

$host = 'localhost';
$db = 'pengundian';
$user = 'root';
$pass = '';
$allowed = ['Ali', 'Siti', 'Ahmad'];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student'])) {
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
    $stmt->execute();

    // Get updated count
    $stmt = $conn->prepare("SELECT count FROM votes WHERE student = ?");
    $stmt->bind_param("s", $student);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();

    echo "Undian untuk $student telah direkodkan. Jumlah undian: " . $count;
    $conn->close();
    exit;
}

// If accessed directly, show nothing or redirect
header('Location: index.html');
$conn->close();
exit;
?>