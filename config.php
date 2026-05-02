<?php
$host = "sql202.infinityfree.com"; 
$user = "if0_39714282"; 
$pass = "MonDomaine1234"; 
$dbname = "if0_39714282_epreuve_db"; 

$conn = new mysqli($host, $user, $pass, $dbname);

// Vérification de la connexion
if ($conn->connect_error) {
    die("Erreur de connexion : " . $conn->connect_error);
}

// Optionnel : définir l'encodage
$conn->set_charset("utf8");
?>
