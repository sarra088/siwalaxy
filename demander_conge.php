<?php
include 'config.php'; // Connexion à la base de données

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Récupérer les valeurs du formulaire
    $matricule = $_POST['matricule'] ?? ''; // Récupérer le matricule saisi par l'utilisateur
    $date_debut = $_POST['date_debut'] ?? '';
    $date_fin = $_POST['date_fin'] ?? '';

    // Vérifier si tous les champs sont remplis
    if (!empty($matricule) && !empty($date_debut) && !empty($date_fin)) {
        $stmt = $conn->prepare("INSERT INTO demandes_conge (matricule, date_debut, date_fin, statut) 
                                VALUES (:matricule, :date_debut, :date_fin, 'en attente')");

        if (
            $stmt->execute([
                'matricule' => $matricule,
                'date_debut' => $date_debut,
                'date_fin' => $date_fin
            ])
        ) {
            echo "<script>alert('Demande envoyée avec succès !'); window.location.href='pageacceuilemploye.php';</script>";
        } else {
            echo "<script>alert('Erreur lors de l\'envoi de la demande.'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('Veuillez remplir tous les champs.'); window.history.back();</script>";
    }
} else {
    echo "Requête invalide.";
}
?>