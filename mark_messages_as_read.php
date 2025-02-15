<?php
session_start();
require 'config.php'; // Connexion à la base de données

if (!isset($_POST['matricule'])) {
    exit('Matricule non fourni');
}

$matricule = $_POST['matricule'];

try {
    // Récupérer l'ID de l'entreprise de l'employé
    $stmt = $conn->prepare("SELECT id_entreprise FROM employes WHERE matricule = :matricule");
    $stmt->execute(['matricule' => $matricule]);
    $id_entreprise = $stmt->fetchColumn();

    // Mettre à jour les messages comme lus
    $stmt = $conn->prepare("UPDATE messages SET lu = 1 WHERE id_entreprise = :id_entreprise AND lu = 0");
    $stmt->execute(['id_entreprise' => $id_entreprise]);

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
}
?>