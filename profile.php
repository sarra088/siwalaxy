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
// Récupérer le nom et le prénom de l'employé
$stmt = $conn->prepare("SELECT nom, prenom FROM employes WHERE matricule = :matricule");
$stmt->execute(['matricule' => $matricule]);
$employeDetails = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employeDetails) {
    throw new Exception("Détails de l'employé introuvables !");
}

$nom = $employeDetails['nom'];
$prenom = $employeDetails['prenom'];
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
// Récupérer les informations de l'employé à partir de la base de données
$stmt = $conn->prepare("SELECT * FROM employes WHERE matricule = :matricule");
$stmt->execute(['matricule' => $matricule]);
$employe = $stmt->fetch(PDO::FETCH_ASSOC);

if ($employe) {
    $prenom = $employe['prenom'];
    $nom = $employe['nom'];
    $email = $employe['email'];
    $num_tel = $employe['num_tel'];
    $num_cin = $employe['num_cin'];
    $titre = $employe['titre'];
    // Ajoutez d'autres champs si nécessaire
} else {
    echo "Employé introuvable.";
}
?>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>

<body>
    <div class="profile-container">
        <div class="profile-card">
            <div class="profile-header">
                <div class="avatar-container">
                    <img src="icon.png" class="avatar" alt="Profile Avatar" />
                    <div class="online-status"></div>
                </div>
                <h1 class="profile-name">
                    <?= htmlspecialchars($prenom . ' ' . $nom) ?>
                </h1>
                <p class="profile-title">
                    <?= htmlspecialchars($titre) ?>
                </p> <!-- Titre de l'employé -->
            </div>

            <div class="profile-content">
                <div class="social-links">
                    <a href="#" class="social-link" data-tooltip="GitHub">
                        <i class="fab fa-github"></i>
                    </a>
                    <a href="#" class="social-link" data-tooltip="LinkedIn">
                        <i class="fab fa-linkedin"></i>
                    </a>
                    <a href="#" class="social-link" data-tooltip="Portfolio">
                        <i class="fas fa-briefcase"></i>
                    </a>
                </div>

                <div class="stats-container">
                    <div class="stat-item">
                        <div class="stat-value">
                            ID
                        </div> <!-- Numéro mATRICULE-->
                        <div class="stat-label">
                            <?= htmlspecialchars($matricule) ?>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">PHONE

                        </div> <!-- Numéro de téléphone -->
                        <div class="stat-label">
                            <?= htmlspecialchars($num_tel) ?>
                        </div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">
                            CIN
                        </div> <!-- Numéro CIN -->
                        <div class="stat-label">
                            <?= htmlspecialchars($num_cin) ?>
                        </div>
                    </div>

                </div>

                <button class="contact-btn">
                    <?= htmlspecialchars($email) ?>
                    <div class="hover-effect"></div>
                </button>
            </div>
        </div>
    </div>

    <style>
        :root {
            --primary: #7c3aed;
            --secondary: #2ed573;
            --accent: #ffa502;
            --bg: #0f172a;
            --surface: #1e293b;
            --text-primary: #f1f2f6;
            --text-secondary: #94a3b8;
        }

        .profile-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: var(--bg);
            font-family: "Inter", sans-serif;
        }

        .profile-card {
            background: var(--surface);
            border-radius: 20px;
            padding: 2rem;
            width: 360px;
            transform-style: preserve-3d;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.2);
            animation: cardEntrance 1s cubic-bezier(0.23, 1, 0.32, 1) forwards;
            opacity: 0;
        }

        @keyframes cardEntrance {
            0% {
                opacity: 0;
                transform: translateY(50px) rotateX(-30deg);
            }

            100% {
                opacity: 1;
                transform: translateY(0) rotateX(0);
            }
        }

        .avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 1.5rem;
            perspective: 1000px;
        }

        .avatar {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary);
            transform: rotateY(0deg);
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .avatar-container:hover .avatar {
            transform: rotateY(180deg);
        }

        .online-status {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 16px;
            height: 16px;
            background: var(--secondary);
            border: 2px solid var(--surface);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }
        }

        .profile-name {
            text-align: center;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        .profile-title {
            text-align: center;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .social-link {
            color: var(--text-secondary);
            font-size: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }

        .social-link:hover {
            color: var(--primary);
            transform: translateY(-3px);
        }

        .social-link::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .social-link:hover::after {
            opacity: 1;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: 12px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
        }

        .stat-value {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .contact-btn {
            position: relative;
            width: 100%;
            padding: 1rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .contact-btn:hover {
            transform: translateY(-2px);
        }

        .hover-effect {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            transform: translate(-50%, -50%);
            pointer-events: none;
            border-radius: 50%;
            animation: ripple 1s linear infinite;
        }

        @keyframes ripple {
            from {
                width: 0;
                height: 0;
                opacity: 0.5;
            }

            to {
                width: 500px;
                height: 500px;
                opacity: 0;
            }
        }
    </style>

    <script>
        document
            .querySelector(".contact-btn")
            .addEventListener("mousemove", function (e) {
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;

                this.style.setProperty("--x", `${x}px`);
                this.style.setProperty("--y", `${y}px`);
            });
    </script>
</body>

</html>