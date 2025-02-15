<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_entreprise']) && !isset($_SESSION['matricule'])) {
    exit("Accès refusé");
}

$expediteur = $_POST['expediteur'];
$message = trim($_POST['message']);
$id_expediteur = $expediteur == 'entreprise' ? $_SESSION['id_entreprise'] : $_SESSION['matricule'];

if (!empty($message)) {
    $stmt = $conn->prepare("INSERT INTO messages (expediteur, id_expediteur, message) VALUES (:expediteur, :id_expediteur, :message)");
    $stmt->execute(['expediteur' => $expediteur, 'id_expediteur' => $id_expediteur, 'message' => $message]);
}

header("Location: messagerie.php");