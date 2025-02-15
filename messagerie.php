<?php
session_start();
require 'config.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['id_entreprise']) && !isset($_SESSION['matricule'])) {
    header('Location: login.php');
    exit();
}

// Déterminer si c'est une entreprise ou un employé
$is_entreprise = isset($_SESSION['id_entreprise']);
$id_utilisateur = $is_entreprise ? $_SESSION['id_entreprise'] : $_SESSION['matricule'];
$expediteur_type = $is_entreprise ? 'entreprise' : 'employe';

// Récupérer les messages
$stmt = $conn->prepare("
    SELECT messages.*, 
        CASE 
            WHEN messages.expediteur = 'employe' THEN employes.nom
            WHEN messages.expediteur = 'entreprise' THEN entreprises.nom_societe
            ELSE 'Inconnu'
        END AS expediteur_nom
    FROM messages
    LEFT JOIN employes ON messages.id_expediteur = employes.id_employe AND messages.expediteur = 'employe'
    LEFT JOIN entreprises ON messages.id_expediteur = entreprises.id_entreprise AND messages.expediteur = 'entreprise'
    WHERE messages.id_destinataire = :id_destinataire
    ORDER BY messages.date_envoi DESC
");
$stmt->execute(['id_destinataire' => $id_utilisateur]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Messagerie Interne</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <h2>Messagerie</h2>

    <?php if ($lastMessage): ?>
        <div class="last-message">
            <strong>
                <?= htmlspecialchars($lastMessage['nom'] . ' ' . $lastMessage['prenom']) ?>:
            </strong>
            <?= htmlspecialchars($lastMessage['message']) ?>
            <span class="status-dot" style="background-color: <?= $lastMessageFromEmployee ? 'green' : 'gray' ?>;"></span>
        </div>
    <?php endif; ?>
    <div id="messages">
        <!-- Affichage des messages -->
        <?php if (empty($messages)): ?>
            <p>Aucun message à afficher.</p>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <p><strong>
                        <?= htmlspecialchars($message['expediteur_nom']) ?> :
                    </strong>
                    <?= htmlspecialchars($message['message']) ?> (
                    <?= htmlspecialchars($message['date_envoi']) ?>)
                </p>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <form method="POST" action="envoyer_message.php">
        <textarea name="message" required></textarea>
        <input type="hidden" name="expediteur" value="<?= htmlspecialchars($expediteur_type) ?>">
        <button type="submit">Envoyer</button>
    </form>

    <style>
        #messages {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
        }

        .message {
            padding: 5px;
            margin: 5px 0;
            border-radius: 5px;
        }

        .employe {
            background-color: #f1f1f1;
            color: black;
        }

        .entreprise {
            background-color: #007bff;
            color: white;
        }
    </style>

</body>

</html>