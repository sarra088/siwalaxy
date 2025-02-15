<?php
session_start();
require 'config.php'; // Connexion à la base de données

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $matricule = $_POST['matricule'];
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $email = $_POST['email'];
    $num_tel = $_POST['num_tel'];
    $date_naissance = $_POST['date_naissance'];
    $titre = $_POST['titre'];

    // Mettre à jour les informations de l'employé
    $stmt = $conn->prepare("UPDATE employes SET nom = ?, prenom = ?, email = ?, num_tel = ?, date_naissance = ?, titre = ? WHERE matricule = ? AND id_entreprise = ?");
    $stmt->execute([$nom, $prenom, $email, $num_tel, $date_naissance, $titre, $matricule, $_SESSION['id_entreprise']]);

    echo "Informations de l'employé mises à jour avec succès.";
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modifier un Employé</title>
</head>

<body>

    <h1>Modifier un Employé</h1>
    <form method="post">
        Matricule de l'employé à modifier : <input type="text" name="matricule" required>
        <br>
        Nom : <input type="text" name="nom" required>
        <br>
        Prénom : <input type="text" name="prenom" required>
        <br>
        Email : <input type="email" name="email" required>
        <br>
        Numéro de téléphone : <input type="text" name="num_tel" required>
        <br>
        Date de naissance : <input type="date" name="date_naissance" required>
        <br>
        Titre : <input type="text" name="titre" required>
        <br>
        <button type="submit">Modifier</button>
    </form>

</body>

</html>