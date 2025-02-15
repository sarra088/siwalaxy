<?php
session_start();
if (!isset($_SESSION['id_entreprise'])) {
    header('Location: login_entreprise.php');
    exit();
}
require 'config.php'; // Connexion à la base de données

$id_entreprise = $_SESSION['id_entreprise'];

// Récupérer les départements
$departements = $conn->query("
    SELECT * FROM departements WHERE id_entreprise = $id_entreprise
")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le nom de l'entreprise
$entreprise = $conn->query("SELECT nom_societe FROM entreprises WHERE id_entreprise = $id_entreprise")->fetch(PDO::FETCH_ASSOC);
$nom_entreprise = $entreprise['nom_societe'];
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <!-- Gestion des employés -->
    <div class="section">
        <h2>Gestion des Employés</h2>
        <button onclick="window.location.href='liste_employes.php'">Liste des Employés</button>
        <button onclick="window.location.href='ajouter_employe.php'">Ajouter Employé</button>
        <button onclick="window.location.href='supprimer_employe.php'">Supprimer Employé</button>
        <button onclick="window.location.href='modifier_employe.php'">Modifier Employé</button>
    </div>


</body>

</html>