<?php
// Variables de connexion
$host = "sql202.infinityfree.com"; // MySQL Hostname
$user = "if0_39714282"; // MySQL Username
$pass = "MonDomaine1234"; // même mot de passe que pour te connecter à InfinityFree
$dbname = "if0_39714282_epreuve_db"; // Nom exact de la base


// Connexion MySQL
$conn = mysqli_connect($host, $user, $pass);
if (!$conn) die("Erreur MySQL : " . mysqli_connect_error());
mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
mysqli_select_db($conn, $dbname);

// Dossier d’upload
$upload_dir = __DIR__ . '/uploads';

// Fonction sécurisée
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

if (!isset($_GET['id']) || !isset($_GET['type'])) {
    die("Paramètres manquants.");
}

$exam_id = intval($_GET['id']);
$type = ($_GET['type'] === 'correction') ? 'correction' : 'exam';

// Récupération du fichier
$stmt = mysqli_prepare($conn, "SELECT filename, correction_filename FROM exams WHERE id=? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $exam_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$exam = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$exam) {
    die("Épreuve introuvable.");
}

$file = ($type === 'correction') ? $exam['correction_filename'] : $exam['filename'];
$path = __DIR__ . "/uploads/" . $file;

if (!file_exists($path)) {
    die("Fichier introuvable.");
}

// Log du téléchargement
$user_id = $_SESSION['user_id'] ?? null;
$stmt = mysqli_prepare($conn, "INSERT INTO download_log (exam_id, user_id, download_type) VALUES (?, ?, ?)");
mysqli_stmt_bind_param($stmt, "iis", $exam_id, $user_id, $type);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

// Téléchargement du fichier
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($file) . '"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
?>
