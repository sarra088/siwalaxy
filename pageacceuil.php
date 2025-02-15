<?php
session_start();
if (!isset($_SESSION['id_entreprise'])) {
    header('Location: login_entreprise.php');
    exit();
}
require 'config.php'; // Connexion à la base de données

$id_entreprise = $_SESSION['id_entreprise'];
$date_aujourdhui = date('Y-m-d');

// Récupérer la liste des employés et leur statut de présence
$employes = $conn->query("
    SELECT e.id_employe, e.nom, e.prenom, 
           COALESCE(p.statut, 'absent') AS statut
    FROM employes e 
    LEFT JOIN presences p 
    ON e.id_employe = p.id_employe AND p.date = '$date_aujourdhui' 
    WHERE e.id_entreprise = $id_entreprise
")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les demandes de congé en attente
$demandes = $conn->query("
    SELECT d.id, e.nom, e.prenom, d.date_debut, d.date_fin 
    FROM demandes_conge d
    JOIN employes e ON d.matricule = e.matricule
    WHERE d.statut = 'en attente'
")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les départements
$departements = $conn->query("
    SELECT * FROM departements WHERE id_entreprise = $id_entreprise
")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer le nom de l'entreprise
$entreprise = $conn->query("SELECT nom_societe FROM entreprises WHERE id_entreprise = $id_entreprise")->fetch(PDO::FETCH_ASSOC);
$nom_entreprise = $entreprise['nom_societe'];

// Compter les messages non lus
$stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM messages WHERE id_entreprise = :id_entreprise AND lu = 0");
$stmt->execute(['id_entreprise' => $id_entreprise]);
$unread_result = $stmt->fetch(PDO::FETCH_ASSOC);
$unread_count = $unread_result['unread_count'] ?? 0;
// Récupérer le dernier message
$lastMessage = $conn->query("
    SELECT m.*, e.nom, e.prenom 
    FROM messages m 
    LEFT JOIN employes e ON m.id_employe = e.id_employe 
    WHERE m.id_entreprise = $id_entreprise 
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
    $lastMessageFromEmployee = !$lastMessageFromCompany; // Inverse la valeur
}
// Gérer l'envoi de message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = $_POST['message'];
    $id_employe = null; // Si le message est envoyé par l'entreprise

    $stmt = $conn->prepare("INSERT INTO messages (id_employe, id_entreprise, message, lu) VALUES (:id_employe, :id_entreprise, :message, 0)");
    $stmt->execute(['id_employe' => $id_employe, 'id_entreprise' => $id_entreprise, 'message' => $message]);

    // Récupérer le dernier message inséré pour l'afficher
    $lastMessage = $conn->lastInsertId();
    $stmt = $conn->prepare("SELECT m.*, e.nom, e.prenom FROM messages m LEFT JOIN employes e ON m.id_employe = e.id_employe WHERE m.id = :id");
    $stmt->execute(['id' => $lastMessage]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    // Générer le HTML du message
    $styleClass = $msg['id_employe'] ? 'message-employe' : 'message-entreprise';
    echo "<div class='$styleClass'><strong>{$msg['nom']} {$msg['prenom']}:</strong> {$msg['message']} <span style='color: gray;'>(" . date('H:i', strtotime($msg['date_envoi'])) . ")</span></div>";
    exit(); // Terminer le script ici pour ne pas afficher le reste de la page
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Votre CSS ici */
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
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

        .presence-list {
            max-height: 80px;
            overflow-y: scroll;
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 20px;
        }

        .employe-status {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 5px 0;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-left: 5px;
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

        .message-entreprise {
            background-color: #003366;
            /* Bleu marine */
        }

        .message-employe {
            background-color: #87CEEB;
            /* Bleu ciel */
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

    <h1>Bienvenue
        <?= htmlspecialchars($nom_entreprise) ?> dans notre application SIWALAXY
    </h1>

    <!-- Présence des employés -->
    <div class="section">
        <h2>Liste de Présence</h2>
        <div class="presence-list">
            <?php foreach ($employes as $employe) { ?>
                <div class="employe-status">
                    <?= $employe['nom'] . ' ' . $employe['prenom'] ?>
                    <span class="status-dot"
                        style="background-color: <?= $employe['statut'] == 'present' ? 'green' : 'red' ?>;"></span>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Demandes de congé -->
    <div class="section">
        <h2>Demandes de congé</h2>
        <?php if (!empty($demandes)): ?>
            <?php foreach ($demandes as $demande): ?>
                <div id="demande-<?= $demande['id'] ?>">
                    <?= htmlspecialchars($demande['nom'] . ' ' . $demande['prenom']) ?>
                    (du <?= htmlspecialchars($demande['date_debut']) ?> au <?= htmlspecialchars($demande['date_fin']) ?>)
                    <form action="gerer_conge.php" method="post" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $demande['id'] ?>">
                        <input type="hidden" name="action" value="accepte">
                        <button type="submit">✔</button>
                    </form>
                    <form action="gerer_conge.php" method="post" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $demande['id'] ?>">
                        <input type="hidden" name="action" value="refuse">
                        <button type="submit">✖</button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Aucune demande en attente.</p>
        <?php endif; ?>
    </div>
    <!-- Liste des Congés Acceptés -->
    <div class="section">
        <h2>Liste des Congés Acceptés</h2>
        <table border="1">
            <tr>
                <th>Nom Employé</th>
                <th>Période</th>
                <th>Nombre de Jours</th>
            </tr>
            <?php
            $conges_acceptes = $conn->query("
                SELECT e.nom, e.prenom, c.date_debut, c.date_fin, c.nb_jours 
                FROM conges_acceptes c 
                JOIN employes e ON c.matricule = e.matricule
            ")->fetchAll(PDO::FETCH_ASSOC); foreach ($conges_acceptes as $conge) {
                echo "<tr>
                    <td>{$conge['nom']} {$conge['prenom']}</td>
                    <td>{$conge['date_debut']} au {$conge['date_fin']}</td>
                    <td>{$conge['nb_jours']}</td>
                  </tr>";
            }
            ?>
        </table>
    </div>

    <!-- Départements -->
    <div id="departements-list">
        <h2>Départements</h2>
        <?php foreach ($departements as $departement) { ?>
            <button onclick="window.location.href='departement.php?id=<?= $departement['id_departement'] ?>'">
                <?= htmlspecialchars($departement['nom']) ?>
            </button>
        <?php } ?>
    </div>

    <!-- Ajout ou suppression d'un département -->
    <div class="section">
        <!-- Formulaire d'ajout -->
        <form id="ajouter-departement-form">
            <input type="text" name="nom" placeholder="Nom du département" required>
            <button type="submit">Ajouter Département</button>
        </form>

        <!-- Formulaire de suppression -->
        <form id="supprimer-departement-form">
            <input type="text" name="nom_supprimer" placeholder="Nom du département à supprimer" required>
            <button type="submit">Supprimer Département</button>
        </form>
    </div>

    <!-- Jours Fériés -->
    <div class="section">
        <h2>Jours Fériés</h2>
        <div class="jours-feries">
            <?php
            $jours_feries = [
                "01-01" => "Jour de l'An",
                "03-20" => "Fête de l'Indépendance",
                "04-09" => "Journée des Martyrs",
                "05-01" => "Fête du Travail",
                "07-25" => "Fête de la République",
                "08-13" => "Fête de la Femme",
                "10-15" => "Fête de l'Évacuation",
                "12-17" => "Fête de la Révolution",
            ];
            foreach ($jours_feries as $date => $nom) { ?>
                <div class="jour-ferie">
                    <strong>
                        <?= $date ?>
                    </strong> -
                    <?= $nom ?>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- Bouton pour afficher la messagerie -->
    <button class="toggle-messaging" onclick="toggleMessaging()">
        Ouvrir Messagerie
        <?php if ($unread_count > 0): ?>
            <?php if (!$lastMessageFromCompany): ?>
                <span style="color: green; font-weight: bold;">●</span> <!-- Point vert pour l'entreprise -->
            <?php endif; ?>
        <?php endif; ?>
    </button>

    <!-- Discussion -->
    <div class="section" id="messaging-section" style="display: none;">
        <h2>Discussion</h2>
        <?php if ($unread_count > 0): ?>
            <?php if ($lastMessageFromCompany): ?>
                <div class="new-message-alert">
                </div>
            <?php else: ?>
                <div class="new-message-alert">Vous avez
                    <?= $unread_count ?> nouveau(x) message(s) de l'employé.
                </div>
            <?php endif; ?>
        <?php endif; ?>
        <form id="message-form" method="post" onsubmit="sendMessage(event)">
            <textarea name="message" placeholder="Écrivez votre message ici..." required></textarea>
            <button type="submit">Envoyer</button>
        </form>
        <div id="messages-list">
            <?php
            // Récupérer les messages
            $messages = $conn->query("
            SELECT m.*, e.nom, e.prenom 
            FROM messages m 
            LEFT JOIN employes e ON m.id_employe = e.id_employe 
            WHERE m.id_entreprise = $id_entreprise 
            ORDER BY m.date_envoi ASC
        ")->fetchAll(PDO::FETCH_ASSOC);

            foreach ($messages as $msg) {
                $styleClass = $msg['id_employe'] ? 'message-employe' : 'message-entreprise'; // Classe CSS selon l'expéditeur
                echo "<div class='$styleClass'><strong>{$msg['nom']} {$msg['prenom']}:</strong> {$msg['message']} <span style='color: gray;'>(" . date('H:i', strtotime($msg['date_envoi'])) . ")</span></div>";
            }
            ?>
        </div>
    </div>
    <!-- Footer -->
    <footer>
        <p>&copy;
            <?= date("Y") ?>
            <?= htmlspecialchars($nom_entreprise) ?>. Tous droits réservés.
        </p>
        <a href="https://www.instagram.com/siwa_laxy?igsh=djVkbHkyanF5d2dm&utm_source=qr">Instagram</a> |
        <a href="mailto:siwalaxy@gmail.com">Contact</a> |
        <a href="about.php">À propos</a>
    </footer>
    <script>
        function gererConge(id, action) {
            fetch('gerer_conge.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: id = ${ id } & action=${ action }
            })
                .then(response => response.text())
            .then(data => {
                alert(data);
                if (action === 'accepte' || action === 'refuse') {
                    document.getElementById('demande-' + id).remove();
                }
            });
        }
    </script>
    <script>
        document.getElementById('ajouter-departement-form').addEventListener('submit', function (event) {
            event.preventDefault(); // Empêche le rechargement de la page

            const formData = new FormData(this); // Récupère les données du formulaire

            fetch('departement.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (response.ok) {
                        return response.text(); // Récupère la réponse en texte
                    }
                    throw new Error('Erreur lors de l\'ajout du département');
                })
                .then(data => {
                    // Mettez à jour la liste des départements
                    document.getElementById('departements-list').innerHTML += data; // Ajoute le nouveau département
                    this.reset(); // Réinitialise le formulaire
                })
                .catch(error => {
                    alert(error.message);
                });
        });

        document.getElementById('supprimer-departement-form').addEventListener('submit', function (event) {
            event.preventDefault(); // Empêche le rechargement de la page

            const formData = new FormData(this); // Récupère les données du formulaire

            fetch('departement.php', {
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (response.ok) {
                        return response.text(); // Récupère la réponse en texte
                    }
                    throw new Error('Erreur lors de la suppression du département');
                })
                .then(data => {
                    // Mettez à jour la liste des départements
                    location.reload(); // Rafraîchit la page pour mettre à jour la liste
                })
                .catch(error => {
                    alert(error.message);
                });
        });
    </script>
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
            fetch('mark_messages_as_read_for_company.php', { // Utilisez le nouveau fichier ici
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `id_entreprise=${encodeURIComponent('<?= $id_entreprise ?>')}` // Envoyer l'ID de l'entreprise
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

            fetch('pageacceuil.php', { // Envoie le message à la même page
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