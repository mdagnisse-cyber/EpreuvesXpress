
<?php
ob_start();
session_start();

/* ---------- INITIALISATION ---------- */
$action = $_GET['action'] ?? 'home'; // Défaut: 'home'
$action = in_array($action, [
    'home', 'login', 'register', 'logout', 
    'upload', 'search', 'detail',
    'admin_dashboard', 'admin_users',
    'admin_messages', 'admin_suggestions', 
    'delete_message', 'delete_suggestion'
]) ? $action : 'home'; // Sécurité: ne garder que les actions valides

/* ---------- CONFIG ---------- 
$host = "localhost"; // MySQL Hostname
$user = "root"; // MySQL Username
$pass = ""; // même mot de passe que pour te connecter à InfinityFree
$dbname = "epreuves_db"; // Nom exact de la base */
/* ---------- CONFIG ---------- */
$host = "sql202.infinityfree.com"; // MySQL Hostname
$user = "if0_39714282"; // MySQL Username
$pass = "MonDomaine1234"; // même mot de passe que pour te connecter à InfinityFree
$dbname = "if0_39714282_epreuve_db"; // Nom  de la base

$upload_dir = __DIR__ . '/uploads';
$allowed_ext = ['pdf','doc','docx','zip','jpg','jpeg','png'];
$max_upload_size = 8 * 1024 * 1024; // 8 Mo
$allowed_mimes = [
    'application/pdf',
    'image/jpeg',
    'image/png',
    'application/zip',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
/* ---------------------------- */

// CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function check_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helpers

function is_logged() { return isset($_SESSION['user_id']); }
// Helpers
function is_admin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function admin_only() {
    if (!is_admin()) {
        $_SESSION['flash_error'] = "Accès réservé aux administrateurs";
        header('Location: index.php');
        exit();
    }
}
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function alert($msg,$type='info'){ echo "<div class='alert {$type}'>".h($msg)."</div>"; }

// Connexion
$conn = mysqli_connect($host, $user, $pass);
if (!$conn) die("Erreur MySQL : " . mysqli_connect_error());
mysqli_set_charset($conn, 'utf8mb4');
mysqli_query($conn, "CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
mysqli_select_db($conn, $dbname);

// Création des tables
mysqli_query($conn, <<<SQL
CREATE TABLE IF NOT EXISTS schools (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) UNIQUE
) ENGINE=InnoDB
SQL
);
mysqli_query($conn, <<<SQL
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    school_id INT,
    name VARCHAR(150),
    UNIQUE(school_id,name),
    FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB
SQL
);
mysqli_query($conn, <<<SQL
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) UNIQUE
) ENGINE=InnoDB
SQL
);
mysqli_query($conn, <<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE,
  email VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  role ENUM('etudiant','admin') DEFAULT 'etudiant',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB
SQL
);
mysqli_query($conn, <<<SQL
CREATE TABLE IF NOT EXISTS exams (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  school_id INT NULL,
  department_id INT NULL,
  subject_id INT,
  title VARCHAR(150),
  year YEAR,
  description TEXT,
  filename VARCHAR(255),
  correction_filename VARCHAR(255) DEFAULT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL,
  FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
) ENGINE=InnoDB
SQL
);

// Initialisation de données de base
$r = mysqli_query($conn, "SELECT COUNT(*) as c FROM subjects");
$row = mysqli_fetch_assoc($r);
if ($row['c'] == 0) {
    mysqli_query($conn, "INSERT INTO subjects (name) VALUES 
        ('Mathématiques'),('Physique'),('Informatique'),('Chimie')");
}
$r2 = mysqli_query($conn, "SELECT COUNT(*) as c FROM schools");
$row2 = mysqli_fetch_assoc($r2);
if ($row2['c'] == 0) {
    mysqli_query($conn, "INSERT INTO schools (name) VALUES 
  ('FLLAC'), ('FADESP'), ('FAST'), ('ENA'), ('CHINOIS'), ('INSTIC')");

    $resf = mysqli_query($conn, "SELECT id FROM schools WHERE name='' LIMIT 1");
    if ($rf = mysqli_fetch_assoc($resf)) {
        $facId = $rf['id'];
        mysqli_query($conn, "INSERT IGNORE INTO departments (school_id,name) VALUES 
            ($facId,'Informatique'),($facId,'Mathématiques')");
    }
}

// Créer dossier d'uploads si nécessaire
if (!is_dir($upload_dir)) mkdir($upload_dir,0755,true);

/* ---------- ROUTAGE / AJAX ---------- */

$action = $_GET['action'] ?? 'home';

if ($action === 'fetch_departments') {
    header('Content-Type: application/json');
    $school_id = intval($_GET['school_id'] ?? 0);
    if (!$school_id) { echo json_encode([]); exit(); }
    $res = mysqli_query($conn, "SELECT id,name FROM departments WHERE school_id=$school_id ORDER BY name");
    $out = [];
    while ($d = mysqli_fetch_assoc($res)) $out[] = $d;
    echo json_encode($out);
    exit();
}
// Formulaire de contact
if ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $message = trim($_POST['message'] ?? '');

  if ($name && $email && $message) {
      $stmt = mysqli_prepare($conn, "INSERT INTO messages (name, email, message) VALUES (?, ?, ?)");
      mysqli_stmt_bind_param($stmt, "sss", $name, $email, $message);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);
      $_SESSION['flash_success'] = "Message envoyé avec succès.";
  } else {
      $_SESSION['flash_error'] = "Veuillez remplir tous les champs du formulaire de contact.";
  }
  header('Location: index.php?action=contact');
  exit();
}

// Formulaire de suggestion
if ($action === 'send_suggestion' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $suggestion = trim($_POST['suggestion'] ?? '');

  if ($suggestion) {
      $stmt = mysqli_prepare($conn, "INSERT INTO suggestions (name, suggestion) VALUES (?, ?)");
      mysqli_stmt_bind_param($stmt, "ss", $name, $suggestion);
      mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);
      $_SESSION['flash_success'] = "Suggestion envoyée avec succès.";
  } else {
      $_SESSION['flash_error'] = "La suggestion ne peut pas être vide.";
  }
  header('Location: index.php?action=suggest');
  exit();
}
/* ---------- FORMULAIRES ---------- */
// Inscription
if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !check_csrf($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Jeton CSRF invalide."; 
        header('Location: index.php?action=register'); 
        exit();
    }

    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $errors           = [];

    // Vérification des champs vides
    if (!$username || !$email || !$password || !$confirm_password) {
        $errors[] = "Tous les champs sont requis.";
    }

    // Email valide
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide.";
    }

    // Longueur minimale du mot de passe
    if (strlen($password) < 8) {
        $errors[] = "Le mot de passe doit contenir au moins 8 caractères.";
    }

    // Confirmation du mot de passe
    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }

    // Vérifier si username ou email déjà pris
    $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email=? OR username=? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ss", $email, $username);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    if (mysqli_stmt_num_rows($stmt)) {
        $errors[] = "Nom d'utilisateur ou email déjà utilisé.";
    }
    mysqli_stmt_close($stmt);

    // Si pas d'erreurs → insertion
    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($conn, "INSERT INTO users (username,email,password) VALUES (?,?,?)");
        mysqli_stmt_bind_param($stmt, "sss", $username, $email, $hash);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $_SESSION['user_id'] = mysqli_insert_id($conn);
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'etudiant';

        header('Location: index.php');
        exit();
    } else {
        $_SESSION['flash_error'] = implode(" ", $errors);
        header('Location: index.php?action=register');
        exit();
    }
}

// Connexion
if ($action === 'login' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!isset($_POST['csrf_token']) || !check_csrf($_POST['csrf_token'])) {
        $_SESSION['flash_error']="Jeton CSRF invalide."; 
        header('Location: index.php?action=login'); exit();
    }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $err = '';
    if (!$email || !$password) $err = "Tous les champs sont requis.";
    else {
        $stmt = mysqli_prepare($conn, "SELECT id, username, password, role FROM users WHERE email=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $uid, $uname, $hash, $role);
        if (mysqli_stmt_fetch($stmt)) {
            if (password_verify($password, $hash)) {
                $_SESSION['user_id']=$uid;
                $_SESSION['username']=$uname;
                $_SESSION['role']=$role;
                header('Location: index.php');
                exit();
            } else $err="Mot de passe incorrect.";
        } else $err="Utilisateur introuvable.";
        mysqli_stmt_close($stmt);
    }
    if ($err) {
        $_SESSION['flash_error']=$err;
        header('Location: index.php?action=login'); exit();
    }
}

// Déconnexion
if ($action === 'logout') {
    session_unset();
    session_destroy();
    header('Location: index.php');
    exit();
}

// Upload épreuve
if ($action === 'upload' && $_SERVER['REQUEST_METHOD']==='POST') {
    if (!is_logged()) { $_SESSION['flash_error']="Connecte-toi d'abord."; header('Location: index.php?action=login'); exit(); }
    if (!isset($_POST['csrf_token']) || !check_csrf($_POST['csrf_token'])) {
        $_SESSION['flash_error']="Jeton CSRF invalide."; header('Location: index.php?action=upload'); exit();
    }
    $title = trim($_POST['title'] ?? '');
    $school_id = intval($_POST['school_id'] ?? 0);
    $department_id = intval($_POST['department_id'] ?? 0);
    $subject_id = intval($_POST['subject_id'] ?? 0);
    $year = intval($_POST['year'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $errors = [];
    if (!$title) $errors[]="Titre requis.";
    if (!$school_id) $errors[]="École requise.";
    if (!$department_id) $errors[]="Département requis.";
    if (!$subject_id) $errors[]="Matière requise.";
    if (!isset($_FILES['exam']) || $_FILES['exam']['error']!==UPLOAD_ERR_OK) $errors[]="Épreuve obligatoire.";
    else {
        $ext = strtolower(pathinfo($_FILES['exam']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) $errors[]="Type d'épreuve non autorisé.";
        if ($_FILES['exam']['size'] > $max_upload_size) $errors[]="Épreuve trop volumineuse (max 8 Mo).";
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($_FILES['exam']['tmp_name']);
        if (!in_array($mime, $allowed_mimes)) $errors[]="Type MIME de l'épreuve non autorisé.";
    }
    $corr_name = null;
    if (isset($_FILES['correction']) && $_FILES['correction']['error']===UPLOAD_ERR_OK) {
        $ext2 = strtolower(pathinfo($_FILES['correction']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext2, $allowed_ext)) $errors[]="Type de correction non autorisé.";
        if ($_FILES['correction']['size'] > $max_upload_size) $errors[]="Correction trop volumineuse.";
        $finfo2 = new finfo(FILEINFO_MIME_TYPE);
        $mime2 = $finfo2->file($_FILES['correction']['tmp_name']);
        if (!in_array($mime2, $allowed_mimes)) $errors[]="Type MIME de la correction non autorisé.";
    }

    if (empty($errors)) {
        $uniq = uniqid() . "_" . basename($_FILES['exam']['name']);
        move_uploaded_file($_FILES['exam']['tmp_name'], "$upload_dir/$uniq");
        if (isset($_FILES['correction']) && $_FILES['correction']['error']===UPLOAD_ERR_OK) {
            $uniq2 = uniqid() . "_" . basename($_FILES['correction']['name']);
            move_uploaded_file($_FILES['correction']['tmp_name'], "$upload_dir/$uniq2");
            $corr_name = $uniq2;
        }
        $stmt = mysqli_prepare($conn, "INSERT INTO exams (user_id, school_id, department_id, subject_id, title, year, description, filename, correction_filename) VALUES (?,?,?,?,?,?,?,?,?)");
        $uid = $_SESSION['user_id'];
        mysqli_stmt_bind_param($stmt, "iiiisssss", $uid, $school_id, $department_id, $subject_id, $title, $year, $description, $uniq, $corr_name);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $_SESSION['flash_success']="Épreuve déposée.";
        header('Location: index.php');
        exit();
    } else {
        $_SESSION['flash_error'] = implode(" ", $errors);
        header('Location: index.php?action=upload'); exit();
    }
}

/* ---------- FILTRAGE / LISTING ---------- */
$filter_subject = isset($_GET['subject']) ? intval($_GET['subject']) : 0;
$filter_year = isset($_GET['year']) ? intval($_GET['year']) : 0;
$filter_school = isset($_GET['school']) ? intval($_GET['school']) : 0;
$filter_department = isset($_GET['department']) ? intval($_GET['department']) : 0;
$keyword = isset($_GET['q']) ? mysqli_real_escape_string($conn, $_GET['q']) : '';
$where = [];
if ($filter_school) $where[] = "e.school_id = $filter_school";
if ($filter_department) $where[] = "e.department_id = $filter_department";
if ($filter_subject) $where[] = "e.subject_id = $filter_subject";
if ($filter_year) $where[] = "e.year = $filter_year";
if ($keyword) $where[] = "(e.title LIKE '%$keyword%' OR e.description LIKE '%$keyword%')";
$where_sql = $where ? "WHERE " . implode(' AND ', $where) : "";

$exams = mysqli_query($conn, "SELECT 
    e.id, e.title, e.year, e.uploaded_at, e.description, 
    e.filename, e.correction_filename,
    s.name AS subject_name, 
    u.username, 
    sc.name AS school_name, 
    d.name AS department_name 
FROM exams e
JOIN subjects s ON e.subject_id=s.id
JOIN users u ON e.user_id=u.id
LEFT JOIN schools sc ON e.school_id=sc.id
LEFT JOIN departments d ON e.department_id=d.id
$where_sql ORDER BY e.uploaded_at DESC");


$schools = mysqli_query($conn, "SELECT * FROM schools ORDER BY name");
$subjects_list = mysqli_query($conn, "SELECT * FROM subjects ORDER BY name");

/* ---------- HTML / AFFICHAGE ---------- */
?><!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>EpreuveXpress</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
   <style>
    select[name="role"] {
    padding: 5px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
}

select[name="role"]:hover {
    border-color: var(--green);
}

table {
    width: 100%;
    margin-top: 20px;
    border-collapse: collapse;
}

th {
    background: #f5f5f5;
    text-align: left;
    padding: 10px;
    border-bottom: 2px solid #ddd;
}

td {
    padding: 10px;
    border-bottom: 1px solid #eee;
}

tr:hover {
    background: #f9f9f9;
}
</style>
  <style>
    :root {
        --green:#2a9d8f;
        --green-dark:#1f7f6f;
        --radius:8px;
        --shadow:0 12px 40px rgba(0,0,0,0.08);
        --transition:.25s cubic-bezier(.4,.2,.2,1);
        --bg: linear-gradient(135deg, #e0f7ff 0%, #ffffff 60%);
    }
    *{box-sizing:border-box;}
    body{
        margin:0;
        font-family: system-ui,-apple-system,BlinkMacSystemFont,sans-serif;
        background: var(--bg);
        color:#1f2d33;
        -webkit-font-smoothing:antialiased;
    }
    a{color:var(--green);text-decoration:none;}
    a:hover{opacity:.85;}
    header{
        background: white;
        padding:14px 20px;
        display:flex;
        flex-wrap:wrap;
        align-items:center;
        justify-content:space-between;
        gap:8px;
        position:sticky;
        top:0;
        z-index:10;
        box-shadow:0 6px 28px rgba(0,0,0,0.05);
    }
    .logo {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  font-weight: 700;
  color: var(--green-dark);
}

.logo-top {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 24px;
}

.logo-top i {
  font-size: 28px;
}

.logo-top .prefix {
  color: var(--green);
}

.logo-bottom {
  font-size: 12px;
  color: #666;
  margin-top: 2px;
}

    nav{
        display:flex;
        flex-wrap:wrap;
        gap:12px;
        align-items:center;
    }
    nav a{
        padding:8px 14px;
        border-radius:6px;
        display:flex;
        align-items:center;
        gap:6px;
        font-size:14px;
        font-weight:500;
        background: rgba(42,157,143,.08);
        border:1px solid transparent;
        transition: var(--transition);
    }
    nav a.active, nav a:hover{
        background: var(--green);
        color:white;
        box-shadow:0 12px 30px rgba(42,157,143,.35);
        transform:translateY(-1px);
    }
    main{max-width:1100px;margin:30px auto;padding:0 12px;}
    .badge{background:var(--green);color:white;padding:4px 12px;border-radius:999px;font-size:12px;display:inline-block;}
    .card{
        background:white;
        border-radius:var(--radius);
        padding:18px 22px;
        margin-bottom:20px;
        position:relative;
        overflow:hidden;
        box-shadow:var(--shadow);
        transition:var(--transition);
    }
    .card:hover{transform:translateY(-3px);}
    .flex{display:flex;gap:14px;flex-wrap:wrap;}
    .grow{flex:1;}
    .pill{background:#f0f9f6;border:1px solid var(--green);padding:6px 16px;border-radius:999px;font-size:12px;display:inline-block;margin-right:8px;}
    .search-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 24px;
    align-items: stretch;
    position: relative;
}

.search-bar input, 
.search-bar select {
    padding: 14px 16px 14px 42px;
    border: 2px solid #e1e5eb;
    border-radius: 8px;
    font-size: 15px;
    flex: 1 1 200px;
    min-width: 200px;
    transition: var(--transition);
    background-color: #f8fafc;
    box-shadow: 0 2px 4px rgba(0,0,0,0.03);
    color: #2d3748;
}

.search-bar input:focus, 
.search-bar select:focus {
    outline: none;
    border-color: var(--green);
    box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.2);
    background-color: white;
}

.search-bar button {
    background: var(--green);
    border: none;
    color: white;
    padding: 0 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 6px rgba(42, 157, 143, 0.2);
}

.search-bar button:hover {
    background: var(--green-dark);
    transform: translateY(-1px);
    box-shadow: 0 6px 12px rgba(42, 157, 143, 0.25);
}

.search-bar button:active {
    transform: translateY(0);
}

/* Icônes intégrées */
.search-input-wrapper {
    position: relative;
    flex: 1 1 300px;
}

.search-input-wrapper::before {
    content: "\f002";
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    position: absolute;
    left: 16px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}

.search-bar input {
    padding-left: 42px;
}

/* Style des selects */
.search-bar select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%236b7280'%3E%3Cpath d='M7 10l5 5 5-5z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 18px;
    padding-right: 36px;
}

/* Responsive */
@media (max-width: 768px) {
    .search-bar {
        gap: 8px;
    }
    
    .search-bar input, 
    .search-bar select {
        min-width: 100%;
        flex: 1 1 100%;
    }
    
    .search-bar button {
        width: 100%;
        justify-content: center;
        padding: 14px;
    }
}
    .small{font-size:13px;}
    .actions{margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;}
    .btn{
        background: var(--green);
        border:none;
        padding:10px 16px;
        color:white;
        border-radius:6px;
        cursor:pointer;
        font-weight:600;
        display:inline-flex;
        align-items:center;
        gap:8px;
        transition:var(--transition);
        text-decoration:none;
        font-size:14px;
    }
    .btn.secondary{
        background: transparent;
        color: var(--green-dark);
        border:1px solid var(--green);
    }
    .btn:hover{filter:brightness(1.05);}
    .form-card{max-width:700px;margin:auto;}
    .form-group{margin-bottom:16px;}
    .form-group label{display:block;font-weight:600;margin-bottom:6px;}
    .form-group input, .form-group select, .form-group textarea{
        width:100%;
        padding:12px 14px;
        border:1px solid #d5dbe0;
        border-radius:6px;
        font-size:14px;
        resize:vertical;
        transition:var(--transition);
    }
    .pill-soft{background:rgba(42,157,143,.1);color: var(--green-dark);padding:6px 14px;border-radius:999px;font-size:12px;display:inline-flex;gap:6px;}
    .alert{padding:14px 18px;border-radius:6px;margin-bottom:18px;position:relative;font-size:14px;}
    .alert.info{background:#e6f7f5;border:1px solid var(--green);color:#0f4941;}
    .alert.success{background:#daf6e9;border:1px solid #2a9d8f;color:#085347;}
    .alert.error{background:#ffe6e6;border:1px solid #e05757;color:#8a1f1f;}
    .footer{margin-top:60px;padding:30px 14px;text-align:center;font-size:14px;color:#555;}
    .tiny{font-size:12px;color:#666;margin-top:4px;}
   .tag {
    background: #e6f7f5;
    color: var(--green-dark);
    padding: 6px 12px;
    border-radius: 16px; /* Arrondi plus prononcé */
    font-size: 12px;
    margin: 0 6px 8px 0; /* Marge en bas ajoutée */
    display: inline-block;
    line-height: 1.4; /* Meilleure hauteur de ligne */
    box-shadow: 0 1px 2px rgba(0,0,0,0.05); /* Ombre subtile */
    border: 1px solid rgba(42, 157, 143, 0.2); /* Bordure légère */
    transition: all 0.2s ease;
    word-break: break-word; /* Gestion des mots longs */
    max-width: 100%; /* Empêche les débordements */
    white-space: normal; /* Permet les retours à ligne */
}
    @media (max-width:900px){
        .flex{flex-direction:column;}
        nav{justify-content:center;}
    }
  </style>
</head>
<body>

<header>
<div class="logo">
  <div class="logo-top">
    <i class="fas fa-book-open"></i>
    <span><span class="prefix">E</span><span>X</span></span>
  </div>
  <div class="logo-bottom">EpreuveXpress</div>
</div>
<div class="admin-panel">
    <nav class="icon-nav">
        <div class="nav-section">
            <a href="index.php?action=home" class="nav-icon <?= $action === 'home' ? 'active' : '' ?>" data-tooltip="Accueil">
                <i class="fas fa-house"></i>
            </a>
        </div>
        
        <?php if (!is_logged()): ?>
            <div class="nav-section auth-section">
                <a href="index.php?action=login" class="nav-icon <?= $action === 'login' ? 'active' : '' ?>" data-tooltip="Connexion">
                    <i class="fas fa-right-to-bracket"></i>
                </a>
                <a href="index.php?action=register" class="nav-icon <?= $action === 'register' ? 'active' : '' ?>" data-tooltip="Inscription">
                    <i class="fas fa-user-plus"></i>
                </a>
            </div>
        <?php else: ?>
            <div class="nav-section main-actions">
                <a href="index.php?action=upload" class="nav-icon <?= $action === 'upload' ? 'active' : '' ?>" data-tooltip="Déposer">
                    <i class="fas fa-upload"></i>
                </a>
                <a href="index.php?action=search" class="nav-icon <?= $action === 'search' ? 'active' : '' ?>" data-tooltip="Rechercher">
                    <i class="fas fa-magnifying-glass"></i>
                </a>
            </div>
            
            <div class="nav-section user-section">
                <a href="index.php?action=logout" class="nav-icon" data-tooltip="Déconnexion">
                    <i class="fas fa-right-from-bracket"></i>
                </a>
                <span class="nav-icon user-pill" data-tooltip="Mon compte">
                    <i class="fas fa-user-circle"></i>
                    <span class="username"><?= h($_SESSION['username']) ?></span>
                </span>
            </div>
        <?php endif; ?>
    </nav>
</div>

<style>
:root {
    --green: #2a9d8f;
    --green-dark: #1d7a6b;
    --nav-bg: #f8f9fa;
    
}

.admin-panel {
    background: var(--nav-bg);
    padding: 10px 15px;
    border-radius: 10px;
    margin-bottom: 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    overflow-x: auto; /* Permet le défilement horizontal si nécessaire */
    -webkit-overflow-scrolling: touch; /* Scroll fluide sur iOS */
    white-space: nowrap; /* Empêche le retour à la ligne */
}

.icon-nav {
    display: inline-flex; /* Alignement horizontal forcé */
    min-width: 100%; /* S'étend sur toute la largeur */
    gap: 20px; /* Espacement entre les sections */
}

.nav-section {
    display: inline-flex; /* Alignement horizontal des sections */
    gap: 15px; /* Espacement entre les icônes */
    align-items: center;
}

.nav-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #555;
    background: rgba(42, 157, 143, 0.08);
    border: 1px solid transparent;
    transition: all 0.3s ease;
    flex-shrink: 0; /* Empêche le rétrécissement */
}

/* Tooltip */
.nav-icon::after {
    content: attr(data-tooltip);
    position: absolute;
    bottom: -35px;
    left: 50%;
    transform: translateX(-50%);
    background: #333;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
    white-space: nowrap;
}

.nav-icon:hover::after {
    opacity: 1;
}

/* États interactifs */
.nav-icon:hover {
    background: var(--green);
    color: white;
    transform: translateY(-2px);
}

.nav-icon.active {
    background: var(--green);
    color: white;
}

.user-pill {
    padding: 0 12px;
    border-radius: 20px;
    background: rgba(42, 157, 143, 0.15);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.username {
    font-size: 14px;
}

/* Adaptation mobile */
@media (max-width: 767px) {
    .admin-panel {
        padding: 10px;
    }
    
    .icon-nav {
        gap: 10px;
    }
    
    .nav-section {
        gap: 10px;
    }
    
    .nav-icon {
        width: 40px;
        height: 40px;
    }
    
    .user-pill .username {
        display: none; /* Cache le nom d'utilisateur en mobile */
    }
    
    .user-pill {
        padding: 0;
        width: 40px;
        justify-content: center;
    }
}

/* Version desktop */
@media (min-width: 768px) {
    .admin-panel {
        overflow-x: visible;
    }
    
    .icon-nav {
        display: flex;
        justify-content: space-between;
        flex-wrap: nowrap;
    }
    
    .nav-section:not(:last-child)::after {
        content: "";
        height: 30px;
        width: 1px;
        background: rgba(0,0,0,0.1);
        margin: 0 10px;
    }
}
</style>
</header>
<div class='admin-links'>
<?php if (isset($action)): ?>
    <?php if (is_admin()): ?>
        <div class="admin-panel">
    <div class="admin-badge" id="adminToggle">
        <i class="fas fa-crown"></i> ADMINISTRATEUR <i class="fas fa-chevron-down" id="adminChevron"></i>
    </div>
    
    <nav class="admin-nav" id="adminNav">
        <div class="admin-nav-section">
            <div class="admin-nav-header">
                <i class="fas fa-tachometer-alt"></i> Tableau de bord
            </div>
            <div class="admin-nav-links">
                <a href="index.php?action=admin_dashboard" class="<?= $action === 'admin_dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-chart-line"></i> Aperçu général
                </a>
                <a href="index.php?action=admin_stats" class="<?= $action === 'admin_stats' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> Statistiques
                </a>
            </div>
        </div>
        
        <div class="admin-nav-section">
            <div class="admin-nav-header">
                <i class="fas fa-database"></i> Gestion des données
            </div>
            <div class="admin-nav-links">
                <a href="index.php?action=admin_users" class="<?= $action === 'admin_users' ? 'active' : '' ?>">
                    <i class="fas fa-users"></i> Utilisateurs
                </a>
                <a href="index.php?action=admin_exams" class="<?= $action === 'admin_exams' ? 'active' : '' ?>">
                    <i class="fas fa-file-alt"></i> Épreuves
                </a>
            </div>
        </div>
        
        <div class="admin-nav-section">
            <div class="admin-nav-header">
                <i class="fas fa-inbox"></i> Communication
            </div>
            <div class="admin-nav-links">
                <a href="index.php?action=admin_messages" class="<?= $action === 'admin_messages' ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i> Messages
                </a>
                <a href="index.php?action=admin_suggestions" class="<?= $action === 'admin_suggestions' ? 'active' : '' ?>">
                    <i class="fas fa-lightbulb"></i> Suggestions
                </a>
            </div>
        </div>
    </nav>
</div>

<style>
.admin-panel {
    background: #f8f9fa;
    border-radius: 10px;
    margin: 20px 0;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.admin-badge {
    background: linear-gradient(135deg, gold, #ffd700);
    color: #000;
    padding: 10px 15px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
}

.admin-badge i {
    transition: transform 0.3s ease;
}

.admin-nav {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.admin-nav.open {
    max-height: 1000px;
}

.admin-nav-section {
    border-bottom: 1px solid #e0e0e0;
}

.admin-nav-section:last-child {
    border-bottom: none;
}

.admin-nav-header {
    padding: 12px 15px;
    font-weight: 600;
    color: #555;
    display: flex;
    align-items: center;
    gap: 10px;
    background: #f0f0f0;
}

.admin-nav-links {
    display: flex;
    flex-direction: column;
    padding: 5px 0;
}

.admin-nav-links a {
    padding: 10px 15px 10px 40px;
    color: #333;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s;
    position: relative;
}

.admin-nav-links a:before {
    content: "";
    position: absolute;
    left: 25px;
    top: 50%;
    transform: translateY(-50%);
    width: 6px;
    height: 6px;
    background: #aaa;
    border-radius: 50%;
}

.admin-nav-links a:hover {
    background: rgba(42, 157, 143, 0.1);
    color: var(--green-dark);
}

.admin-nav-links a.active {
    background: rgba(42, 157, 143, 0.15);
    color: var(--green-dark);
    font-weight: 500;
}

.admin-nav-links a.active:before {
    background: var(--green);
}

.admin-nav-links i {
    width: 20px;
    text-align: center;
}

@media (min-width: 768px) {
    .admin-panel {
        position: sticky;
        top: 20px;
    }
    
    .admin-badge {
        cursor: default;
    }
    
    .admin-nav {
        max-height: none !important;
    }
    
    #adminChevron {
        display: none;
    }
}
</style>

<script>
// Pour mobile : ouverture/fermeture du menu
document.getElementById('adminToggle').addEventListener('click', function() {
    if (window.innerWidth < 768) {
        const nav = document.getElementById('adminNav');
        const chevron = document.getElementById('adminChevron');
        
        nav.classList.toggle('open');
        chevron.style.transform = nav.classList.contains('open') ? 'rotate(180deg)' : 'rotate(0)';
    }
});

// Fermer le menu si on clique ailleurs (mobile)
document.addEventListener('click', function(e) {
    if (window.innerWidth < 768 && !e.target.closest('.admin-panel')) {
        document.getElementById('adminNav').classList.remove('open');
        document.getElementById('adminChevron').style.transform = 'rotate(0)';
    }
});
</script>
    <?php endif; ?>
<?php endif; ?>
</div>

<style>
.admin-links {
    text-align: center;
    margin: 20px 0;
    font-family: 'Segoe UI', Roboto, sans-serif;
}

.admin-panel {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    display: inline-block;
    text-align: left;
}

.admin-badge {
  text-align: right;
    background: linear-gradient(135deg, gold, #ffd700);
    color: #000;
    padding: 6px 12px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 0.85rem;
    display: inline-block;
    margin-bottom: 10px;
    box-shadow: 0 2px 3px rgba(0,0,0,0.1);
}

.admin-nav {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.admin-nav a {
    color: #495057;
    text-decoration: none;
    padding: 8px 15px;
    border-radius: 5px;
    transition: all 0.3s ease;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 8px;
    background: #e9ecef;
}

.admin-nav a:hover {
    background: #dee2e6;
    transform: translateY(-2px);
}

.admin-nav a.active {
    background: #0d6efd;
    color: white;
    box-shadow: 0 2px 5px rgba(13, 110, 253, 0.3);
}

.admin-nav a i {
    font-size: 0.9rem;
}
</style>
<style>
  .tot{
    text-align: center;
  }
</style>
<main>
  <div style="max-width:1100px;margin:0 auto;padding:10px;">

    <?php
    if (!empty($_SESSION['flash_error'])) { alert($_SESSION['flash_error'],'error'); unset($_SESSION['flash_error']); }
    if (!empty($_SESSION['flash_success'])) { alert($_SESSION['flash_success'],'success'); unset($_SESSION['flash_success']); }
    ?>
<?php 
// ---- Dépôt multiple (admin uniquement) ----
if ($action === 'multi_upload_process' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !check_csrf($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Jeton CSRF invalide.";
        header('Location: index.php?action=multi_upload');
        exit();
    }
    if (!is_logged() || $_SESSION['role'] !== 'admin') {
        $_SESSION['flash_error'] = "Accès refusé.";
        header('Location: index.php');
        exit();
    }

    foreach ($_POST['title'] as $i => $title) {
        $title = trim($title);
        if ($title === '') continue;

        $school_id     = intval($_POST['school_id'][$i] ?? 0);
        $department_id = intval($_POST['department_id'][$i] ?? 0);
        $subject_id    = intval($_POST['subject_id'][$i] ?? 0);
        $year          = intval($_POST['year'][$i] ?? 0);
        $description   = trim($_POST['description'][$i] ?? '');

        // Upload épreuve
        $exam_filename = '';
        if (!empty($_FILES['exam']['name'][$i])) {
            $tmp = $_FILES['exam']['tmp_name'][$i];
            $name = basename($_FILES['exam']['name'][$i]);
            $dest = $upload_dir . '/' . uniqid() . '_' . $name;
            if (move_uploaded_file($tmp, $dest)) {
                $exam_filename = basename($dest);
            }
        }

        // Upload correction
        $correction_filename = '';
        if (!empty($_FILES['correction']['name'][$i])) {
            $tmp = $_FILES['correction']['tmp_name'][$i];
            $name = basename($_FILES['correction']['name'][$i]);
            $dest = $upload_dir . '/' . uniqid() . '_' . $name;
            if (move_uploaded_file($tmp, $dest)) {
                $correction_filename = basename($dest);
            }
        }

        // Insertion en base
        $stmt = mysqli_prepare($conn, "INSERT INTO exams (user_id, school_id, department_id, subject_id, title, year, description, filename, correction_filename) VALUES (?,?,?,?,?,?,?,?,?)");
        mysqli_stmt_bind_param($stmt, "iiiisisss", $_SESSION['user_id'], $school_id, $department_id, $subject_id, $title, $year, $description, $exam_filename, $correction_filename);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    $_SESSION['flash_success'] = "Épreuves ajoutées avec succès.";
    header('Location: index.php?action=search');
    exit();
}

 if ($action === 'register'): ?>
<div class="card form-card">
  <h2>Inscription</h2>
  <p>Crée ton compte</p>

  <form method="post" action="index.php?action=register">
    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">

    <div class="form-group">
      <label>Nom d'utilisateur</label>
      <input type="text" name="username" required>
    </div>

    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required>
    </div>

    <div class="form-group" style="position: relative;">
      <label>Mot de passe <span style="color:red;">(min. 8 caractères)</span></label>
      <input type="password" id="password" name="password" minlength="8" required style="padding-right: 35px;">
      <i class="fas fa-eye" id="togglePassword" 
         style="position: absolute; right: 10px; top: 38px; cursor: pointer; color: gray;"></i>
    </div>

    <div class="form-group" style="position: relative;">
      <label>Confirmer le mot de passe</label>
      <input type="password" id="confirm_password" name="confirm_password" minlength="8" required style="padding-right: 35px;">
      <i class="fas fa-eye" id="toggleConfirmPassword" 
         style="position: absolute; right: 10px; top: 38px; cursor: pointer; color: gray;"></i>
    </div>

    <button class="btn" type="submit"><i class="fas fa-user-plus"></i> S'inscrire</button>
  </form>

  <p class="tiny">Tu as déjà un compte ? <a href="index.php?action=login">Connexion</a></p>
</div>

<script>
// Afficher / masquer mot de passe principal
document.getElementById("togglePassword").addEventListener("click", function () {
  let pwd = document.getElementById("password");
  this.classList.toggle("fa-eye-slash");
  pwd.type = (pwd.type === "password") ? "text" : "password";
});

// Afficher / masquer mot de passe de confirmation
document.getElementById("toggleConfirmPassword").addEventListener("click", function () {
  let pwd = document.getElementById("confirm_password");
  this.classList.toggle("fa-eye-slash");
  pwd.type = (pwd.type === "password") ? "text" : "password";
});
</script>
     Voici le code complet et unifié pour votre page d'accueil, intégrant toutes les fonctionnalités demandées dans un design moderne et organisé :

```html
<?php elseif ($action === 'home'): ?>
<div class="home-container">
  <!-- Hero Section -->
  <section class="hero-section">
    <h1>📚 EpreuveXpress</h1>
    <p class="subtitle">La plateforme collaborative des ressources académiques</p>
    <div class="action-buttons">
      <a href="index.php?action=search" class="main-btn"><i class="fas fa-search"></i> Rechercher</a>
      <a href="index.php?action=upload" class="main-btn outline"><i class="fas fa-upload"></i> Partager</a>
    </div>
  </section>

  <!-- Value Cards -->
<section class="value-cards">
  <div class="value-card">
    <i class="fas fa-book-open"></i>
    <h3>Banque d'Épreuves</h3>
    <p>Accédez à des examens antérieurs avec leurs corrigés détaillés pour mieux vous préparer</p>
  </div>
  <div class="value-card">
    <i class="fas fa-filter"></i>
    <h3>Recherche Avancée</h3>
    <p>Trouvez rapidement ce qu'il vous faut grâce à nos filtres par filière, professeur et année</p>
  </div>
  <div class="value-card">
    <i class="fas fa-shield-alt"></i>
    <h3>Espace Sécurisé</h3>
    <p>Partagez vos documents en toute confiance avec un système de vérification des contenus</p>
  </div>
</section>

  <!-- Navigation Tab -->
  <div class="content-navigation">
    <button class="nav-tab active" onclick="showContent('about')">À Propos</button>
    <button class="nav-tab" onclick="showContent('contact')">Contact</button>
    <button class="nav-tab" onclick="showContent('support')">Soutenir</button>
    <button class="nav-tab" onclick="showContent('guide')">Guide</button>
  </div>

  <!-- About Content -->
  <div id="about-content" class="content-section" style="display:block;">
    <div class="about-section">
      <h3>Notre Mission</h3>
      <p><strong>EpreuveXpress</strong> facilite l'accès aux épreuves et corrigés grâce à une bibliothèque numérique collaborative et gratuite.</p>
      <p>Enseignants, étudiants et diplômés peuvent contribuer à enrichir cette base de connaissances utile à tous.</p>
      
      <h3>Pourquoi nous utiliser ?</h3>
      <ul class="benefits-list">
        <li><i class="fas fa-check-circle"></i> Accès facile aux épreuves classées par matière</li>
        <li><i class="fas fa-check-circle"></i> Recherche intelligente et filtres avancés</li>
        <li><i class="fas fa-check-circle"></i> Plateforme 100% gratuite et sans publicité</li>
        <li><i class="fas fa-check-circle"></i> Contribuez et aidez la communauté étudiante</li>
      </ul>
    </div>
  </div>

  <!-- Contact Content -->
  <div id="contact-content" class="content-section">
    <div class="contact-grid">
      <div class="contact-form">
        <h3><i class="fas fa-envelope"></i> Nous contacter</h3>
        <form method="post" action="index.php?action=send_message">
          <input type="text" name="name" placeholder="Votre nom" required>
          <input type="email" name="email" placeholder="Votre email" required>
          <textarea name="message" placeholder="Votre message" required></textarea>
          <button type="submit" class="main-btn"><i class="fas fa-paper-plane"></i> Envoyer</button>
        </form>
      </div>
      
      <div class="suggestions-form">
        <h3><i class="fas fa-lightbulb"></i> Suggestions</h3>
        <form method="post" action="index.php?action=send_suggestion">
          <textarea name="suggestion" placeholder="Vos idées pour améliorer la plateforme..." required></textarea>
          <button type="submit" class="main-btn"><i class="fas fa-plus-circle"></i> Soumettre</button>
        </form>
      </div>
    </div>
  </div>

  <!-- Support Content -->
  <div id="support-content" class="content-section">
    <div class="support-grid">
      <div class="donation-section">
        <h3><i class="fas fa-hand-holding-heart"></i> Soutenir le projet</h3>
        <p>Votre soutien nous aide à maintenir et améliorer la plateforme :</p>
        
        <div class="donation-methods">
          <div class="method">
            <i class="fas fa-mobile-alt"></i>
            <div>
              <span>Mobile Money</span>
              <strong>+229 01 53 94 49 63</strong>
            </div>
          </div>
          
          <div class="method">
            <i class="fas fa-money-bill-wave"></i>
            <div>
              <span>Flooz</span>
              <strong>+229 01 45 51 81 80</strong>
            </div>
          </div>
          
          <div class="method">
            <i class="fas fa-user-tie"></i>
            <div>
              <span>Bénéficiaire</span>
              <strong>EpreuveXpress</strong>
            </div>
          </div>
        </div>
      </div>
        <div class="social-section">
    <h3><i class="fas fa-share-alt"></i> Partager</h3>
    <p>Aidez-nous en parlant de la plateforme autour de vous :</p>
    
    <div class="social-buttons">
        <a href="https://wa.me/?text=Découvrez%20EpreuveXpress%20-%20La%20plateforme%20collaborative%20des%20épreuves%20académiques%20:%20https://EpreuveXpress.wuaze.com" 
           class="social-btn whatsapp" target="_blank">
            <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=https://EpreuveXpress.wuaze.com" 
           class="social-btn facebook" target="_blank">
            <i class="fab fa-facebook-f"></i> Facebook
        </a>
        <button class="social-btn copy-link" onclick="copyToClipboard('https://EpreuveXpress.wuaze.com')">
            <i class="fas fa-link"></i> Copier le lien
        </button>
    </div>
    <p id="copy-notification" class="copy-notification"></p>
</div>

<script>
// Fonction pour copier le lien de production
function copyToClipboard(url) {
    navigator.clipboard.writeText(url).then(() => {
        const notification = document.getElementById('copy-notification');
        notification.textContent = 'Lien copié avec succès !';
        notification.classList.add('show');
        
        setTimeout(() => {
            notification.classList.remove('show');
        }, 3000);
    }).catch(err => {
        console.error('Erreur lors de la copie : ', err);
        alert('Impossible de copier le lien, veuillez le sélectionner manuellement.');
    });
}
</script>

<style>
/* Styles existants conservés */
.social-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.social-btn {
    padding: 10px 15px;
    border-radius: 5px;
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.whatsapp { background: #25D366; }
.facebook { background: #3b5998; }
.copy-link { background: #6c757d; }

.copy-notification {
    color: #2a9d8f;
    font-size: 14px;
    margin-top: 10px;
    opacity: 0;
    transition: opacity 0.3s;
}

.copy-notification.show {
    opacity: 1;
}
</style>
        
      </div>
    </div>
  </div>

  <!-- Guide Content -->
<div id="guide-content" class="content-section">
  <div class="guide-section">
    <h3><i class="fas fa-book-open"></i> Guide Utilisateur</h3>
    
    <div class="steps-container">
      <div class="step">
        <div class="step-icon">
          <i class="fas fa-user-plus step-number-icon"></i>
          <div class="step-number">1</div>
        </div>
        <div class="step-content">
          <h4>Créer un compte</h4>
          <p>Inscrivez-vous gratuitement pour accéder à toutes les fonctionnalités</p>
        </div>
      </div>
      
      <div class="step">
        <div class="step-icon">
          <i class="fas fa-search step-number-icon"></i>
          <div class="step-number">2</div>
        </div>
        <div class="step-content">
          <h4>Rechercher des épreuves</h4>
          <p>Utilisez les filtres par école, matière et année pour trouver ce qu'il vous faut</p>
        </div>
      </div>
      
      <div class="step">
        <div class="step-icon">
          <i class="fas fa-upload step-number-icon"></i>
          <div class="step-number">3</div>
        </div>
        <div class="step-content">
          <h4>Partager vos documents</h4>
          <p>Déposez vos épreuves et corrigés pour aider les autres étudiants</p>
        </div>
      </div>
      
      <div class="step">
        <div class="step-icon">
          <i class="fas fa-file-download step-number-icon"></i>
          <div class="step-number">4</div>
        </div>
        <div class="step-content">
          <h4>Télécharger et réviser</h4>
          <p>Accédez à toutes les ressources pour vos préparations</p>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* Ajouts spécifiques pour les icônes du guide */
.step-icon {
  position: relative;
  width: 50px;
  height: 50px;
  margin-right: 15px;
}

.step-number {
  position: absolute;
  bottom: -5px;
  right: -5px;
  width: 25px;
  height: 25px;
  background: var(--primary-dark);
  color: white;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.8rem;
  font-weight: bold;
}

.step-number-icon {
  font-size: 1.8rem;
  color: var(--primary);
  background: rgba(42, 157, 143, 0.1);
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  padding: 10px;
}

.step-content h4 {
  display: flex;
  align-items: center;
  gap: 10px;
}

/* Animation des icônes */
.step-icon i {
  transition: transform 0.3s ease;
}

.step:hover .step-icon i {
  transform: scale(1.1);
}
</style>

<style>
:root {
  --primary: #2a9d8f;
  --primary-dark: #1d7a6b;
  --light: #f8f9fa;
  --light-gray: #e9ecef;
  --text: #333;
  --text-light: #666;
}

.home-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

/* Hero Section */
.hero-section {
  text-align: center;
  padding: 50px 20px;
  background: linear-gradient(135deg, var(--primary), var(--primary-dark));
  color: white;
  border-radius: 10px;
  margin-bottom: 30px;
}

.hero-section h1 {
  font-size: 2.5rem;
  margin-bottom: 10px;
}

.subtitle {
  font-size: 1.2rem;
  margin-bottom: 30px;
  opacity: 0.9;
}

.action-buttons {
  display: flex;
  gap: 15px;
  justify-content: center;
  flex-wrap: wrap;
}

.main-btn {
  padding: 12px 25px;
  border-radius: 6px;
  background: white;
  color: var(--primary);
  text-decoration: none;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: all 0.3s;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.main-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.main-btn.outline {
  background: transparent;
  border: 2px solid white;
  color: white;
}

/* Value Cards */
.value-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 20px;
  margin: 40px 0;
}

.value-card {
  padding: 25px;
  background: white;
  border-radius: 8px;
  text-align: center;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
  transition: transform 0.3s;
}

.value-card:hover {
  transform: translateY(-5px);
}

.value-card i {
  font-size: 2rem;
  color: var(--primary);
  margin-bottom: 15px;
}

.value-card h3 {
  color: var(--primary-dark);
  margin-bottom: 10px;
}

.value-card p {
  color: var(--text-light);
  line-height: 1.5;
}

/* Navigation Tabs */
.content-navigation {
  display: flex;
  border-bottom: 1px solid var(--light-gray);
  margin: 40px 0 20px;
  overflow-x: auto;
}

.nav-tab {
  padding: 12px 20px;
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  cursor: pointer;
  font-weight: 600;
  color: var(--text-light);
  white-space: nowrap;
}

.nav-tab.active {
  color: var(--primary);
  border-bottom-color: var(--primary);
}

/* Content Sections */
.content-section {
  display: none;
  padding: 20px 0;
  animation: fadeIn 0.5s;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* About Section */
.about-section {
  background: white;
  padding: 25px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.about-section h3 {
  color: var(--primary-dark);
  margin-top: 20px;
  margin-bottom: 15px;
}

.about-section h3:first-child {
  margin-top: 0;
}

.benefits-list {
  list-style: none;
  padding: 0;
}

.benefits-list li {
  margin-bottom: 10px;
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.benefits-list i {
  color: var(--primary);
  margin-top: 3px;
}

/* Contact Section */
.contact-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
}

.contact-form, .suggestions-form {
  background: white;
  padding: 25px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.contact-form h3, .suggestions-form h3 {
  color: var(--primary-dark);
  margin-top: 0;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

input, textarea {
  width: 100%;
  padding: 12px;
  margin-bottom: 15px;
  border: 1px solid var(--light-gray);
  border-radius: 6px;
  font-family: inherit;
  font-size: 1rem;
}

textarea {
  min-height: 120px;
  resize: vertical;
}

/* Support Section */
.support-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 30px;
}

.donation-section, .social-section {
  background: white;
  padding: 25px;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.donation-section h3, .social-section h3 {
  color: var(--primary-dark);
  margin-top: 0;
  margin-bottom: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.donation-methods {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.method {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 15px;
  background: var(--light);
  border-radius: 6px;
}

.method i {
  font-size: 1.5rem;
  color: var(--primary);
}

.method div {
  display: flex;
  flex-direction: column;
}

.method span {
  font-size: 0.9rem;
  color: var(--text-light);
}

.method strong {
  font-size: 1.1rem;
  color: var(--text);
}


/* Responsive */
@media (max-width: 768px) {
  .contact-grid, .support-grid {
    grid-template-columns: 1fr;
  }
  
  .hero-section {
    padding: 30px 15px;
  }
  
  .value-cards {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 480px) {
  .content-navigation {
    gap: 5px;
  }
  
  .nav-tab {
    padding: 10px 15px;
    font-size: 0.9rem;
  }
}
</style>

<script>
// Fonction pour afficher le contenu des onglets
function showContent(sectionId) {
  // Masquer tous les contenus
  document.querySelectorAll('.content-section').forEach(section => {
    section.style.display = 'none';
  });
  
  // Désactiver tous les onglets
  document.querySelectorAll('.nav-tab').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Afficher la section sélectionnée
  document.getElementById(sectionId + '-content').style.display = 'block';
  
  // Activer l'onglet correspondant
  event.currentTarget.classList.add('active');
}


// Animation au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
  // Animation des cartes de valeur
  const valueCards = document.querySelectorAll('.value-card');
  valueCards.forEach((card, index) => {
    setTimeout(() => {
      card.style.opacity = 1;
      card.style.transform = 'translateY(0)';
    }, 150 * index);
  });

  // Initialisation
  if (window.location.hash) {
    const tabId = window.location.hash.substring(1);
    const tabButton = document.querySelector(`.nav-tab[onclick="showContent('${tabId}')"]`);
    if (tabButton) tabButton.click();
  }
});
</script>

<!-- Intégration des icônes Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script>
  // Désactiver tous les onglets
  document.querySelectorAll('.nav-tab').forEach(tab => {
    tab.classList.remove('active');
  });
  
  // Afficher la section sélectionnée
  document.getElementById(sectionId + '-content').style.display = 'block'
   </script>
<?php elseif ($action === 'multi_upload'): ?>
    <?php if (!is_logged() || $_SESSION['role'] !== 'admin'): ?>
        <div class="card"><p>Accès réservé aux administrateurs.</p></div>
    <?php else: 
        // Récupération des données depuis la base AVANT la partie HTML
        $schools = mysqli_query($conn, "SELECT * FROM schools ORDER BY name");
        $departments = mysqli_query($conn, "SELECT * FROM departments ORDER BY name");
        $subjects_list = mysqli_query($conn, "SELECT * FROM subjects ORDER BY name");
        
        // Vérification que les requêtes ont réussi
        if(!$schools) die("Erreur écoles: " . mysqli_error($conn));
        if(!$departments) die("Erreur départements: " . mysqli_error($conn));
        if(!$subjects_list) die("Erreur matières: " . mysqli_error($conn));
    ?>
    <div class="multi-upload-form">
    <div class="form-card">
        <h2>Déposer plusieurs épreuves</h2>
        <form method="post" enctype="multipart/form-data" action="index.php?action=multi_upload_process">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">

            <!-- Champs communs à toutes les épreuves -->
            <fieldset style="border:1px solid #ccc; padding:10px; margin-bottom:15px;">
                <legend>Informations communes</legend>
                
                <div class="form-group">
                    <label>École / Faculté</label>
                    <select name="common_school" id="common_school" required>
                        <option value="">-- choisir --</option>
                        <?php if(mysqli_num_rows($schools) > 0): ?>
                            <?php mysqli_data_seek($schools, 0); ?>
                            <?php while($s = mysqli_fetch_assoc($schools)): ?>
                                <option value="<?php echo $s['id']; ?>"><?php echo h($s['name']); ?></option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="">Aucune école disponible</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Département</label>
                    <select name="common_department" id="common_department" required disabled>
                        <option value="">-- choisissez d'abord une école --</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Matière</label>
                    <select name="common_subject" required>
                        <?php if(mysqli_num_rows($subjects_list) > 0): ?>
                            <?php mysqli_data_seek($subjects_list, 0); ?>
                            <?php while($sub = mysqli_fetch_assoc($subjects_list)): ?>
                                <option value="<?php echo $sub['id']; ?>"><?php echo h($sub['name']); ?></option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="">Aucune matière disponible</option>
                        <?php endif; ?>
                    </select>
                </div>
            </fieldset>

            <!-- Champs spécifiques à chaque épreuve -->
            <?php for ($i = 0; $i < 5; $i++): ?>
                <fieldset style="border:1px solid #ccc; padding:10px; margin-bottom:15px;">
                    <legend>Épreuve <?php echo $i+1; ?></legend>

                    <div class="form-group">
                        <label>Titre</label>
                        <input type="text" name="title[]" placeholder="Titre de l'épreuve" required>
                    </div>

                    <div class="form-group">
                        <label>Niveau</label>
                        <select name="year[]" required>
                            <option value="">-- choisir l'année --</option>
                            <option value="1">1ère année</option>
                            <option value="2">2è année</option>
                            <option value="3">3è année</option>
                            <option value="4">4è année</option>
                            <option value="5">5è année</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description[]" rows="2"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Fichier épreuve</label>
                        <input type="file" name="exam[]" accept=".pdf,.doc,.docx,.zip,.jpg,.jpeg,.png" required>
                    </div>

                    <div class="form-group">
                        <label>Correction (optionnelle)</label>
                        <input type="file" name="correction[]" accept=".pdf,.doc,.docx,.zip,.jpg,.jpeg,.png">
                    </div>
                </fieldset>
            <?php endfor; ?>

            <button class="btn" type="submit"><i class="fas fa-cloud-upload-alt"></i> Envoyer</button>
        </form>
    </div>
</div>

<script>
// Chargement dynamique des départements pour les champs communs
document.getElementById('common_school').addEventListener('change', function() {
    const schoolId = this.value;
    const dep = document.getElementById('common_department');
    dep.innerHTML = '<option>Chargement...</option>';
    dep.disabled = true;
    
    if (!schoolId) {
        dep.innerHTML = '<option value="">-- d\'abord choisir école --</option>';
        return;
    }
    
    fetch('index.php?action=fetch_departments&school_id=' + encodeURIComponent(schoolId))
        .then(r => r.json())
        .then(data => {
            dep.innerHTML = '';
            if (!data.length) {
                dep.innerHTML = '<option value="">Aucun département</option>';
            } else {
                data.forEach(d => {
                    const o = document.createElement('option');
                    o.value = d.id;
                    o.textContent = d.name;
                    dep.appendChild(o);
                });
                dep.disabled = false;
            }
        })
        .catch(() => {
            dep.innerHTML = '<option value="">Erreur</option>';
        });
});

// Au moment de la soumission, copier les valeurs communes dans chaque épreuve
document.querySelector('form').addEventListener('submit', function(e) {
    // Récupérer les valeurs communes
    const commonSchool = document.querySelector('[name="common_school"]').value;
    const commonDepartment = document.querySelector('[name="common_department"]').value;
    const commonSubject = document.querySelector('[name="common_subject"]').value;
    
    // Créer des champs cachés pour chaque épreuve
    for (let i = 0; i < 5; i++) {
        const schoolInput = document.createElement('input');
        schoolInput.type = 'hidden';
        schoolInput.name = 'school_id[]';
        schoolInput.value = commonSchool;
        this.appendChild(schoolInput);
        
        const deptInput = document.createElement('input');
        deptInput.type = 'hidden';
        deptInput.name = 'department_id[]';
        deptInput.value = commonDepartment;
        this.appendChild(deptInput);
        
        const subjectInput = document.createElement('input');
        subjectInput.type = 'hidden';
        subjectInput.name = 'subject_id[]';
        subjectInput.value = commonSubject;
        this.appendChild(subjectInput);
    }
});
</script>
    <?php endif; ?>

<?php elseif ($action === 'login'): ?> 
<div class="card form-card">
  <h2>Connexion</h2>
  <p>Accède à ton espace.</p>

  <?php
  // Vérification après soumission
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      $password = $_POST['password'] ?? '';
      if (strlen($password) < 8) {
          $_SESSION['flash_error'] = "Le mot de passe doit contenir au moins 8 caractères.";
      }
  }
  ?>

  <form method="post" action="index.php?action=login">
    <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
    
    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" required>
    </div>
    
    <div class="form-group" style="position: relative;">
      <label>Mot de passe <span style="color:red;">(min. 8 caractères)</span></label>
      <input type="password" id="password" name="password" minlength="8" required style="padding-right: 35px;">
      <i class="fas fa-eye" id="togglePassword" 
         style="position: absolute; right: 10px; top: 38px; cursor: pointer; color: gray;"></i>
    </div>
    
    <button class="btn" type="submit"><i class="fas fa-right-to-bracket"></i> Se connecter</button>
  </form>
  
  <p class="tiny">Pas encore inscrit ? <a href="index.php?action=register">Inscription</a></p>
</div>

<script>
// Gestion affichage / masquage mot de passe
document.getElementById("togglePassword").addEventListener("click", function () {
    let passwordInput = document.getElementById("password");
    let icon = this;
    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.classList.remove("fa-eye");
        icon.classList.add("fa-eye-slash");
    } else {
        passwordInput.type = "password";
        icon.classList.remove("fa-eye-slash");
        icon.classList.add("fa-eye");
    }
});
</script>

    <?php elseif ($action === 'upload'): ?>
      <?php if (is_logged() && $_SESSION['role'] === 'admin'): ?>
     <a class="btn btn-multi-upload" href="index.php?action=multi_upload">
    <i class="fas fa-layer-group"></i> Dépôt multiple
    </a>
<?php endif; ?>

      <?php if (!is_logged()): ?>
        <div class="card"><p>Tu dois te connecter pour déposer une épreuve. <a href="index.php?action=login">Connexion</a></p></div>
      <?php else: ?>
        <div class="card form-card">
          <h2>Déposer une épreuve</h2>
          <form method="post" enctype="multipart/form-data" action="index.php?action=upload">
            <input type="hidden" name="csrf_token" value="<?php echo h($_SESSION['csrf_token']); ?>">
            <div class="form-group">
              <label>École / Faculté</label>
              <select name="school_id" id="school" required>
                <option value="">-- cliquez pour choisir --</option>
                <?php 
                  mysqli_data_seek($schools,0);
                  while($s = mysqli_fetch_assoc($schools)): ?>
                    <option value="<?php echo $s['id']; ?>"><?php echo h($s['name']); ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Département</label>
              <select name="department_id" id="department" required disabled>
                <option value="">-- choisissez d'abord une école --</option>
              </select>
            </div>
            <div class="form-group">
              <label>Matière</label>
              <select name="subject_id" required>
                <?php 
                  mysqli_data_seek($subjects_list,0);
                  while($sub = mysqli_fetch_assoc($subjects_list)): ?>
                    <option value="<?php echo $sub['id']; ?>"><?php echo h($sub['name']); ?></option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Titre</label>
              <input type="text" name="title" required>
            </div>
            <div class="form-group">
              <label>Niveau</label>
              <select name="year" required> 
                <option value= "">-- Choisissez le niveau--</option>
                <option value="1ère année">1ère année</option>
                <option value="2è année">2è année</option>
                <option value="3è année">3è année</option>
                <option value="4è année">4è année</option>
                <option value="5è année">5è année</option>
                </select>
            </div>
            <div class="form-group">
              <label>Description (facultatif)</label>
              <textarea name="description" rows="3"></textarea>
            </div>
            <div class="form-group">
              <label>Fichier épreuve</label>
              <input type="file" name="exam" accept=".pdf,.doc,.docx,.zip,.jpg,.jpeg,.png" required>
            </div>
            <div class="form-group">
              <label>Correction (optionnelle)</label>
              <input type="file" name="correction" accept=".pdf,.doc,.docx,.zip,.jpg,.jpeg,.png">
            </div>
            <button class="btn" type="submit"><i class="fas fa-cloud-upload-alt"></i> Déposer</button>
          </form>
        </div>
      <?php endif; ?>
      <?php endif; ?>              
    <?php
    // vue détail
    if ($action === 'detail' && isset($_GET['id'])):
      $id = intval($_GET['id']);
      $query = "
        SELECT 
          e.id, e.title, e.year, e.description, e.filename, e.correction_filename, e.uploaded_at,
          s.name AS subject_name,
          sc.name AS school_name,
          d.name AS department_name,
          u.username
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        JOIN users u ON e.user_id = u.id
        LEFT JOIN schools sc ON e.school_id = sc.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.id = $id
        LIMIT 1
      ";
      $exam = mysqli_fetch_assoc(mysqli_query($conn, $query));
      
        if (!$exam):
            alert("Épreuve introuvable.","error");
        else:
    ?>
      <div class="card">
        <div class="flex">
          <div class="grow">
            <h2>
              <?php echo h($exam['title'] ?? 'Sans titre'); ?> 
            </h2>
              <p class="tiny"><strong>Niveau :</strong><?php echo h ($exam['year'] ?? '_'); ?></p>
           
            <p class="tiny">
              <strong>École :</strong> <?php echo h($exam['school_name'] ?? '—'); ?> • 
              <strong>Département :</strong> <?php echo h($exam['department_name'] ?? '—'); ?>
            </p>
            <p class="tiny"><strong>Matière :</strong> <?php echo h($exam['subject_name'] ?? '—'); ?></p>
            <p class="tiny"><strong>Déposé par :</strong> <?php echo h($exam['username'] ?? 'anonyme'); ?></p>
            <p><?php echo nl2br(h($exam['description'] ?? '')); ?></p>
          </div>
          <div style="min-width:160px;display:flex;flex-direction:column;align-items:center;gap:56px;">
    <?php if (!empty($exam['filename'])): ?>
        <a href="uploads/<?php echo urlencode($exam['filename']); ?>" target="_blank" class="btn" title="Voir l'épreuve">
            <i class="fas fa-eye"></i> Voir
        </a>
        <a class="btn" href="uploads/<?php echo urlencode($exam['filename']); ?>" download>
            <i class="fas fa-download"></i> Télécharger
        </a>
    <?php endif; ?>
    <?php if (!empty($exam['correction_filename'])): ?>
        <a href="uploads/<?php echo urlencode($exam['correction_filename']); ?>" target="_blank" class="btn" title="Voir la correction">
            <i class="fas fa-eye"></i> Voir Corr.
        </a>
        <a class="btn" href="uploads/<?php echo urlencode($exam['correction_filename']); ?>" download>
            <i class="fas fa-check-circle"></i> Télécharger
        </a>
    <?php else: ?>
        <div class="badge">Correction indisponible</div>
    <?php endif; ?>
</div>
        </div>
      </div>
    <?php
        endif;
    endif;
    ?>

     <?php if ($action === 'search'): ?>
      <?php if (!is_logged()): ?>
        <div class="card"><p>Tu dois te connecter pour télécharger une épreuve. <a href="index.php?action=login">Connexion</a></p></div>
      <?php else: ?>
      <div class="card">
        <div class="flex" style="align-items:center;justify-content:space-between;">
          <div>
            <h2>Rechercher des épreuves</h2>
            <div class="small">Filtre par école, département, matière, année ou mot-clé</div>
          </div>
        </div>

        <form class="search-bar" method="get" action="index.php">
          <input type="hidden" name="action" value="search">
          <input type="text" name="q" placeholder="Mot-clé" value="<?php echo h($keyword); ?>">
          <select name="school" id="filter-school">
            <option value="0">Toutes écoles</option>
            <?php 
              mysqli_data_seek($schools,0);
              while($s = mysqli_fetch_assoc($schools)): ?>
                <option value="<?php echo $s['id']; ?>" <?php if($filter_school==$s['id']) echo 'selected'; ?>><?php echo h($s['name']); ?></option>
            <?php endwhile; ?>
          </select>
          <select name="department" id="filter-department">
            <option value="0">Tous départements</option>
            <?php 
              if ($filter_school) {
                $deps = mysqli_query($conn, "SELECT * FROM departments WHERE school_id=$filter_school ORDER BY name");
                while($d = mysqli_fetch_assoc($deps)): ?>
                  <option value="<?php echo $d['id']; ?>" <?php if($filter_department==$d['id']) echo 'selected'; ?>><?php echo h($d['name']); ?></option>
                <?php endwhile;
              }
                  
            ?>
          </select>
          <select name="subject">
            <option value="0">Toutes matières</option>
            <?php 
              mysqli_data_seek($subjects_list,0);
              while($sub = mysqli_fetch_assoc($subjects_list)): ?>
                <option value="<?php echo $sub['id']; ?>" <?php if($filter_subject==$sub['id']) echo 'selected'; ?>><?php echo h($sub['name']); ?></option>
            <?php endwhile; ?>
          </select>
            <select name="year">
            <option value="0">Tous niveaux</option>
            <option value="1" <?php if($filter_year==1) echo 'selected'; ?>>1ère année</option>
            <option value="2" <?php if($filter_year==2) echo 'selected'; ?>>2è année</option>
            <option value="3" <?php if($filter_year==3) echo 'selected'; ?>>3è année</option>
            <option value="4" <?php if($filter_year==4) echo 'selected'; ?>>4è année</option>
            <option value="5" <?php if($filter_year==5) echo 'selected'; ?>>5è année</option>
            </select>
          <button type="submit"><i class="fas fa-magnifying-glass"></i> Rechercher</button>
        </form>

        <?php if (mysqli_num_rows($exams) == 0): ?>
          <p>Aucune épreuve trouvée.</p>
        <?php endif; ?>

        <?php while($exam = mysqli_fetch_assoc($exams)): ?>
  <div class="card">
    <div class="flex">
      <div class="grow">
       <?php
// Tableau pour convertir l'année (numérique) en texte
$niveau_labels = [
    1 => "1ère année",
    2 => "2è année",
    3 => "3è année",
    4 => "4è année",
    5 => "5è année"
];
$niveau = $exam['year'] ?? null;
?>
<h2>
  <?php echo h($exam['title'] ?? 'Sans titre'); ?> 
  <span class="small">
    (<?php echo isset($niveau_labels[$niveau]) ? $niveau_labels[$niveau] : '—'; ?>)
  </span>
</h2>

        <div class="tiny">
          <span class="tag"><?php echo h($exam['school_name'] ?? '—'); ?></span>
          <span class="tag"><?php echo h($exam['department_name'] ?? '—'); ?></span>
          <span class="tag"><?php echo h($exam['subject_name'] ?? '—'); ?></span>
        </div>
        <p class="tiny">
          Déposé par <?php echo h($exam['username'] ?? 'anonyme'); ?> • 
          <?php 
            $uploaded = $exam['uploaded_at'] ?? null;
            echo $uploaded ? date('d/m/Y', strtotime($uploaded)) : 'Date inconnue';
          ?>
        </p>
        <p><?php echo nl2br(h($exam['description'] ?? '')); ?></p>
      </div>
      <div class="actions">
    <?php if (!empty($exam['filename'])): ?>
        <a href="uploads/<?php echo urlencode($exam['filename']); ?>" target="_blank" class="btn" title="Voir l'épreuve">
            <i class="fas fa-eye"></i> Voir
        </a>
        <a class="btn secondary" href="download.php?id=<?php echo intval($exam['id']); ?>&type=exam">
            <i class="fas fa-download"></i> Épreuve
        </a>
    <?php endif; ?>
    <?php if (!empty($exam['correction_filename'])): ?>
        <a href="uploads/<?php echo urlencode($exam['correction_filename']); ?>" target="_blank" class="btn" title="Voir la correction">
            <i class="fas fa-eye"></i> Voir Corr.
        </a>
        <a class="btn secondary" href="download.php?id=<?php echo intval($exam['id']); ?>&type=correction">
            <i class="fas fa-check-circle"></i> Correction
        </a>
    <?php endif; ?>
</div>
    </div>
  </div>
<?php endwhile; ?>

  </div>
    <?php endif; ?>
  </div>
   <?php endif; ?>
   <?php 
   /* ---------- ADMIN ROUTES ---------- */
   
/* ---------- ADMIN ROUTES ---------- */
if ($action === 'admin_dashboard') {
    admin_only();
    // Statistiques basiques
    $stats = [
        'users' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM users"))['total'],
        'exams' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM exams"))['total'],
        'schools' => mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM schools"))['total']
    ];
} 
elseif ($action === 'admin_users') {
    admin_only();
    $users_query = mysqli_query($conn, "SELECT id, username, email, role FROM users ORDER BY created_at DESC");
}
elseif ($action === 'admin_update_role' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_only();
    
    if (!check_csrf($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Erreur de sécurité";
        header("Location: index.php?action=admin_users");
        exit();
    }

    $user_id = intval($_POST['user_id']);
    $new_role = ($_POST['role'] === 'admin') ? 'admin' : 'etudiant';

    // Empêcher l'auto-rétrogradation
    if ($user_id === $_SESSION['user_id'] && $new_role === 'etudiant') {
        $_SESSION['flash_error'] = "Vous ne pouvez pas vous retirer les droits admin";
    } else {
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $user_id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Rôle mis à jour";
        } else {
            $_SESSION['flash_error'] = "Erreur lors de la mise à jour";
        }
    }
    header("Location: index.php?action=admin_users");
    exit();
}
/* ---------- ADMIN ROUTES ---------- */
/* ---------- ADMIN MESSAGES ---------- */
elseif ($action === 'admin_messages') {
    admin_only();
    $messages = mysqli_query($conn, "SELECT * FROM messages ORDER BY sent_at DESC");
    include 'templates/admin_messages.php';
    exit();
}

elseif ($action === 'admin_suggestions') {
    admin_only();
    $suggestions = mysqli_query($conn, "SELECT * FROM suggestions ORDER BY submitted_at DESC");
    include 'templates/admin_suggestions.php';
    exit();
}

elseif ($action === 'delete_message' && isset($_GET['id'])) {
    admin_only();
    $id = intval($_GET['id']);
    mysqli_query($conn, "DELETE FROM messages WHERE id = $id");
    $_SESSION['flash_success'] = "Message supprimé";
    header("Location: index.php?action=admin_messages");
    exit();
}

elseif ($action === 'delete_suggestion' && isset($_GET['id'])) {
    admin_only();
    $id = intval($_GET['id']);
    mysqli_query($conn, "DELETE FROM suggestions WHERE id = $id");
    $_SESSION['flash_success'] = "Suggestion supprimée";
    header("Location: index.php?action=admin_suggestions");
    exit();
}
elseif ($action === 'admin_exams') {
    admin_only();
    
    // Gestion des épreuves
    $exams_query = mysqli_query($conn, "
    SELECT 
        e.Id,
        e.Title,
        e.Filename,
        e.Uploaded_at,
        u.username,
        s.name AS subject_name,
        d.name AS department_name,
        sch.name AS school_name
    FROM exams e
    JOIN users u ON e.User_id = u.id
    LEFT JOIN subjects s ON e.Subject_id = s.id
    LEFT JOIN departments d ON e.Department_id = d.id
    LEFT JOIN schools sch ON e.School_id = sch.id
    ORDER BY e.Uploaded_at DESC
");
    
    include 'admin_panel.php';
    exit();
}

elseif ($action === 'delete_exam' && isset($_GET['id'])) {
    admin_only();
    
    $exam_id = intval($_GET['id']);
    $exam = mysqli_fetch_assoc(mysqli_query($conn, "SELECT filename, correction_filename FROM exams WHERE id = $exam_id"));
    
    if ($exam) {
        @unlink($upload_dir.'/'.$exam['filename']);
        @unlink($upload_dir.'/'.$exam['correction_filename']);
        mysqli_query($conn, "DELETE FROM exams WHERE id = $exam_id");
        $_SESSION['flash_success'] = "Épreuve supprimée";
    } else {
        $_SESSION['flash_error'] = "Épreuve introuvable";
    }
    
    header("Location: index.php?action=admin_exams");
    exit();
}

elseif ($action === 'admin_stats') {
    admin_only();
    
    // Statistiques
    $stats_data = [
        'recent_users' => mysqli_query($conn, "SELECT username, created_at FROM users ORDER BY created_at DESC LIMIT 5"),
        'popular_subjects' => mysqli_query($conn, "SELECT s.name, COUNT(e.id) as count FROM subjects s LEFT JOIN exams e ON s.id = e.subject_id GROUP BY s.id ORDER BY count DESC LIMIT 5"),
        'activity' => mysqli_query($conn, "SELECT DATE(uploaded_at) as day, COUNT(*) as count FROM exams GROUP BY day ORDER BY day DESC LIMIT 7"),
        'downloads'=> mysqli_query($conn, "SELECT DATE(download_date) AS day, COUNT(*) AS count FROM download_log WHERE download_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)  GROUP BY day     ORDER BY day DESC")
    ];


    include 'admin_panel.php';
    exit();
}
elseif ($action === 'bulk_delete_exams' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_only();
    
    // Vérification CSRF
    if (!isset($_POST['csrf_token']) || !check_csrf($_POST['csrf_token'])) {
        $_SESSION['flash_error'] = "Erreur de sécurité";
        header("Location: index.php?action=admin_exams");
        exit();
    }

    // Vérifier si des IDs ont été sélectionnés
    if (empty($_POST['ids'])) {
        $_SESSION['flash_error'] = "Aucune épreuve sélectionnée";
        header("Location: index.php?action=admin_exams");
        exit();
    }

    // Nettoyer les IDs
    $ids = array_map('intval', $_POST['ids']);
    $ids_list = implode(',', $ids);

    // 1. Récupérer les noms de fichiers
    $files_to_delete = [];
    $query = mysqli_query($conn, "SELECT filename, correction_filename FROM exams WHERE id IN ($ids_list)");
    while ($row = mysqli_fetch_assoc($query)) {
        if (!empty($row['filename'])) $files_to_delete[] = $row['filename'];
        if (!empty($row['correction_filename'])) $files_to_delete[] = $row['correction_filename'];
    }

    // 2. Supprimer de la base de données
    $delete_query = mysqli_query($conn, "DELETE FROM exams WHERE id IN ($ids_list)");
    
    if ($delete_query) {
        $count = mysqli_affected_rows($conn);
        
        // 3. Supprimer les fichiers physiques
        foreach ($files_to_delete as $filename) {
            @unlink("uploads/" . $filename);
        }
        
        $_SESSION['flash_success'] = "$count épreuve(s) supprimée(s) avec succès";
    } else {
        $_SESSION['flash_error'] = "Erreur lors de la suppression: " . mysqli_error($conn);
    }

    header("Location: index.php?action=admin_exams");
    exit();
}
elseif ($action === 'delete_user' && isset($_GET['id'])) {
    admin_only();
    
    $user_id = intval($_GET['id']);
    $current_user = $_SESSION['user_id'];
    
    // Vérifications de sécurité
    if ($user_id === $current_user) {
        $_SESSION['flash_error'] = "Auto-suppression interdite";
    } else {
        // Vérifier s'il reste d'autres admins
        $admins_count = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND id != $user_id")
                          ->fetch_row()[0];
        
        $user_role = $conn->query("SELECT role FROM users WHERE id = $user_id")->fetch_row()[0] ?? null;
        
        if ($user_role === 'admin' && $admins_count < 1) {
            $_SESSION['flash_error'] = "Vous devez conserver au moins un admin";
        } elseif ($user_role) {
            // Transaction pour supprimer d'abord les épreuves
            $conn->begin_transaction();
            try {
                $conn->query("DELETE FROM exams WHERE user_id = $user_id");
                $conn->query("DELETE FROM users WHERE id = $user_id");
                $conn->commit();
                $_SESSION['flash_success'] = "Utilisateur supprimé";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['flash_error'] = "Erreur lors de la suppression";
            }
        } else {
            $_SESSION['flash_error'] = "Utilisateur introuvable";
        }
    }
    header("Location: index.php?action=admin_users");
    exit();
}

// Inclusion du template admin
if (in_array($action, ['admin_dashboard', 'admin_users'])) {
    include 'admin_panel.php';
    exit(); // Important pour éviter que le reste du script s'exécute
}

?>
</main>
<script>
// chargement dynamique des départements (upload)
document.getElementById('school')?.addEventListener('change', function(){
    const schoolId = this.value;
    const dep = document.getElementById('department');
    dep.innerHTML = '<option>Chargement...</option>';
    dep.disabled = true;
    if (!schoolId) {
        dep.innerHTML = '<option value="">-- d\'abord choisir école --</option>';
        return;
    }
    fetch('index.php?action=fetch_departments&school_id=' + encodeURIComponent(schoolId))
      .then(r => r.json())
      .then(data => {
        dep.innerHTML = '';
        if (!data.length) {
          dep.innerHTML = '<option value="">Aucun département</option>';
        } else {
          data.forEach(d => {
            const o = document.createElement('option');
            o.value = d.id;
            o.textContent = d.name;
            dep.appendChild(o);
          });
          dep.disabled = false;
        }
      })
      .catch(() => {
        dep.innerHTML = '<option value="">Erreur</option>';
      });
});
</script>
<script>
// Chargement dynamique des départements (dans le formulaire de recherche)
document.getElementById('filter-school')?.addEventListener('change', function () {
  const schoolId = this.value;
  const dep = document.getElementById('filter-department');

  dep.innerHTML = '<option value="0">Chargement...</option>';
  dep.disabled = true;

  if (!schoolId || schoolId === "0") {
    dep.innerHTML = '<option value="0">Tous départements</option>';
    dep.disabled = false;
    return;
  }

  fetch('index.php?action=fetch_departments&school_id=' + encodeURIComponent(schoolId))
    .then(response => response.json())
    .then(data => {
      dep.innerHTML = '<option value="0">Tous départements</option>';
      data.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;
        opt.textContent = item.name;
        dep.appendChild(opt);
      });
      dep.disabled = false;
    })
    .catch(() => {
      dep.innerHTML = '<option value="0">Erreur de chargement</option>';
      dep.disabled = false;
    });
});
</script>
<script>
  // Lorsque l'école change, on met à jour les départements
document.getElementById('filter-school').addEventListener('change', function() {
  var schoolId = this.value;

  // On appelle le fichier PHP avec l'ID de l'école sélectionnée
  var xhr = new XMLHttpRequest();
  xhr.open('GET', 'get_departments.php?school_id=' + schoolId, true);
  xhr.onload = function() {
    if (xhr.status === 200) {
      var departments = JSON.parse(xhr.responseText);
      
      // On vide la liste des départements actuelle
      var departmentSelect = document.getElementById('filter-department');
      departmentSelect.innerHTML = '<option value="0">Tous départements</option>';

      // On ajoute les nouveaux départements reçus de la requête AJAX
      departments.forEach(function(department) {
        var option = document.createElement('option');
        option.value = department.id;
        option.textContent = department.name;
        departmentSelect.appendChild(option);
      });
    }
  };
  xhr.send();
});
</script>

<div class="footer">
      <div>&copy; <?php echo date('Y'); ?> EpreuveXpress. Tous droits réservés.</div>
      <div class="C">Aidons nous mutuellement</div>
    </div>
    <style>
    
/* Style général du formulaire */
.multi-upload-form {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.form-card {
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
    padding: 30px;
    margin-bottom: 40px;
}

.form-card h2 {
    color: #2a9d8f;
    text-align: center;
    margin-bottom: 30px;
    font-size: 26px;
    font-weight: 600;
    border-bottom: 2px solid #f0f0f0;
    padding-bottom: 15px;
}

/* Style des fieldset (blocs épreuve) */
.multi-upload-form fieldset {
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 25px;
    background: #f9f9f9;
    position: relative;
    transition: all 0.3s ease;
}

.multi-upload-form fieldset:hover {
    border-color: #2a9d8f;
    box-shadow: 0 4px 12px rgba(42, 157, 143, 0.15);
}

.multi-upload-form fieldset legend {
    font-weight: 600;
    color: #2a9d8f;
    padding: 0 15px;
    background: #ffffff;
    border-radius: 20px;
    border: 1px solid #2a9d8f;
    font-size: 16px;
}

/* Style des groupes de champs */
.multi-upload-form .form-group {
    margin-bottom: 20px;
}

.multi-upload-form label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
    font-size: 14px;
}

.multi-upload-form input[type="text"],
.multi-upload-form textarea,
.multi-upload-form select {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s ease;
    background: #ffffff;
    box-sizing: border-box;
}

.multi-upload-form input[type="text"]:focus,
.multi-upload-form textarea:focus,
.multi-upload-form select:focus {
    border-color: #2a9d8f;
    box-shadow: 0 0 0 3px rgba(42, 157, 143, 0.2);
    outline: none;
}

.multi-upload-form textarea {
    min-height: 80px;
    resize: vertical;
}

/* Style des selects */
.multi-upload-form select {
    appearance: none;
    background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 16px;
}

/* Style des champs fichier */
.multi-upload-form input[type="file"] {
    width: 100%;
    padding: 10px;
    border: 1px dashed #ccc;
    border-radius: 6px;
    background: #fafafa;
    transition: all 0.3s ease;
}

.multi-upload-form input[type="file"]:hover {
    border-color: #2a9d8f;
    background: #f5f5f5;
}

/* Bouton d'envoi */
.multi-upload-form .btn {
    display: block;
    width: 100%;
    max-width: 300px;
    margin: 30px auto 0;
    padding: 14px 25px;
    background: #2a9d8f;
    color: white;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-align: center;
}

.multi-upload-form .btn:hover {
    background: #22867a;
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(42, 157, 143, 0.4);
}

.multi-upload-form .btn i {
    margin-right: 8px;
}
/* Style spécifique pour le bouton Dépôt multiple */
.btn-multi-upload {
    display: block;
    margin: 20px auto;
    text-align: center;
    max-width: 200px;
    padding: 12px 20px;
    background: #2a9d8f;
    color: white;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.3s ease;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.btn-multi-upload:hover {
    background: #22867a;
    transform: translateY(-2px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.15);
}

.btn-multi-upload i {
    margin-right: 8px;
}

/* Style responsive */
@media (max-width: 768px) {
    .form-card {
        padding: 20px;
    }
    
    .multi-upload-form fieldset {
        padding: 15px;
    }
    
    .multi-upload-form input[type="text"],
    .multi-upload-form textarea,
    .multi-upload-form select {
        padding: 10px 12px;
    }
}

/* Animation pour différencier les blocs */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.multi-upload-form fieldset {
    animation: fadeInUp 0.4s ease-out forwards;
}

/* Délai d'animation pour chaque bloc */
.multi-upload-form fieldset:nth-child(1) { animation-delay: 0.1s; }
.multi-upload-form fieldset:nth-child(2) { animation-delay: 0.2s; }
.multi-upload-form fieldset:nth-child(3) { animation-delay: 0.3s; }
.multi-upload-form fieldset:nth-child(4) { animation-delay: 0.4s; }
.multi-upload-form fieldset:nth-child(5) { animation-delay: 0.5s; }
</style>

<script>

</script>
<style> 
/* Justification du texte - Version améliorée */
.card p:not(.tiny):not(.small),
.card .description,
.presentation-site p,
.presentation-site li {
  text-align: justify;
  text-justify: inter-word;
  -webkit-hyphens: auto;
  -ms-hyphens: auto;
  hyphens: auto;
  line-height: 1.6;
  word-spacing: -0.05em;
  margin-bottom: 1em;
}

/* Exceptions pour petits textes et éléments UI */
.tiny, .small, 
.nav a, .btn, 
.form-group label, 
.form-control {
  text-align: left !important;
  hyphens: none !important;
}

/* Adaptation mobile */
@media (max-width: 768px) {
  .card p, 
  .presentation-site p {
    text-align: left;
    hyphens: none;
  }
}
</style>

</body>
<?php
// À la fin du fichier, avant le </html>
ob_end_flush();
?>
</html>