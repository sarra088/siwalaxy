<?php
session_start();
if (!isset($_SESSION['id_entreprise'])) {
    header('Location: login_entreprise.php');
    exit();
}
require 'config.php'; // Connexion à la base de données

$id_entreprise = $_SESSION['id_entreprise'];

// Récupérer la liste des employés
$employes = $conn->query("
    SELECT * FROM employes WHERE id_entreprise = $id_entreprise
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Employés</title>
</head>

<body>

    <h1>Liste des Employés</h1>
    <table border="1">
        <tr>
            <th>Matricule</th>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Email</th>
            <th>Téléphone</th>
            <th>Date de Naissance</th>
            <th>Titre</th>
        </tr>
        <?php foreach ($employes as $employe): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($employe['matricule']) ?>
                </td>
                <td>
                    <?= htmlspecialchars($employe['nom']) ?>
                </td>
                <td>
                    <?= htmlspecialchars($employe['prenom']) ?>
                </td>
                <td>
                    <?= htmlspecialchars($employe['email']) ?>
                </td>
                <td>
                    <?= htmlspecialchars($employe['num_tel']) ?>
                </td>
                <td>
                    <?= htmlspecialchars($employe['date_naissance']) ?>
                </td>
                <td>
                    <?= htmlspecialchars($employe['titre']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

</body>

</html>