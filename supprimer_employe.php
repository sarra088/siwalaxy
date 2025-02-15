<?php
session_start();
require 'config.php'; // Connexion à la base de données

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $matricule = $_POST['matricule'];

    // Supprimer l'employé
    $stmt = $conn->prepare("DELETE FROM employes WHERE matricule = ? AND id_entreprise = ?");
    $stmt->execute([$matricule, $_SESSION['id_entreprise']]);

    echo "Employé supprimé avec succès.";
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supprimer un Employé</title>
</head>

<body>

    <h1>Supprimer un Employé</h1>
    <form method="post">
        Matricule de l'employé à supprimer : <input type="text" name="matricule" required>
        <button type="submit">Supprimer</button>
    </form>

</body>

</html>