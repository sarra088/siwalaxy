<?php
// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['matricule'])) {
    header('Location: login_employe.php');
    exit();
}

// Inclure le fichier de configuration
require 'config.php';

// Récupérer la matricule depuis la session
$matricule = $_SESSION['matricule'];

try {
    // Récupérer l'ID de l'employé à partir du matricule
    $stmt = $conn->prepare("SELECT id_employe FROM employes WHERE matricule = :matricule");
    $stmt->execute(['matricule' => $matricule]);
    $employe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employe) {
        throw new Exception("Employé introuvable !");
    }

    $id_employe = $employe['id_employe']; // Stocker l'ID employé

    // Récupérer le nombre de notifications non lues
    $stmt = $conn->prepare("SELECT COUNT(*) AS notif_count FROM notifications WHERE matricule = :matricule AND vu = 0");
    $stmt->execute(['matricule' => $matricule]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notif_count = $result['notif_count'] ?? 0;
    // Récupérer le dernier message
    $lastMessage = $conn->query("
    SELECT m.*, e.nom, e.prenom 
    FROM messages m 
    LEFT JOIN employes e ON m.id_employe = e.id_employe 
    WHERE m.id_entreprise = (SELECT id_entreprise FROM employes WHERE matricule = '$matricule') 
    ORDER BY m.date_envoi DESC 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

    // Déterminer si le dernier message est de l'entreprise ou de l'employé
    if (!$lastMessage) {
        // Si aucun message n'est trouvé, on met des valeurs par défaut
        $lastMessageFromCompany = false;
        $lastMessageFromEmployee = false;
    } else {
        $lastMessageFromCompany = $lastMessage['id_employe'] === null; // Vérifie si c'est un message de l'entreprise

    }
    // Compter les messages non lus
    $stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM messages WHERE id_entreprise = (SELECT id_entreprise FROM employes WHERE matricule = :matricule) AND lu = 0");
    $stmt->execute(['matricule' => $matricule]);
    $unread_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = $unread_result['unread_count'] ?? 0;

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
    exit();
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
    exit();
}

// Récupérer les messages
$messages = $conn->query("
    SELECT m.*, e.nom, e.prenom 
    FROM messages m 
    LEFT JOIN employes e ON m.id_employe = e.id_employe 
    WHERE m.id_entreprise = (SELECT id_entreprise FROM employes WHERE matricule = '$matricule') 
    ORDER BY m.date_envoi ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Gérer l'envoi de message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = $_POST['message'];
    $id_entreprise = (int) $conn->query("SELECT id_entreprise FROM employes WHERE matricule = '$matricule'")->fetchColumn(); // ID de l'entreprise

    try {
        $stmt = $conn->prepare("INSERT INTO messages (id_employe, id_entreprise, message, lu) VALUES (:id_employe, :id_entreprise, :message, 0)");
        $stmt->execute(['id_employe' => $id_employe, 'id_entreprise' => $id_entreprise, 'message' => $message]);

        // Récupérer le message inséré pour l'afficher
        $newMessage = [
            'nom' => 'Vous',
            // Nom de l'employé
            'prenom' => '',
            // Prénom de l'employé
            'message' => $message,
            'date_envoi' => date('Y-m-d H:i:s'),
            // Date actuelle
            'id_employe' => $id_employe
        ];

        // Retourner le message sous forme de HTML
        echo "<div class='message-employe'><strong>{$newMessage['nom']}:</strong> {$newMessage['message']} <span style='color: gray;'>(" . date('H:i', strtotime($newMessage['date_envoi'])) . ")</span></div>";
        exit(); // Terminer le script après l'envoi
    } catch (PDOException $e) {
        echo "Erreur lors de l'envoi du message : " . $e->getMessage();
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Page Employé</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        a {
            color: #000;
            text-decoration: none;
            position: relative;
        }

        .notification-icon {
            position: relative;
            display: inline-block;
            margin-right: 5px;
        }

        .notification-icon .point {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 10px;
            height: 10px;
            background-color: green;
            border-radius: 50%;
            display:
                <?= ($notif_count > 0) ? 'block' : 'none' ?>
            ;
        }

        .new-message-alert {
            color: red;
            font-weight: bold;
            display:
                <?= ($unread_count > 0) ? 'block' : 'none' ?>
            ;
            /* Affiche le message si des messages non lus */
            margin-bottom: 10px;
        }

        .section {
            margin: 20px 0;
        }

        textarea {
            width: 100%;
            height: 50px;
        }

        #messages-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 10px;
        }

        #messages-list div {
            margin-bottom: 10px;
            padding: 10px;
            border-radius: 8px;
            color: white;
        }

        .message-employe {
            background-color: #87CEEB;
            /* Bleu ciel */
        }

        .message-entreprise {
            background-color: #003366;
            /* Bleu marine */
        }

        .toggle-messaging {
            cursor: pointer;
            background-color: #007BFF;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 5px;
            margin: 10px 0;
        }

        .toggle-messaging:hover {
            background-color: #0056b3;
        }
    </style>
</head>

<body>
    <h1>Bienvenue sur votre espace employé</h1>

    <a href="page_notification_employe.php" class="notification-icon">
        📩 Notifications
        <div class="point"></div>
    </a><br>

    <!-- Bouton pour afficher la messagerie -->
    <button class="toggle-messaging" onclick="toggleMessaging()">
        Ouvrir Messagerie
        <?php if ($unread_count > 0 && $lastMessageFromCompany): ?>
            <span style="color: green; font-weight: bold;">●</span>
            <!-- Point vert uniquement si dernier message de l'entreprise -->
        <?php endif; ?>

    </button>

    <!-- Discussion -->
    <div class="section" id="messaging-section" style="display: none;">
        <h2>Discussion</h2>
        <?php if ($unread_count > 0): ?>
            <?php if ($lastMessageFromCompany): ?>
                <div class="new-message-alert">Vous avez
                    <?= $unread_count ?> nouveau(x) message(s) de l'entreprise.
                </div>

            <?php endif; ?>
        <?php endif; ?>
        <form id="message-form" method="post" onsubmit="sendMessage(event)">
            <textarea name="message" placeholder="Écrivez votre message ici..." required></textarea>
            <button type="submit">Envoyer</button>
        </form>
        <div id="messages-list">
            <?php
            foreach ($messages as $msg) {
                $styleClass = $msg['id_employe'] ? 'message-employe' : 'message-entreprise'; // Classe CSS selon l'expéditeur
                echo "<div class='$styleClass'><strong>{$msg['nom']} {$msg['prenom']}:</strong> {$msg['message']} <span style='color: gray;'>(" . date('H:i', strtotime($msg['date_envoi'])) . ")</span></div>";
            }
            ?>
        </div>
    </div>

    <script>
        function toggleMessaging() {
            const messagingSection = document.getElementById('messaging-section');
            if (messagingSection.style.display === 'none') {
                messagingSection.style.display = 'block';
                markMessagesAsRead(); // Marquer les messages comme lus lorsque la section est ouverte
            } else {
                messagingSection.style.display = 'none';
            }
        }

        function markMessagesAsRead() {
            fetch('mark_messages_as_read.php', { // Créez un fichier PHP pour marquer les messages comme lus
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `matricule=${encodeURIComponent('<?= $matricule ?>')}` // Envoyer le matricule pour identifier l'utilisateur
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur lors de la mise à jour des messages');
                    }
                })
                .catch(error => {
                    console.error(error);
                });
        }

        function sendMessage(event) {
            event.preventDefault(); // Empêche le rechargement de la page

            const formData = new FormData(event.target); // Récupère les données du formulaire

            fetch('pageacceuilemploye.php', { // Envoie le message à la même page
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (response.ok) {
                        return response.text(); // Récupère la réponse en texte
                    }
                    throw new Error('Erreur lors de l\'envoi du message');
                })
                .then(data => {
                    // Ajoute le nouveau message à la liste des messages
                    const messagesList = document.getElementById('messages-list');
                    messagesList.innerHTML += data; // Ajoute le nouveau message
                    event.target.reset(); // Réinitialise le formulaire
                })
                .catch(error => {
                    alert(error.message);
                });
        }
    </script>
</body>

</html>