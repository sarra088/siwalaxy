<?php
session_start();
require 'config.php'; // Connexion à la base de données

$id_entreprise = $_SESSION['id_entreprise'];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!empty($_POST['nom'])) {
        // Ajouter un département
        $nom = $_POST['nom'];
        $stmt = $conn->prepare("INSERT INTO departements (id_entreprise, nom) VALUES (?, ?)");
        $stmt->execute([$id_entreprise, $nom]);

        // Renvoyer le nouveau département sous forme de bouton
        echo "<button onclick=\"window.location.href='departement.php?id={$conn->lastInsertId()}'\">" . htmlspecialchars($nom) . "</button>";
    } elseif (!empty($_POST['nom_supprimer'])) {
        // Supprimer un département
        $nom_supprimer = $_POST['nom_supprimer'];
        $stmt = $conn->prepare("DELETE FROM departements WHERE id_entreprise = ? AND nom = ?");
        $stmt->execute([$id_entreprise, $nom_supprimer]);
    }
} elseif (isset($_GET['id'])) {
    // Récupérer les employés du département
    $id_departement = $_GET['id'];
    $stmt = $conn->prepare("
        SELECT e.nom, e.prenom, e.matricule 
        FROM employes e 
        JOIN departements d ON e.id_departement = d.id_departement 
        WHERE d.id_departement = ? AND d.id_entreprise = ?
    ");
    $stmt->execute([$id_departement, $id_entreprise]);
    $employes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Afficher les employés
    if ($employes) {
        echo "<h2>Employés dans ce département :</h2>";
        echo "<ul>";
        foreach ($employes as $employe) {
            echo "<li>" . htmlspecialchars($employe['nom']) . " " . htmlspecialchars($employe['prenom']) . " (Matricule: " . htmlspecialchars($employe['matricule']) . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Aucun employé trouvé dans ce département.</p>";
    }
}
exit();
?>