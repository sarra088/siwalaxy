<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_entreprise'])) {
    header('Location: login_entreprise.php');
    exit();
}

if (isset($_POST['id']) && isset($_POST['action'])) {
    $id_demande = $_POST['id'];
    $action = $_POST['action'];

    // Récupérer la demande de congé pour calculer le nombre de jours
    $stmt = $conn->prepare("SELECT matricule, date_debut, date_fin, DATEDIFF(date_fin, date_debut) + 1 AS nb_jours FROM demandes_conge WHERE id = ?");
    $stmt->execute([$id_demande]);
    $demande = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($demande) {
        $matricule = $demande['matricule'];
        $date_debut = $demande['date_debut'];
        $date_fin = $demande['date_fin'];
        $nb_jours = $demande['nb_jours'];

        if ($action == 'accepte') {
            // Ajouter la demande acceptée dans la table conges_acceptes
            $stmt = $conn->prepare("INSERT INTO conges_acceptes (matricule, date_debut, date_fin, nb_jours) VALUES (?, ?, ?, ?)");
            $stmt->execute([$matricule, $date_debut, $date_fin, $nb_jours]);
        }

        // Mettre à jour le statut de la demande (acceptée ou refusée)
        $stmt = $conn->prepare("UPDATE demandes_conge SET statut = ? WHERE id = ?");
        $stmt->execute([$action, $id_demande]);

        // Ajouter une notification pour l'employé
        $stmt = $conn->prepare("INSERT INTO notifications (matricule, message, vu) VALUES (?, ?, 0)");
        $message = ($action == 'accepte') ? "Votre demande de congé a été acceptée." : "Votre demande de congé a été refusée.";
        $stmt->execute([$matricule, $message]);

        $_SESSION['message'] = "Action effectuée avec succès.";
    } else {
        $_SESSION['message'] = "Erreur: demande introuvable.";
    }
}
if ($action == 'refuse') {
    $stmt = $conn->prepare("DELETE FROM demandes_conge WHERE id = ?");
    $stmt->execute([$id_demande]);
}
if ($action == 'accepte') {
    $stmt = $conn->prepare("DELETE FROM demandes_conge WHERE id = ?");
    $stmt->execute([$id_demande]);
}

header('Location: pageacceuil.php');
exit();
?>