<?php
session_start();
require 'config.php'; // Connexion à la base de données

if (!isset($_POST['id_entreprise'])) {
    exit('ID d\'entreprise non fourni');
}

$id_entreprise = $_POST['id_entreprise'];

try {
    // Mettre à jour les messages comme lus pour l'entreprise spécifiée
    $stmt = $conn->prepare("UPDATE messages SET lu = 1 WHERE id_entreprise = :id_entreprise AND lu = 0");
    $stmt->execute(['id_entreprise' => $id_entreprise]);

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>