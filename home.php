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
<?php

if (!isset($_SESSION['id_entreprise'])) {
    header('Location: login_entreprise.php');
    exit();
}
require 'config.php'; // Connexion à la base de données

$employes = $conn->prepare("SELECT matricule, nom, prenom, email, num_tel, date_naissance, titre FROM employes WHERE id_entreprise = ?");
$employes->execute([$id_entreprise]);
$employes = $employes->fetchAll(PDO::FETCH_ASSOC);
?>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_employe'], $_POST['date'], $_POST['heures_travaillees'])) {
    $id_employe = $_POST['id_employe'];
    $date = $_POST['date'];
    $heures_travaillees = $_POST['heures_travaillees'];

    // Vérifiez que les heures travaillées sont valides
    if ($heures_travaillees < 0 || $heures_travaillees > 24) {
        echo "❌ Les heures travaillées doivent être comprises entre 0 et 24.";
        exit();
    }

    // Insertion dans la base de données
    $stmt = $conn->prepare("INSERT INTO feuilles_temps (id_employe, date, heures_travaillees) VALUES (:id_employe, :date, :heures_travaillees)");
    $stmt->execute(['id_employe' => $id_employe, 'date' => $date, 'heures_travaillees' => $heures_travaillees]);

    header('Location: home.php'); // Rediriger après l'ajout
    exit();
} ?>
<?php

require 'config.php'; // Connexion à la base de données

// Vérifier si l'entreprise est connectée
if (!isset($_SESSION['id_entreprise'])) {
    header('Location: login_entreprise.php');
    exit();
}

$id_entreprise = $_SESSION['id_entreprise'];

// Récupérer les départements
$departements = $conn->prepare("SELECT id_departement, nom FROM departements WHERE id_entreprise = ?");
$departements->execute([$id_entreprise]);
$departements = $departements->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la liste des employés
$employes = $conn->prepare("SELECT * FROM employes WHERE id_entreprise = ?");
$employes->execute([$id_entreprise]);
$employes = $employes->fetchAll(PDO::FETCH_ASSOC);

// Gérer les requêtes POST : ajout, modification et suppression d'un employé
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Vérification des champs requis pour l'ajout ou la modification
    if (isset($_POST['editMode']) && $_POST['editMode'] == "1") {
        // Mode d'édition
        $matricule = $_POST['matricule'] ?? '';
        $nom = $_POST['nom'] ?? '';
        $prenom = $_POST['prenom'] ?? '';
        $num_cin = $_POST['num_cin'] ?? '';
        $email = $_POST['email'] ?? '';
        $num_tel = $_POST['num_tel'] ?? '';
        $id_departement = $_POST['id_departement'] ?? '';
        $date_naissance = $_POST['date_naissance'] ?? '';
        $titre = $_POST['titre'] ?? '';
        $oldMatricule = $_POST['oldMatricule'] ?? '';

        // Vérification des formats des champs
        if (!preg_match('/^\d{4}$/', $matricule)) {
            echo "❌ Le matricule doit contenir exactement 4 chiffres.";
            exit();
        }
        if (!preg_match('/^\d{8}$/', $num_cin)) {
            echo "❌ Le numéro CIN doit contenir exactement 8 chiffres.";
            exit();
        }
        if (!preg_match('/^\d{8}$/', $num_tel)) {
            echo "❌ Le numéro de téléphone doit contenir exactement 8 chiffres.";
            exit();
        }

        // Mise à jour de l'employé
        $stmt = $conn->prepare("UPDATE employes SET matricule = ?, nom = ?, prenom = ?, email = ?, num_tel = ?, date_naissance = ?, titre = ?, id_departement = ? WHERE matricule = ? AND id_entreprise = ?");
        if ($stmt->execute([$matricule, $nom, $prenom, $email, $num_tel, $date_naissance, $titre, $id_departement, $oldMatricule, $id_entreprise])) {
            // Rediriger pour rafraîchir la page
            header("Location: home.php");
            exit();
        } else {
            echo "❌ Erreur lors de la mise à jour.";
        }
    } elseif (isset($_POST['delete']) && $_POST['delete'] == "1") {
        // Mode de suppression
        $matricule = $_POST['matricule'] ?? '';

        // Vérifiez si le matricule est défini et non vide
        if (empty($matricule)) {
            echo "❌ Le matricule doit être fourni.";
            exit();
        }

        // Suppression de l'employé
        $stmt = $conn->prepare("DELETE FROM employes WHERE matricule = ? AND id_entreprise = ?");
        if ($stmt->execute([$matricule, $id_entreprise])) {
            // Rediriger pour rafraîchir la page
            header("Location: home.php");
            exit();
        } else {
            echo "❌ Erreur lors de la suppression.";
        }
    } else {
        // Ajout d'un nouvel employé
        $matricule = $_POST['matricule'] ?? '';
        $nom = $_POST['nom'] ?? '';
        $prenom = $_POST['prenom'] ?? '';
        $num_cin = $_POST['num_cin'] ?? '';
        $email = $_POST['email'] ?? '';
        $num_tel = $_POST['num_tel'] ?? '';
        $id_departement = $_POST['id_departement'] ?? '';
        $date_naissance = $_POST['date_naissance'] ?? '';
        $titre = $_POST['titre'] ?? '';

        // Vérification des formats des champs
        if (!preg_match('/^\d{4}$/', $matricule)) {
            echo "❌ Le matricule doit contenir exactement 4 chiffres.";
            exit();
        }
        if (!preg_match('/^\d{8}$/', $num_cin)) {
            echo "❌ Le numéro CIN doit contenir exactement 8 chiffres.";
            exit();
        }
        if (!preg_match('/^\d{8}$/', $num_tel)) {
            echo "❌ Le numéro de téléphone doit contenir exactement 8 chiffres.";
            exit();
        }

        // Ajout d'un nouvel employé
        $stmt = $conn->prepare("INSERT INTO employes (matricule, nom, prenom, num_cin, email, num_tel, id_departement, date_naissance, titre, id_entreprise) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$matricule, $nom, $prenom, $num_cin, $email, $num_tel, $id_departement, $date_naissance, $titre, $id_entreprise])) {
            // Rediriger pour rafraîchir la page
            header("Location: home.php");
            exit();
        } else {
            echo "❌ Erreur lors de l'ajout.";
        }
    }
}
?>
<?php

if (!isset($_SESSION['id_entreprise'])) {
    header('Location: login_entreprise.php');
    exit();
}

require 'config.php'; // Connexion à la base de données

$id_entreprise = $_SESSION['id_entreprise'];

// Récupérer les feuilles de temps des employés
$feuilles_temps = $conn->query("
    SELECT e.nom, e.prenom, f.date, f.heures_travaillees 
    FROM feuilles_temps f
    JOIN employes e ON f.id_employe = e.id_employe
    WHERE e.id_entreprise = $id_entreprise
    ORDER BY f.date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Gérer l'ajout d'une nouvelle feuille de temps
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_employe'], $_POST['date'], $_POST['heures_travaillees'])) {
    $id_employe = $_POST['id_employe'];
    $date = $_POST['date'];
    $heures_travaillees = $_POST['heures_travaillees'];

    $stmt = $conn->prepare("INSERT INTO feuilles_temps (id_employe, date, heures_travaillees) VALUES (:id_employe, :date, :heures_travaillees)");
    $stmt->execute(['id_employe' => $id_employe, 'date' => $date, 'heures_travaillees' => $heures_travaillees]);

    header('Location: gestion_feuilles_temps.php'); // Rediriger après l'ajout
    exit();
}

// Récupérer la liste des employés pour le formulaire
$emp = $conn->query("SELECT id_employe, nom, prenom FROM employes WHERE id_entreprise = $id_entreprise")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php

if (!isset($_SESSION['id_entreprise'])) {
    header('Location: login_entreprise.php');
    exit();
}
require 'config.php'; // Connexion à la base de données

$id_entreprise = $_SESSION['id_entreprise'];
$date_aujourdhui = date('Y-m-d');

// Récupérer la liste des employés et leur statut de présence
$e = $conn->query("
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
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siwalaxy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" >
</head>
<style>
    /* styles.css */
    /* ================ CORE VARIABLES ================ */
    :root {
        /* Colors */
        --primary: #6366f1;
        --primary-dark: #4f46e5;
        --accent: #10b981;
        --background: #f8fafc;
        --text: #1e293b;
        --text-light: #ffffff;
        --error: #dc2626;

        /* Spacing */
        --space-sm: 0.5rem;
        --space-md: 1rem;
        --space-lg: 2rem;

        /* Shadows */
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);

        /* Transitions */
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

        /* Layout */
        --sidebar-width: 280px;
        --dashboard-width: 300px;
    }

    /* ================ BASE STYLES ================ */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', system-ui, sans-serif;
    }

    body {
        background: linear-gradient(195deg, #1a1b2f, #221f4b);
        line-height: 1.6;
        color: var(--text-light);
    }

    /* ================ LAYOUT STRUCTURE ================ */
    .dashboard-container {
        display: flex;
        min-height: 100vh;
        position: relative;
    }

    /* Sidebars */
    .sidebar {
        width: var(--sidebar-width);
        background: linear-gradient(195deg, #1a1b2f, #2d2b50);
        position: fixed;
        height: 100vh;
        transform: translateX(-100%);
        transition: transform 0.3s var(--transition);
        z-index: 1000;
    }

    .dashboard-sidebar {
        width: var(--dashboard-width);
        background: var(--background);
        position: fixed;
        right: -100%;
        height: 100vh;
        box-shadow: var(--shadow-md);
        transition: right 0.3s var(--transition);
        z-index: 1000;
    }

    .main-content {
        flex: 1;
        padding: var(--space-lg);
        transition: margin 0.3s var(--transition);
        min-height: 100vh;
    }

    /* ================ COMPONENTS ================ */
    /* Navigation */
    .sidebar-menu li {
        display: flex;
        align-items: center;
        padding: var(--space-md);
        margin: var(--space-sm) 0;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: var(--transition);
    }

    /* Cards */
    .card {
        background: var(--background);
        padding: var(--space-lg);
        border-radius: 1rem;
        box-shadow: var(--shadow-sm);
        transition: var(--transition);
    }

    /* Tables */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        background: var(--background);
        box-shadow: var(--shadow-sm);
    }

    /* Forms */
    .form-control {
        padding: var(--space-sm);
        border: 2px solid #e2e8f0;
        border-radius: 0.5rem;
        transition: var(--transition);
    }

    /* Buttons */
    .btn {
        padding: var(--space-sm) var(--space-md);
        border: none;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: var(--transition);
    }

    /* ================ UTILITIES ================ */
    .flex-center {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .slide-in {
        animation: slideIn 0.4s ease-out;
    }

    .fade-in {
        animation: fadeIn 0.3s ease-in;
    }

    .active {
        display: block !important;
        opacity: 1 !important;
    }

    /* ================ ANIMATIONS ================ */
    @keyframes slideIn {
        from {
            transform: translateX(-100px);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* ================ RESPONSIVE DESIGN ================ */
    @media (max-width: 768px) {

        .sidebar,
        .dashboard-sidebar {
            width: 100%;
        }

        .main-content {
            padding: var(--space-md);
            margin: 0 !important;
        }

        .card {
            padding: var(--space-md);
        }
    }

    :root {
        --primary-color: #6366f1;
        --secondary-color: #4f46e5;
        --accent-color: #10b981;
        --background-light: #f8fafc;
        --text-dark: #1e293b;
        --text-light: #ffffff;
        --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.12);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    body {
        background-image: linear-gradient(90deg, #071751, #480048, #c04848);
        line-height: 1.6;
    }

    .dashboard-container {
        display: flex;
        min-height: 100vh;
        position: relative;
        background: linear-gradient(195deg, #1a1b2f, #221f4b);
    }

    /* Enhanced Sidebar */
    .sidebar {
        width: 280px;
        background: linear-gradient(195deg, #1a1b2f, #2d2b50);
        color: #fff;
        padding: 1.5rem;
        position: fixed;
        height: 100vh;
        transform: translateX(0);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        z-index: 1000;
    }

    .sidebar-header {
        display: flex;
        align-items: center;
        margin-bottom: 2rem;
        padding: 0.5rem;


    }



    .sidebar-menu li {
        list-style: none;
        padding: 0.75rem 1rem;
        margin: 0.25rem 0;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: var(--transition);
        display: flex;
        align-items: center;
        position: relative;
        overflow: hidden;
    }

    .sidebar-menu li::before {
        content: '';
        position: absolute;
        left: -100%;
        width: 4px;
        height: 100%;
        background: var(--primary-color);
        transition: left 0.3s ease;
    }

    .sidebar-menu li:hover {
        background: rgba(69, 135, 222, 0.05);
        transform: translateX(8px);
    }

    .sidebar-menu li:hover::before {
        left: 0;
    }

    .sidebar-menu li.active {
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        box-shadow: var(--shadow-sm);
    }

    .sidebar-menu li i {
        width: 32px;
        font-size: 1rem;
        margin-right: 0.75rem;
    }

    /* Main Content Enhancements */
    .main-content {
        flex: 1;
        padding: 2rem;
        margin-left: 280px;
        transition: margin 0.3s ease;
    }

    .top-nav {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding: 1rem 1.5rem;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        border-radius: 1rem;
        box-shadow: var(--shadow-md);
    }

    .search-box {
        background: var(--background-light);
        padding: 0.5rem 1rem;
        border-radius: 2rem;
        display: flex;
        align-items: center;
        transition: var(--transition);
        border: 1px solid #e2e8f0;
    }

    .search-box:focus-within {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }

    .search-box input {
        border: none;
        outline: none;
        margin-left: 0.75rem;
        background: transparent;
        width: 240px;
        font-size: 0.9rem;
    }

    .nav-icons {
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }

    .notification-bell {
        position: relative;
        cursor: pointer;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: var(--transition);
    }

    .notification-bell:hover {
        background: #167de4;
    }

    .notification-count {
        position: absolute;
        top: -2px;
        right: -2px;
        background: #ef4444;
        color: white;
        border-radius: 50%;
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .profile-pic {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        transition: transform 0.3s ease;
        border: 2px solid var(--primary-color);
    }

    .profile-pic:hover {
        transform: scale(1.05);
    }

    /* Enhanced Cards */
    .card {
        background: white;
        padding: 1.5rem;
        border-radius: 1rem;
        box-shadow: var(--shadow-md);
        transition: var(--transition);
        border: 1px solid #f1f5f9;
    }

    .card:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    }

    /* Modern Animations */
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes fadeInLeft {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .content-section {
        display: none;
        opacity: 0;
        transform: translateY(20px);
        animation: slideIn 0.4s ease forwards;
    }

    .content-section.active {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    /* Enhanced Notifications */
    .notifications-panel {
        position: fixed;
        right: -320px;
        top: 0;
        width: 320px;
        height: 100%;
        background: rgba(56, 101, 225, 0.95);
        backdrop-filter: blur(10px);
        box-shadow: -2px 0 15px rgba(0, 0, 0, 0.05);
        transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 1.5rem;
        z-index: 1000;
    }

    .notifications-panel.active {
        right: 0;
    }

    .notification-item {
        padding: 1rem;
        background: rgb(217, 217, 217);
        margin-bottom: 1rem;
        border-radius: 0.75rem;
        box-shadow: var(--shadow-sm);
        animation: notificationEntry 0.3s ease forwards;
        display: flex;
        align-items: center;
        gap: 1rem;
        position: relative;
    }

    @keyframes notificationEntry {
        from {
            opacity: 0;
            transform: translateX(100%);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    /* Modern Chat Interface */
    .chat-container {
        background: white;
        border-radius: 1rem;
        padding: 1.5rem;
        height: 70vh;
        display: flex;
        flex-direction: column;
        box-shadow: var(--shadow-md);
    }

    .chat-messages {
        flex: 1;
        overflow-y: auto;
        margin-bottom: 1.5rem;
        padding-right: 0.5rem;
    }

    .message {
        padding: 0.75rem 1rem;
        margin-bottom: 1rem;
        border-radius: 1rem;
        max-width: 70%;
        animation: messageEntry 0.3s ease;
        background: var(--background-light);
        position: relative;
    }

    .message::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 20px;
        border-width: 8px 8px 0 0;
        border-style: solid;
        border-color: var(--background-light) transparent transparent transparent;
    }

    @keyframes messageEntry {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Add these CSS styles */
    .year-calendar {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 2rem;
        margin-top: 1.5rem;
    }

    .month-container {
        background: white;
        padding: 1.5rem;
        border-radius: 1rem;
        box-shadow: var(--shadow-md);
    }

    .month-header {
        text-align: center;
        margin-bottom: 1rem;
        font-weight: 600;
        color: var(--text-dark);
    }

    .calendar-container {
        width: 300px;
        overflow: hidden;
        position: relative;
    }

    .calendar-slides {
        display: flex;
        transition: transform 0.5s ease-in-out;
    }

    .calendar-month {
        width: 100%;
        flex-shrink: 0;
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
    }

    .calendar-day {
        padding: 8px;
        text-align: center;
        border: 1px solid #ddd;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.25rem;
    }

    .calendar-day {
        padding: 0.5rem;
        text-align: center;
        border-radius: 0.5rem;
        cursor: pointer;
        transition: var(--transition);
        font-size: 0.9rem;
        border: 1px solid transparent;
    }

    .calendar-day:hover:not(.selected) {
        background: var(--primary-color);
        color: white;
    }

    .calendar-day.selected {
        background: var(--secondary-color);
        color: white;
        animation: selectDate 0.3s ease;
    }

    .calendar-day.empty {
        visibility: hidden;
    }

    @keyframes selectDate {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(0.9);
        }

        100% {
            transform: scale(1);
        }
    }

    /* Loading Animations */
    @keyframes pulse {
        50% {
            opacity: 0.5;
        }
    }

    .loading {
        animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .main-content {
            margin-left: 0;
        }

        .search-box input {
            width: 160px;
        }
    }

    .management-card {
        position: relative;
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .action-buttons button {
        padding: 6px 12px;
        border: none;
        border-radius: 8px;
        margin: 0 4px;
        transition: transform 0.2s ease;
    }

    .edit-btn {
        background: #e3f2fd;
        color: #1976d2;
    }

    .delete-btn {
        background: #ffebee;
        color: #d32f2f;
    }

    .approve-btn {
        background: #e8f5e9;
        color: #2e7d32;
    }

    .reject-btn {
        background: #ffebee;
        color: #d32f2f;
    }

    .planner-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 10px;
    }

    .time-slot {
        border: 1px solid #eee;
        padding: 10px;
        border-radius: 8px;
        min-height: 80px;
    }

    .fiche-item {
        background: #f5f5f5;
        padding: 8px;
        border-radius: 6px;
        display: flex;
        justify-content: space-between;
        cursor: move;
    }

    .document-list {
        max-height: 300px;
        overflow-y: auto;
    }

    .doc-item {
        display: flex;
        align-items: center;
        padding: 10px;
        border-bottom: 1px solid #eee;
    }

    .rating i {
        color: #ffd600;
        cursor: pointer;
        transition: color 0.2s ease;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
    }

    .modal-content {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        width: 400px;
        animation: modalSlide 0.3s ease-out;
    }

    @keyframes modalSlide {
        from {
            transform: translateY(-50px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    /* Drag and drop styles */
    .dragging {
        opacity: 0.5;
        transform: scale(0.98);
    }

    :root {
        --primary: #2563eb;
        --primary-hover: #1d4ed8;
        --success: #22c55e;
        --success-hover: #16a34a;
        --warning: #eab308;
        --warning-hover: #ca8a04;
        --danger: #dc2626;
        --danger-hover: #b91c1c;
        --text-main: #1e293b;
        --text-light: #64748b;
        --border: #e2e8f0;
    }

    /* Animations */
    @keyframes slideIn {
        from {
            transform: translateX(-100px);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes popIn {
        from {
            transform: scale(0.95);
            opacity: 0;
        }

        to {
            transform: scale(1);
            opacity: 1;
        }
    }

    .slide-in {
        animation: slideIn 0.6s ease-out;
    }

    .pop-in {
        animation: popIn 0.4s ease-out;
    }

    /* Table Styling */
    .modern-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        border-radius: 12px;
        overflow: hidden;
    }

    .modern-table th {
        background: #2c3e50;
        color: white;
        padding: 15px;
        text-align: left;
    }

    .modern-table td {
        padding: 12px 15px;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.3s ease;
    }

    .modern-table tr:hover td {
        background: #f8f9fa;
    }

    .modern-table tr:last-child td {
        border-bottom: none;
    }

    /* Action Buttons */
    .actions {
        display: flex;
        gap: 10px;
    }

    .icon-btn {
        border: none;
        background: none;
        cursor: pointer;
        padding: 5px;
        transition: transform 0.2s;
    }

    .icon-btn:hover {
        transform: scale(1.1);
    }

    .edit-btn {
        color: #3498db;
    }

    .delete-btn {
        color: #e74c3c;
    }

    /* Header Styling */
    .header-title {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 10px;
    }

    .header-title h1 {
        margin: 0;
        font-size: 1.8em;
    }

    .count {
        font-weight: normal;
        color: #7f8c8d;
    }

    .add-btn {
        background: #27ae60;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 25px;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s;
    }

    .add-btn:hover {
        transform: translateY(-2px);
        cursor: pointer;
        background-color: #033f1b;
    }

    .table-container {
        margin-top: 20px;
        border-radius: 12px;
        overflow: hidden;
    }

    .animated {
        animation-duration: 1s;
        animation-fill-mode: both;
    }

    @keyframes bounce {

        0%,
        20%,
        50%,
        80%,
        100% {
            transform: translateY(0);
        }

        40% {
            transform: translateY(-15px);
        }

        60% {
            transform: translateY(-7px);
        }
    }

    .bounce {
        animation-name: bounce;
    }

    /* Form Overlay Styles */
    .form-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        justify-content: center;
        align-items: center;
        z-index: 1000;
    }

    /* Form Animation */
    @keyframes slideDown {
        from {
            transform: translateY(-100px);
            opacity: 0;
        }

        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .add-form-container {
        background: white;
        width: 700px;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        animation: slideDown 0.4s ease-out;
    }

    .form-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        border-bottom: 2px solid #eee;
        padding-bottom: 15px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        margin-bottom: 15px;
    }

    .form-group label {
        margin-bottom: 8px;
        color: #34495e;
        font-weight: 500;
    }

    .form-group input {
        padding: 10px;
        border: 2px solid #bdc3c7;
        border-radius: 6px;
        transition: border-color 0.3s ease;
    }

    .form-group input:focus {
        border-color: #3498db;
        outline: none;
    }

    .form-actions {
        margin-top: 25px;
        display: flex;
        justify-content: flex-end;
        gap: 15px;
    }

    .form-actions:hover {
        font-size: 20px;
    }

    .save-btn,
    .cancel-btn {
        padding: 12px 25px;
        border: none;
        border-radius: 25px;
        cursor: pointer;
        transition: transform 0.2s, opacity 0.2s;
    }

    .save-btn {
        background: #27ae60;
        color: white;
    }

    .cancel-btn {
        background: #e74c3c;
        color: white;
    }

    .save-btn:hover,
    .cancel-btn:hover {
        transform: translateY(-2px);
        opacity: 0.9;
    }

    .close-btn {
        background: none;
        border: none;
        font-size: 1.4em;
        color: #7f8c8d;
        cursor: pointer;
        transition: transform 0.2s;
    }

    .close-btn:hover {
        transform: rotate(90deg);
        color: #e74c3c;
    }

    .new-row {
        animation: highlightRow 1.5s;
    }

    @keyframes highlightRow {
        from {
            background: #e8f4fc;
        }

        to {
            background: transparent;
        }
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
    }

    .employee-table {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        transition: transform 0.3s ease;
    }

    .employee-table:hover {
        transform: translateY(-5px);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        padding: 1.2rem;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    th {
        background: #4a90e2;
        color: white;
    }

    tr:hover {
        background: #f8f9fa;
        transition: background 0.2s ease;
    }

    .add-time {
        background: #4a90e2;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .add-time:hover {
        background: #357abd;
        transform: scale(1.05);
    }

    .planner {
        margin-top: 2rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .employee-card {
        background: white;
        padding: 1.5rem;
        border-radius: 15px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        animation: slideUp 0.5s ease;
    }

    .time-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .time-day {
        background: #f0f2f5;
        padding: 0.5rem;
        text-align: center;
        border-radius: 8px;
        font-size: 0.9rem;
        transition: transform 0.2s ease;
    }

    .time-day:hover {
        transform: scale(1.1);
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .profile-container {
        max-width: 800px;
        margin: 2rem auto;
        animation: fadeIn 1s ease-in;
    }

    .header {

        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        height: 200px;
        border-radius: 15px 15px 0 0;
        position: relative;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .pdp {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid white;
        position: absolute;
        bottom: -60px;
        left: 50%;
        transform: translateX(-50%);
        background: #fff;
        overflow: hidden;
        transition: all 0.3s ease;
        animation: float 3s ease-in-out infinite;
    }

    .pdp:hover {
        transform: translateX(-50%) scale(1.05);
    }

    .info-section {
        background: white;
        padding: 80px 40px 40px;
        border-radius: 0 0 15px 15px;
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .info-item {
        padding: 1.5rem;
        background: rgba(99, 102, 241, 0.05);
        border-radius: 10px;
        transition: transform 0.3s ease;
        border-left: 4px solid #6366f1;
    }

    .info-item:hover {
        transform: translateY(-5px);
    }

    .info-item h3 {
        color: #6366f1;
        margin-bottom: 0.5rem;
        font-size: 1.1rem;
    }

    .info-item h3 i {
        margin-right: 10px;
        width: 25px;
        color: #6366f1;
    }

    .icon {
        width: 25px;
        text-align: center;
    }

    .info-item p {
        color: #374151;
        font-size: 1rem;
        opacity: 0.9;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes float {

        0%,
        100% {
            transform: translateX(-50%) translateY(0);
        }

        50% {
            transform: translateX(-50%) translateY(-10px);
        }
    }

    @media (max-width: 768px) {
        .profile-container {
            margin: 1rem;
        }

        .info-section {
            padding: 70px 20px 20px;
        }

        .pdp {
            width: 100px;
            height: 100px;
            bottom: -50px;
        }
    }

    /* Leave Requests Management Styles */
    #leave .management-card {
        border: none;
        border-radius: 15px;
        overflow: hidden;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        transform: translateY(0);
        transition: all 0.3s ease;
        background: linear-gradient(145deg, #ffffff 0%, #f8f9fa 100%);
    }

    #leave .management-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }

    #leave .bg-gradient-orange {
        background: linear-gradient(135deg, #ff6b6b 0%, #ff8e53 100%);
        padding: 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        color: white;
    }

    #leave .card-header i {
        font-size: 1.8rem;
        animation: wobble 2s infinite;
    }

    #leave .card-content {
        padding: 1.5rem;
        background: rgba(255, 255, 255, 0.9);
    }

    .request-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.2rem;
        margin: 1rem 0;
        border-radius: 10px;
        background: white;
        box-shadow: 0 3px 15px rgba(0, 0, 0, 0.05);
        transform: translateX(0);
        opacity: 1;
        transition: all 0.4s ease;
        animation: slideIn 0.6s ease forwards;
    }

    .request-item:hover {
        transform: translateX(10px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }

    .request-item.pending {
        border-left: 4px solid #ffd93d;
    }

    .request-item.approved {
        border-left: 4px solid #6c5ce7;
    }

    .request-item.rejected {
        border-left: 4px solid #ff7675;
    }

    .request-actions {
        display: flex;
        gap: 1rem;
        opacity: 0;
        transform: translateX(20px);
        transition: all 0.3s ease;
    }

    .request-item:hover .request-actions {
        opacity: 1;
        transform: translateX(0);
    }

    .approve-btn,
    .reject-btn {
        border: none;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .approve-btn {
        background: #00b894;
        color: white;
    }

    .approve-btn:hover {
        background: #00a383;
        transform: scale(1.05);
    }

    .approve-btn:hover i {
        animation: bounce 0.6s;
    }

    .reject-btn {
        background: #ff7675;
        color: white;
    }

    .reject-btn:hover {
        background: #e66767;
        transform: scale(1.05);
    }

    .reject-btn:hover i {
        animation: shake 0.4s;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-30px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes wobble {

        0%,
        100% {
            transform: rotate(0deg);
        }

        25% {
            transform: rotate(3deg);
        }

        75% {
            transform: rotate(-3deg);
        }
    }

    @keyframes bounce {

        0%,
        100% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-5px);
        }
    }

    @keyframes shake {
        0% {
            transform: translateX(0);
        }

        25% {
            transform: translateX(3px);
        }

        50% {
            transform: translateX(-3px);
        }

        75% {
            transform: translateX(2px);
        }

        100% {
            transform: translateX(0);
        }
    }

    /* Hover effect for cards */
    .management-card:hover .card-header {
        transform: translateY(-2px);
        transition: transform 0.3s ease;
    }

    /* Status indicator animation */
    .request-item::after {
        content: '';
        position: absolute;
        right: -10px;
        top: 50%;
        transform: translateY(-50%);
        width: 8px;
        height: 8px;
        border-radius: 50%;
        opacity: 0;
        transition: all 0.3s ease;
    }

    .request-item.pending::after {
        background: #ffd93d;
        animation: pulse 1.5s infinite;
    }

    @keyframes pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(255, 217, 61, 0.4);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(255, 217, 61, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(255, 217, 61, 0);
        }
    }

    .dashboard-sidebar {
        position: fixed;
        right: 0;
        top: 0;
        height: 100vh;
        width: 300px;
        background: #ffffff;
        padding: 20px;
        box-shadow: -5px 0 15px rgba(0, 0, 0, 0.05);
        transform: translateX(100%);
        animation: slideIn 0.5s forwards 0.3s;
        overflow-y: auto;
    }

    .icon-container {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-bottom: 30px;
    }

    .icon {
        font-size: 1.2rem;
        color: #333;
        transition: all 0.3s ease;
        cursor: pointer;
        position: relative;
    }

    .icon:hover {
        transform: scale(1.1);
        color: #2c3e50;
    }

    .calendar {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 25px;
        transform: translateY(20px);
        opacity: 0;
        animation: fadeInUp 0.4s forwards 0.5s;
    }

    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 5px;
    }

    .calendar-day {
        text-align: center;
        padding: 5px;
        font-size: 0.9rem;
        color: #666;
    }

    .calendar-date {
        text-align: center;
        padding: 8px;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .calendar-date:hover {
        background: #e9ecef;
    }

    .current-date {
        background: #3498db;
        color: white !important;
    }

    .news-container {
        transform: translateY(20px);
        opacity: 0;
        animation: fadeInUp 0.4s forwards 0.7s;
    }

    .news-item {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .news-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
    }

    .news-date {
        font-size: 0.8rem;
        color: #95a5a6;
        margin-bottom: 5px;
    }

    .news-title {
        font-weight: 600;
        margin-bottom: 5px;
        color: #2c3e50;
    }

    .news-excerpt {
        font-size: 0.9rem;
        color: #7f8c8d;
        line-height: 1.4;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
        }

        to {
            transform: translateX(0);
        }
    }

    @keyframes fadeInUp {
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 768px) {
        .dashboard-sidebar {
            width: 250px;
            padding: 15px;
        }
    }

    .dashboard-container {
        display: flex;
        min-height: 100vh;
        transition: all 0.3s ease;
    }

    /* Left Sidebar */
    .sidebar {
        width: 250px;
        background: #2c3e50;
        color: white;
        position: fixed;
        left: -250px;
        top: 0;
        height: 100vh;
        transition: all 0.3s ease;
        z-index: 1000;
    }

    /* Right Sidebar */
    .dashboard-sidebar {
        width: 300px;
        background: white;
        position: fixed;
        right: -300px;
        top: 0;
        height: 100vh;
        box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        z-index: 1000;
        padding: 20px;
    }

    /* Main Content */
    .main-content {
        flex: 1;
        padding: 20px;
        transition: margin 0.3s ease;
        min-height: 100vh;
    }

    /* Hamburger Buttons */
    .hamburger {
        position: fixed;
        top: 20px;
        cursor: pointer;
        background: #2c3e50;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        z-index: 1001;
    }

    .left-hamburger {
        left: 20px;
    }

    .right-hamburger {
        right: 20px;
    }

    /* Active States */
    .sidebar.active {
        left: 0;
    }

    .dashboard-sidebar.active {
        right: 0;
    }

    .sidebar.active~.main-content {
        margin-left: 250px;
    }

    .dashboard-sidebar.active~.main-content {
        margin-right: 300px;
    }

    /* Close Buttons */
    .close-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        cursor: pointer;
        font-size: 1.5rem;
        color: #666;
    }

    /* Existing Styles */
    .sidebar-header {
        padding: 20px;
        text-align: center;
        border-bottom: 1px solid #34495e;
    }

    .sidebar-menu li {
        padding: 15px;
        margin: 5px 10px;
        border-radius: 5px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.3s ease;
    }

    .sidebar-menu li:hover {
        background: #34495e;
    }

    .sidebar-menu .active {
        background: #3498db;
    }

    /* Your existing calendar and news styles */
    .icon-container {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-bottom: 30px;
    }

    .calendar {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 25px;
    }

    .news-item {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    @media (max-width: 768px) {

        .sidebar.active~.main-content,
        .dashboard-sidebar.active~.main-content {
            margin: 0;
        }

        .sidebar.active,
        .dashboard-sidebar.active {
            width: 100%;
        }
    }

    /* Content Sections */
    .content-section {
        display: none;
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.3s ease;
    }

    .content-section.active {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    /* Fix sidebar z-index */
    .sidebar {
        z-index: 1001;
    }

    .dashboard-sidebar {
        z-index: 999;
    }

    /* Main content positioning */
    .main-content {
        position: relative;
        z-index: 1;
    }

    /* Sidebars */
    .sidebar {
        width: 250px;
        background: #2c3e50;
        color: white;
        position: fixed;
        left: -250px;
        top: 0;
        height: 100vh;
        transition: all 0.3s ease;
        z-index: 1000;
    }

    .dashboard-sidebar {
        width: 300px;
        background: white;
        position: fixed;
        right: -300px;
        top: 0;
        height: 100vh;
        box-shadow: -2px 0 10px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        z-index: 1000;
        padding: 20px;
    }

    /* Main Content */
    .main-content {
        margin: 20px;
        transition: margin 0.3s ease;
        min-height: 100vh;
    }

    /* Hamburger Buttons */
    .hamburger {
        position: fixed;
        top: 20px;
        cursor: pointer;
        background: #2c3e50;
        color: white;
        padding: 10px 15px;
        border-radius: 5px;
        z-index: 1001;
    }

    .left-hamburger {
        left: 20px;
    }

    .right-hamburger {
        right: 20px;
    }

    /* Active States */
    .sidebar.active {
        left: 0;
    }

    .dashboard-sidebar.active {
        right: 0;
    }

    .sidebar.active~.main-content {
        margin-left: 270px;
    }

    .dashboard-sidebar.active~.main-content {
        margin-right: 320px;
    }

    /* Content Sections */
    .content-section {
        display: none;
        opacity: 0;
        transform: translateY(20px);
        transition: all 0.3s ease;
    }

    .content-section.active {
        display: block;
        opacity: 1;
        transform: translateY(0);
    }

    /* Navigation Menu */
    .sidebar-menu li {
        padding: 15px;
        margin: 5px;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .sidebar-menu li:hover {
        background: #34495e;
    }

    .sidebar-menu .active {
        background: #3498db;
    }

    /* Calendar & News Styles */
    .calendar {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 25px;
    }

    .news-item {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 15px;
    }

    /* Employee Table */
    .modern-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }

    .modern-table th,
    .modern-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }

    /* Responsive Design */
    @media (max-width: 768px) {

        .sidebar.active,
        .dashboard-sidebar.active {
            width: 100%;
            z-index: 1002;
        }

        .sidebar.active~.main-content,
        .dashboard-sidebar.active~.main-content {
            margin: 20px;
        }
    }

    /* ================ CSS Variables & Theming ================ */
    :root {
        /* Color System */
        --primary-hue: 235;
        --primary-saturation: 80%;
        --primary-lightness: 50%;

        --color-primary: hsl(var(--primary-hue),
                var(--primary-saturation),
                var(--primary-lightness));
        --color-primary-dark: hsl(var(--primary-hue),
                calc(var(--primary-saturation) - 10%),
                calc(var(--primary-lightness) - 10%));
        --color-accent: #10b981;
        --color-background: hsl(0, 0%, 100%);
        --color-surface: hsl(0, 0%, 98%);
        --color-text: hsl(220, 13%, 18%);
        --color-text-secondary: hsl(220, 9%, 46%);

        /* Dark Mode Variables */
        --color-background-dark: hsl(220, 13%, 18%);
        --color-surface-dark: hsl(220, 13%, 22%);
        --color-text-dark: hsl(0, 0%, 98%);
        --color-text-secondary-dark: hsl(0, 0%, 70%);

        /* Elevation Shadows */
        --elevation-1: 0 1px 3px rgba(0, 0, 0, 0.12);
        --elevation-2: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        --elevation-3: 0 10px 15px -3px rgba(0, 0, 0, 0.1);

        /* Transitions */
        --transition-speed: 0.3s;
        --transition-easing: cubic-bezier(0.4, 0, 0.2, 1);
        --transition-all: all var(--transition-speed) var(--transition-easing);

        /* Border Radius */
        --radius-sm: 4px;
        --radius-md: 8px;
        --radius-lg: 12px;

        /* Spacing */
        --space-unit: 8px;
        --space-1: calc(var(--space-unit) * 1);
        --space-2: calc(var(--space-unit) * 2);
        --space-3: calc(var(--space-unit) * 3);
    }

    /* ================ Base Styles ================ */
    .settings-panel {
        --background: var(--color-background);
        --surface: var(--color-surface);
        --text: var(--color-text);
        --text-secondary: var(--color-text-secondary);

        background: var(--background);
        color: var(--text);
        padding: var(--space-3);
        border-radius: var(--radius-lg);
        box-shadow: var(--elevation-3);
        transform: translateY(20px);
        opacity: 0;
        animation: panelEntrance 0.6s var(--transition-easing) forwards;
        will-change: transform, opacity;
        border: 1px solid hsl(0, 0%, 90%);
    }

    .dark-mode .settings-panel {
        --background: var(--color-background-dark);
        --surface: var(--color-surface-dark);
        --text: var(--color-text-dark);
        --text-secondary: var(--color-text-secondary-dark);
        border-color: hsl(0, 0%, 20%);
    }

    /* ================ Animations ================ */
    @keyframes panelEntrance {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes settingsGroupEntrance {
        from {
            opacity: 0;
            transform: translateX(-20px);
        }

        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    @keyframes spin {
        from {
            transform: rotate(0deg);
        }

        to {
            transform: rotate(360deg);
        }
    }

    /* ================ Components ================ */
    :root {
        --color-primary: hsl(210, 80%, 55%);
        --color-accent: hsl(280, 70%, 60%);
        --surface: hsl(0, 0%, 100%);
        --surface-dark: hsl(220, 15%, 16%);
        --transition-all: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .settings-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: var(--space-2);
        margin-bottom: var(--space-3);
        position: relative;
        opacity: 0;
        animation: headerEntrance 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;


    }

    @keyframes headerEntrance {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes headerLine {
        to {
            transform: scaleX(1);
        }
    }

    .spinning {
        animation: spin 1.8s cubic-bezier(0.68, -0.55, 0.27, 1.55) infinite;
        filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.15));

        @keyframes spin {
            to {
                transform: rotate(360deg) scale(1.1);
            }
        }
    }

    .settings-group {
        margin-bottom: var(--space-3);
        background: var(--surface);
        border-radius: var(--radius-md);
        padding: var(--space-2);
        opacity: 0;
        transform: translateY(10px);
        animation: settingsGroupEntrance 0.4s var(--transition-easing) forwards;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        transition: var(--transition-all);

        &:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 12px -2px rgba(0, 0, 0, 0.1);
        }

        @keyframes settingsGroupEntrance {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    }

    .setting-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-2);
        margin: var(--space-1) 0;
        border-radius: var(--radius-sm);
        transition: var(--transition-all);
        cursor: pointer;
        position: relative;
        background: linear-gradient(to right,
                transparent 0%,
                transparent 90%,
                var(--color-primary) 100%);
        background-size: 300% 100%;
        background-position: 100% 0;




    }

    /* Dark Mode */
    .dark-mode {
        .settings-header::after {
            opacity: 0.15;
        }

        .settings-group {
            background: var(--surface-dark);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2);
        }

        .setting-item {
            background: linear-gradient(to right,
                    transparent 0%,
                    transparent 90%,
                    var(--color-accent) 100%);


        }
    }

    /* Staggered Animation Delay */
    .settings-group:nth-child(1) {
        animation-delay: 0.1s
    }

    .settings-group:nth-child(2) {
        animation-delay: 0.2s
    }

    .settings-group:nth-child(3) {
        animation-delay: 0.3s
    }

    /* ================ Custom Controls ================ */
    .switch {
        --switch-width: 48px;
        --switch-height: 28px;
        --thumb-size: 20px;

        position: relative;
        display: inline-block;
        width: var(--switch-width);
        height: var(--switch-height);

    }

    .slider {
        position: absolute;
        cursor: pointer;
        inset: 0;
        background: hsl(0, 0%, 80%);
        transition: var(--transition-all);
        border-radius: var(--radius-lg);

    }

    /* ================ Theme Colors ================ */
    .theme-colors {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(40px, 1fr));
        gap: var(--space-2);
        margin: var(--space-2) 0;
    }

    .color-swatch {
        --size: 40px;

        width: var(--size);
        height: var(--size);
        border-radius: var(--radius-md);
        cursor: pointer;
        transition: var(--transition-all);
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;


    }

    /* ================ Responsive Design ================ */
    @media (max-width: 768px) {
        .settings-panel {
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            margin: 0 var(--space-2);
        }

        .settings-group {
            padding: var(--space-2);
        }

        .setting-item {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-1);
        }
    }

    /*time handle*/
    .time-tracker {
        padding: 2rem;
        max-width: 1200px;
        margin: 0 auto;
    }

    .card {
        background: #ffffff;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .card:hover {
        transform: translateY(-5px);
    }

    .form-title,
    .table-title {
        font-size: 1.5rem;
        color: #2c3e50;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .form-group {
        position: relative;
        margin-bottom: 1rem;
    }

    .form-input {
        width: 100%;
        padding: 1rem;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: transparent;
    }

    .form-input:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }

    .form-label {
        position: absolute;
        left: 1rem;
        top: 1rem;
        padding: 0 0.25rem;
        background: white;
        color: #95a5a6;
        transition: all 0.3s ease;
        pointer-events: none;
    }

    .form-input:focus~.form-label,
    .form-input:not(:placeholder-shown)~.form-label {
        top: -0.5rem;
        left: 0.8rem;
        font-size: 0.8rem;
        color: #3498db;
    }

    .add-time-btn {
        position: relative;
        background: #3498db;
        color: white;
        border: none;
        padding: 1rem 2rem;
        border-radius: 8px;
        font-size: 1rem;
        cursor: pointer;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .btn-hover-effect {
        position: absolute;
        background: rgba(255, 255, 255, 0.2);
        width: 100%;
        height: 100%;
        left: -100%;
        top: 0;
        transition: left 0.4s ease;
    }

    .add-time-btn:hover .btn-hover-effect {
        left: 100%;
    }

    .table-wrapper {
        overflow-x: auto;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        min-width: 600px;
    }

    th,
    td {
        padding: 1rem;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
    }

    th {
        background: #3498db;
        color: white;
        font-weight: 600;
        position: sticky;
        top: 0;
    }

    tr:last-child td {
        border-bottom: none;
    }

    tr {
        transition: all 0.3s ease;
    }

    tr:hover {
        background: #f8f9fa;
        transform: translateX(8px);
    }

    .delete-btn {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .delete-btn::after {
        content: '';
        position: absolute;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.2);
        left: -100%;
        top: 0;
        transition: left 0.3s ease;
    }

    .delete-btn:hover::after {
        left: 100%;
    }

    @keyframes newEntry {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes removeEntry {
        to {
            opacity: 0;
            transform: translateX(50px);
        }
    }

    #2025-calendar {
        display: grid;
        gap: 2rem;
        padding: 1rem;
    }

    .calendar-month {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 1rem;
    }

    .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.5rem;
        margin-top: 1rem;
    }

    .calendar-day {
        aspect-ratio: 1;
        padding: 0.5rem;
        border-radius: 4px;
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    /* Keep previous .public-holiday styles */
    /* Main layout structure */
    .dashboard-container {
        display: grid;
        grid-template-columns: auto 1fr;
        min-height: 100vh;
        position: relative;
    }

    /* Sidebars (existing) */
    .sidebar-primary,
    .sidebar-secondary {
        position: fixed;
        top: 0;
        bottom: 0;
        z-index: 100;
        width: 250px;
        background: #fff;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar-primary {
        left: 0;
    }

    .sidebar-secondary {
        right: 0;
    }

    /* Main content area */
    .main-content {
        grid-column: 2;
        padding: 2rem;

        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    /* New Elements */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin: 2rem 0;
    }

    .stat-circle {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        background: conic-gradient(#4CAF50 75%, #eee 0);
        display: grid;
        place-items: center;
        position: relative;
        transition: transform 0.3s ease;
    }

    .stat-circle:hover {
        transform: scale(1.05);
    }

    .stat-circle::before {
        content: attr(data-percent)"%";
        position: absolute;
        font-size: 1.5rem;
        font-weight: bold;
        color: #2C3E50;
    }

    .departments-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }

    .department-card {
        background: #fff;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .department-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
    }

    .add-department {
        background: #3498DB;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        min-height: 120px;
    }

    .dashboard-footer {
        margin-top: auto;
        padding: 2rem 0;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .social-links {
        display: flex;
        gap: 1.5rem;
    }

    .social-link {
        color: #2C3E50;
        transition: all 0.3s ease;
    }

    .social-link:hover {
        color: #3498DB;
        transform: translateY(-2px);
    }

    /* Table styling */
    .leave-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    }

    .leave-table th,
    .leave-table td {
        padding: 1rem;
        text-align: left;
    }

    .leave-table thead {
        background: #2C3E50;
        color: white;
    }

    .leave-table tbody tr {
        transition: background 0.3s ease;
    }

    .leave-table tbody tr:hover {
        background: #f8f9fa;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .presence-list {
            max-height: 80px;
            overflow-y: scroll;
            border: 1px solid #ccc;
            padding: 10px;
            margin-bottom: 20px;
            background-color: white;
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
            margin-left: 480px;
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

<body>
    <!-- Hamburger Buttons -->
    <div class="hamburger left-hamburger" onclick="toggleSidebar('left')">
        <i class="fas fa-bars"></i>
    </div>
    <div class="hamburger right-hamburger" onclick="toggleSidebar('right')">
        <i class="fas fa-bars"></i>
    </div>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="close-btn" onclick="toggleSidebar('left')">&times;</div>
            <div class="sidebar-header">

            </div>
            <ul class="sidebar-menu">
                <li class="active" data-target="dashboard">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </li>
                <li data-target="profile">
                    <i class="fas fa-user"></i>
                    <span>Profile</span>
                </li>
                <li data-target="messenger">
                    <i class="fas fa-comments"></i>
                    <span>Messenger</span>
                </li>
                <li data-target="calendar">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Calendar</span>
                </li>
                <li data-target="settings">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </li>
                <li data-target="employees">
                    <i class="fas fa-users"></i>
                    <span>Employees</span>
                </li>
                <li data-target="leave">
                    <i class="fas fa-envelope-open-text"></i>
                    <span>Leave Requests</span>
                </li>
                <li data-target="time">
                    <i class="fas fa-clock"></i>
                    <span>Time Tracking</span>
                </li>
                <li data-target="evaluation">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Evaluation</span>
                </li>
            </ul>

            <div class="sidebar-footer">
                <!-- Footer content can be added here -->
            </div>
        </nav>

        <!-- Main Content -->
        <div class="main-content">

            <!-- Top Navigation -->
            <div class="dashboard-sidebar">
                <div class="icon-container">
                    <i class="icon fas fa-bell"></i>
                    <i class="icon fas fa-user-circle"></i>
                </div>

                <div class="calendar">
                    <div class="calendar-header">
                        <span>February </span>
                        <div class="calendar-controls">
                            <i class="icon fas fa-chevron-left"></i>
                            <i class="icon fas fa-chevron-right"></i>
                        </div>
                    </div>
                    <div class="calendar-grid">
                        <!-- Days -->
                        <div class="calendar-day">Sun</div>
                        <div class="calendar-day">Mon</div>
                        <div class="calendar-day">Tue</div>
                        <div class="calendar-day">Wed</div>
                        <div class="calendar-day">Thu</div>
                        <div class="calendar-day">Fri</div>
                        <div class="calendar-day">Sat</div>
                        <br>
                        <!-- Dates -->
                        <!-- Example dates - you would need to implement dynamic dates -->
                        <div class="calendar-date">26</div>
                        <div class="calendar-date">27</div>
                        <div class="calendar-date">28</div>
                        <div class="calendar-date">29</div>
                        <div class="calendar-date">30</div>br
                        <div class="calendar-date current-date">1</div>
                        <div class="calendar-date">2</div>
                        <!-- Add more dates as needed -->
                    </div>
                </div>

                <div class="news-container">
                    <div class="news-item">

                        <div class="news-title">Employees attendance</div>
                        <div class="news-excerpt">sara mlika</div>
                    </div>


                </div>
            </div>


            <div class="content-section active" id="dashboard">
                <!-- Welcome Header -->
                <div class="welcome-header">
                    <h1>Welcome Back,  <?= htmlspecialchars($nom_entreprise) ?> dans notre application SIWALAXY 👋</h1>
                    <p>Manage your team efficiently</p><br>
                    <br>
                </div>

                <!-- Dashboard Content -->
                <div class="content-section active" id="dashboard">


                   <!-- Présence des employés -->
                   <div class="content-section active" id="dashboard">
    <!-- Présence des employés -->
    <h2>Liste de Présence</h2>
    <div class="presence-list">
        <ul>
            <?php foreach ($e as $employe): ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        <li class="employe-status">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            <?= htmlspecialchars($employe['nom'] . ' ' . $employe['prenom']) ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            <span class="status-dot" style="background-color: <?= $employe['statut'] == 'present' ? 'green' : 'red' ?>;"></span>
                                                                                        
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
                    <!-- Accepted Leave List -->
<div class="section">
    <h2>Accepted Leave List</h2>
    <table class="leave-table">
        <thead>
            <tr>
                <th>Employee Name</th>
                <th>Period</th>
                <th>Number of Days</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $conges_acceptes = $conn->query("
                SELECT e.nom, e.prenom, c.date_debut, c.date_fin, c.nb_jours 
                FROM conges_acceptes c 
                JOIN employes e ON c.matricule = e.matricule
            ")->fetchAll(PDO::FETCH_ASSOC); foreach ($conges_acceptes as $conge) {
                echo "<tr>
                    <td>" . htmlspecialchars($conge['nom'] . ' ' . $conge['prenom']) . "</td>
                    <td>" . htmlspecialchars($conge['date_debut']) . " to " . htmlspecialchars($conge['date_fin']) . "</td>
                    <td>" . htmlspecialchars($conge['nb_jours']) . "</td>
                  </tr>";
            }
            ?>
        </tbody>
    </table>
</div>

                    <!-- Départements -->
<h2>Departements</h2>
<div class="departments-section">
    <?php foreach ($departements as $departement): ?>
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                <div class="department-card" onclick="window.location.href='departement.php?id=<?= $departement['id_departement'] ?>'">
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    <h3><?= htmlspecialchars($departement['nom']) ?></h3>
               
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                </div>
    <?php endforeach; ?>
    
    <!-- Carte pour ajouter un département -->
    <div class="department-card add-department">
       
        <!-- Formulaire d'ajout -->
    <form id="ajouter-departement-form">
        <input type="text" name="nom" placeholder="Name of departement" required>
        <button type="submit">add departement</button>
    </form>

    </div>
</div>
 <!-- Carte pour supprimer un département -->
 <div class="department-card add-department">
       
        <!-- Formulaire de suppression -->
    <form id="supprimer-departement-form">
        <input type="text" name="nom_supprimer" placeholder="Name of dpartement" required>
        <button type="submit">delete departement</button>
    </form>

   </div>
</div><br><br><br>
<!-- Bouton pour afficher la messagerie -->
<button class="toggle-messaging" onclick="toggleMessaging()">
💬
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
            <button type="submit"><i class="fas fa-paper-plane"></i></button>
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
        ")->fetchAll(PDO::FETCH_ASSOC); foreach ($messages as $msg) {
                $styleClass = $msg['id_employe'] ? 'message-employe' : 'message-entreprise'; // Classe CSS selon l'expéditeur
                echo "<div class='$styleClass'><strong>{$msg['nom']} {$msg['prenom']}:</strong> {$msg['message']} <span style='color: gray;'>(" . date('H:i', strtotime($msg['date_envoi'])) . ")</span></div>";
            }
            ?>
        </div>
    </div>
                        <!-- Footer -->
                        <footer class="dashboard-footer">
                            <div class="contact-info">
                                <p>siwalaxy@gmail.com</p>

                            </div>
                            <div class="social-links">
                                <a href="https://www.instagram.com/siwa_laxy?igsh=djVkbHkyanF5d2dm&utm_source=qr" class="social-link"><i class="fab fa-instagram"></i></a>
                                <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                                <a href="mailto:siwalaxy@gmail.com" class="social-link"><i class="far fa-envelope"></i></a>
                            </div>
                        </footer>
                    </div>
                </div>

                <!-- Add Font Awesome for icons -->
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


                <!-- Modals -->
                <div id="employeeModal" class="modal">
                    <div class="modal-content">
                        <h3>Add New Employee</h3>
                        <form id="employeeForm">
                            <input type="text" placeholder="Full Name" required>
                            <input type="email" placeholder="Email" required>
                            <select>
                                <option>Position</option>
                                <!-- Options -->
                            </select>
                            <div class="form-actions">
                                <button type="submit">Save</button>
                                <button type="button" onclick="closeModal()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="content-section" id="profile">
                <div class="profile-container">
                    <div class="header">
                        <div class="pdp">
                            <!-- Add company logo image here -->
                            <img src="a (2).jpeg" alt="profile">
                        </div>
                    </div>

                    <div class="info-section">
                        <div class="info-grid">
                            <div class="info-item">
                                <h3><i class="fas fa-envelope icon"></i>Email</h3>
                                <p class="email">Loading...</p>
                            </div>

                            <div class="info-item">
                                <h3><i class="fas fa-map-marker-alt icon"></i>Address</h3>
                                <p class="address">Loading...</p>
                            </div>

                            <div class="info-item">
                                <h3><i class="fas fa-mail-bulk icon"></i>Postal Code</h3>
                                <p class="postal-code">Loading...</p>
                            </div>

                            <div class="info-item">
                                <h3><i class="fas fa-phone icon"></i>Phone</h3>
                                <p class="phone">Loading...</p>
                            </div>

                            <div class="info-item">
                                <h3><i class="fas fa-calendar-alt icon"></i>Foundation Date</h3>
                                <p class="foundation-date">Loading...</p>
                            </div>

                            <div class="info-item">
                                <h3><i class="fas fa-building icon"></i>Departments</h3>
                                <p class="departments">Loading...</p>
                            </div>

                            <div class="info-item">
                                <h3><i class="fas fa-users icon"></i>Employees</h3>
                                <p class="employees">Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="content-section" id="messenger">
                <!-- Messenger Content -->
                <h2>Messenger</h2>
                <div class="chat-container">
                <div class="content-section" id="messenger">
    <!-- Messenger Content -->
    <h2>Messenger</h2>
    <div class="chat-container">
        <div class="chat-messages" id="messages-list">
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
                echo "<div class='$styleClass'><strong>" . htmlspecialchars($msg['nom'] . ' ' . $msg['prenom']) . ":</strong> " . htmlspecialchars($msg['message']) . " <span style='color: gray;'>(" . date('H:i', strtotime($msg['date_envoi'])) . ")</span></div>";
            }
            ?>
        </div>
        <div class="message-input">
            <input type="text" placeholder="Type a message..." required>
            <button type="submit"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>
    </div>
</div>
                </div>
            </div>
<div id="employees" class="content-section">
        <div class="employee-management">
            <!-- Animated Header -->
            <div class="management-header slide-in">
                <div class="header-title">
                    <i class="fas fa-users animated bounce"></i>
                    <h1>Employee Directory</h1>
                </div>
                <div class="header-actions">
                    <button class="action-btn add-btn" id="showAddForm">
                        <i class="fas fa-plus-circle"></i> Add Employee
                    </button><br>
                    <button class="icon-btn edit-btn" id="showEditForm"><i class="fas fa-edit"></i> Edit Employee</button>
                    <button class="icon-btn delete-btn" id="showDeleteForm"><i class="fas fa-trash-alt"></i> Delete Employee</button>
                </div>
            </div>

            <h1>Employee List</h1>

            <!-- Employee Table -->
            <div class="table-container pop-in">
                <table class="modern-table" border="1">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Surname</th>
                            <th>Email</th>
                            <th>Telephone</th>
                            <th>Date of Birth</th>
                            <th>Title</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employes as $employe): ?>
                                                                                                                                                                        <tr>
                                                                                                                                                                            <td><?= htmlspecialchars($employe['matricule']) ?></td>
                                                                                                                                                                            <td><?= htmlspecialchars($employe['nom']) ?></td>
                                                                                                                                                                            <td><?= htmlspecialchars($employe['prenom']) ?></td>
                                                                                                                                                                            <td><?= htmlspecialchars($employe['email']) ?></td>
                                                                                                                                                                            <td><?= htmlspecialchars($employe['num_tel']) ?></td>
                                                                                                                                                                            <td><?= htmlspecialchars($employe['date_naissance']) ?></td>
                                                                                                                                                                            <td><?= htmlspecialchars($employe['titre']) ?></td>
                                                                                                                                                                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Add Employee Form (Initially Hidden) -->
        <div class="form-overlay" id="formOverlay">
            <div class="add-form-container slide-down">
                <div class="form-header">
                    <h2><i class="fas fa-user-plus"></i> New Employee</h2>
                    <button class="close-btn" id="closeAddForm">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form id="addEmployeeForm" class="employee-form" method="post">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Employee ID (4 digits):</label>
                            <input type="text" name="matricule" pattern="\d{4}" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name:</label>
                            <input type="text" name="nom" required>
                        </div>
                        <div class="form-group">
                            <label>First Name:</label>
                            <input type="text" name="prenom" required>
                        </div>
                        <div class="form-group">
                            <label>CIN Number (8 digits):</label>
                            <input type="text" name="num_cin" pattern="\d{8}" required>
                        </div>
                        <div class="form-group">
                            <label>Email:</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Telephone Number (8 digits):</label>
                            <input type="text" name="num_tel" pattern="\d{8}" required>
                        </div>
                        <div class="form-group">
                            <label>Date of Birth:</label>
                            <input type="date" name="date_naissance" required>
                        </div>
                        <div class="form-group">
                            <label>Title:</label>
                            <input type="text" name="titre" required>
                        </div>
                        <div class="form-group">
                            <label>Department:</label>
                            <select name="id_departement" required>
                                <option value="" disabled selected>Select a department</option>
                                <?php foreach ($departements as $departement): ?>
                                                                                                                                                                                <option value="<?= $departement['id_departement'] ?>"><?= htmlspecialchars($departement['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="cancel-btn">Cancel</button>
                        <button type="submit" class="save-btn">Save Employee</button>
                    </div>
                </form>
            </div>
        </div>
<!-- Edit Employee Form (Initially Hidden) -->
<div class="form-overlay" id="editFormOverlay">
    <div class="edit-form-container slide-down">
        <div class="form-header">
            <h2><i class="fas fa-edit"></i> Edit Employee</h2>
            <button class="close-btn" id="closeEditForm">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="editEmployeeForm" class="employee-form" method="post">
            <input type="hidden" name="editMode" value="1"> <!-- Champ caché pour indiquer le mode d'édition -->
            <input type="hidden" name="oldMatricule" value="<?= htmlspecialchars($employe['matricule']) ?>"> <!-- Champ caché pour l'ancien matricule -->
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee ID:</label>
                    <input type="text" name="matricule" value="<?= htmlspecialchars($employe['matricule']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Last Name:</label>
                    <input type="text" name="nom" value="<?= htmlspecialchars($employe['nom']) ?>" required>
                </div>
                <div class="form-group">
                    <label>First Name:</label>
                    <input type="text" name="prenom" value="<?= htmlspecialchars($employe['prenom']) ?>" required>
                </div>
                <div class="form-group">
                    <label>CIN Number (8 digits):</label>
                    <input type="text" name="num_cin" value="<?= htmlspecialchars($employe['num_cin']) ?>" pattern="\d{8}" required>
                </div>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($employe['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Telephone Number (8 digits):</label>
                    <input type="text" name="num_tel" value="<?= htmlspecialchars($employe['num_tel']) ?>" pattern="\d{8}" required>
                </div>
                <div class="form-group">
                    <label>Date of Birth:</label>
                    <input type="date" name="date_naissance" value="<?= htmlspecialchars($employe['date_naissance']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Title:</label>
                    <input type="text" name="titre" value="<?= htmlspecialchars($employe['titre']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Department:</label>
                    <select name="id_departement" required>
                        <option value="" disabled>Select a department</option>
                        <?php foreach ($departements as $departement): ?>
                                                                                                                                                    <option value="<?= $departement['id_departement'] ?>" <?= $departement['id_departement'] == $employe['id_departement'] ? 'selected' : '' ?>><?= htmlspecialchars($departement['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-actions">
                <button type="reset" class="cancel-btn">Cancel</button>
                <button type="submit" class="save-btn">Update Employee</button>
            </div>
        </form>
    </div>
</div>
 <!-- Delete Employee Form -->
<div class="form-overlay" id="deleteFormOverlay">
    <div class="delete-form-container slide-down">
        <div class="form-header">
            <h2><i class="fas fa-trash-alt"></i> Delete Employee</h2>
            <button class="close-btn" id="closeDeleteForm">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="deleteEmployeeForm" class="employee-form" method="post">
            <div class="form-group">
                <label>Employee ID to delete:</label>
                <input type="text" name="matricule" required>
            </div>
        </form>

        <?php if (isset($employe)): ?>
                                                                                                                    <form id="confirmDeleteForm" class="employee-form" method="post">
                                                                                                                        <input type="hidden" name="delete" value="1"> <!-- Champ caché pour indiquer le mode de suppression -->
                                                                                                                        <input type="hidden" name="matricule" value="<?= htmlspecialchars($employe['matricule']) ?>"> <!-- Matricule de l'employé à supprimer -->

                                                                                                                        <div class="form-actions">
                                                                                                                            <button type="reset" class="cancel-btn">Cancel</button>
                                                                                                                            <button type="submit" class="delete-btn">Delete Employee</button>
                                                                                                                        </div>
                                                                                                                    </form>
        <?php endif; ?>
    </div>
</div>
</div>
<!-- Leave Requests Management -->
<div id="leave" class="content-section">
    <div class="card management-card">
        <div class="card-header bg-gradient-orange">
            <i class="fas fa-calendar-times"></i>
            <h3>Leave Requests</h3>
        </div>
        <div class="card-content">
    <?php if (!empty($demandes)): ?>
                                                                                                                                        <?php foreach ($demandes as $demande): ?>
                                                                                                                                                                                                                                                                            <div id="request-<?= $demande['id'] ?>" class="request-item pending">
                                                                                                                                                                                                                                                                                <div class="request-info">
                                                                                                                                                                                                                                                                                    <h4><?= htmlspecialchars($demande['nom'] . ' ' . $demande['prenom']) ?></h4>
                                                                                                                                                                                                                                                                                    <p>From <?= htmlspecialchars($demande['date_debut']) ?> to <?= htmlspecialchars($demande['date_fin']) ?></p>
                                                                                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                                                                                <div class="request-actions">
                                                                                                                                                                                                                                                                                    <form action="gerer_conge.php" method="post" style="display:inline;">
                                                                                                                                                                                                                                                                                        <input type="hidden" name="id" value="<?= $demande['id'] ?>">
                                                                                                                                                                                                                                                                                        <input type="hidden" name="action" value="accepte">
                                                                                                                                                                                                                                                                                        <button type="submit" class="approve-btn">
                                                                                                                                                                                                                                                                                            <i class="fas fa-check"></i> Approve
                                                                                                                                                                                                                                                                                        </button>
                                                                                                                                                                                                                                                                                    </form>
                                                                                                                                                                                                                                                                                    <form action="gerer_conge.php" method="post" style="display:inline;">
                                                                                                                                                                                                                                                                                        <input type="hidden" name="id" value="<?= $demande['id'] ?>">
                                                                                                                                                                                                                                                                                        <input type="hidden" name="action" value="refuse">
                                                                                                                                                                                                                                                                                        <button type="submit" class="reject-btn">
                                                                                                                                                                                                                                                                                            <i class="fas fa-times"></i> Reject
                                                                                                                                                                                                                                                                                        </button>
                                                                                                                                                                                                                                                                                    </form>
                                                                                                                                                                                                                                                                                </div>
                                                                                                                                                                                                                                                                            </div>
                                                                                                                                        <?php endforeach; ?>
    <?php else: ?>
                                                                                                                                        <p>No pending requests.</p>
    <?php endif; ?>
       </div>
    </div>
</div>
            <!-- Add this to your settings section -->
            <div id="settings" class="content-section">
                <div class="settings-panel">
                    <div class="settings-header">
                        <h3>Application Settings</h3>
                        <i class="fas fa-cog spinning"></i>
                    </div>

                    <div class="settings-group animated-entry">
                        <h4 class="settings-group-title">
                            <i class="fas fa-user"></i>
                            User Preferences
                        </h4>

                        <div class="setting-item">
                            <div class="setting-label">Dark Mode</div>
                            <label class="switch">
                                <input type="checkbox" id="darkModeToggle">
                                <span class="slider round"></span>
                            </label>
                        </div>

                        <div class="setting-item">
                            <div class="setting-label">Notification Sound</div>
                            <div class="select-wrapper">
                                <select class="styled-select">
                                    <option>Chime</option>
                                    <option>Bell</option>
                                    <option>None</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </div>

                    <div class="settings-group animated-entry delay-1">
                        <h4 class="settings-group-title">
                            <i class="fas fa-palette"></i>
                            Theme Customization
                        </h4>

                        <div class="theme-colors">
                            <div class="color-swatch primary" data-color="#6366f1"></div>
                            <div class="color-swatch secondary" data-color="#10b981"></div>
                            <div class="color-swatch accent" data-color="#f59e0b"></div>
                        </div>

                        <div class="theme-preview">
                            <div class="preview-box primary-bg"></div>
                            <div class="preview-box secondary-bg"></div>
                            <div class="preview-box accent-bg"></div>
                        </div>
                    </div>

                    <div class="settings-group animated-entry delay-2">
                        <h4 class="settings-group-title">
                            <i class="fas fa-bell"></i>
                            Notifications
                        </h4>

                        <div class="notification-settings">
                            <div class="setting-item">
                                <div class="setting-label">Email Notifications</div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider round"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-label">Push Notifications</div>
                                <label class="switch">
                                    <input type="checkbox">
                                    <span class="slider round"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div id="time" class="content-section">
    <div class="time-tracking-container">
        <div class="time-tracker">
            <!-- Input Form -->
            <div class="time-form card">
    <h2 class="form-title"><i class="fas fa-clock"></i> Add Work Hours</h2>
    <form method="post">
        <div class="form-grid">
            <div class="form-group">
                <select id="employeeId" name="id_employe" class="form-input" required>
                <option value="">Sélectionnez un employé</option>
                <?php foreach ($emp as $employe): ?>
                                                                                                                                                                                                                                                                                                                            <option value="<?= $employe['id_employe'] ?>"><?= htmlspecialchars($employe['nom'] . ' ' . $employe['prenom']) ?></option>
                <?php endforeach; ?>
                </select>
                <label for="employeeId" class="form-label">Employee</label>
            </div>
            <div class="form-group">
                <input type="date" id="workDate" name="date" class="form-input" required>
                <label for="workDate" class="form-label">Date</label>
            </div>
            <div class="form-group">
                <input type="number" id="hoursWorked" name="heures_travaillees" class="form-input" step="0.5" min="0" max="24" required>
                <label for="hoursWorked" class="form-label">Hours Worked</label>
            </div>
        </div>
        <button type="submit" class="add-time-btn">
            <span>Add Time</span>
            <div class="btn-hover-effect"></div>
        </button>
    </form>
</div>


            <!-- Time Entries Table -->
            <div class="time-table card">
                <h2 class="table-title"><i class="fas fa-history"></i> Time Records</h2>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th> Name</th>
    
                                <th>Date</th>
                                <th>Hours Worked</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($feuilles_temps as $feuille): ?>
                                                                                                                                                                                                                                                                                                                                                                                        <tr>

                                                                                                                                                                                                                                                                                                                                                                                            <td>
                                                                                                                                                                                                                                                                                                                                                            <?= htmlspecialchars($feuille['nom'] . ' ' . $feuille['prenom']) ?>
                                                                                                                                                                                                                                                                                                                                                        </td>
                                                                                                                                                                                                                                                                                                                                                                                            <td><?= htmlspecialchars($feuille['date']) ?></td>
                                                                                                                                                                                                                                                                                                                                                                                            <td><?= htmlspecialchars($feuille['heures_travaillees']) ?></td>
                                                                                                                                                                                                                                                                                                                                                                                        </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
            <div class="content-section" id="evaluation">
                <div class="container">
                    <!-- Add Project Button -->
                    <button class="add-project-btn" onclick="toggleProjectForm()">
                        <i class="fas fa-plus"></i> Add New Project
                    </button>

                    <!-- Project Creation Form -->
                    <div class="project-form hidden">
                        <div class="form-header">
                            <h2>Create New Project</h2>
                            <i class="fas fa-times close-btn" onclick="toggleProjectForm()"></i>
                        </div>
                        <form id="projectForm">
                            <div class="form-group">
                                <label>Project Title</label>
                                <input type="text" id="projectName" required>
                            </div>
                            <div class="form-group">
                                <label>Project Type</label>
                                <select id="projectType" required>
                                    <input type="text">Select Type</input>

                                </select>
                            </div>
                            <div class="form-group">
                                <label>Due Date</label>
                                <input type="date" id="dueDate" required>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea id="projectDesc" required></textarea>
                            </div>
                            <button type="submit" class="submit-btn">
                                Create Project
                                <div class="hover-effect"></div>
                            </button>
                        </form>
                    </div>

                    <!-- Projects Container -->
                    <div class="projects-container" id="projectsContainer"></div>

                    <!-- Rating Modal -->
                    <div class="rating-modal" id="ratingModal">
                        <div class="modal-content">
                            <h3>Rate Employee</h3>
                            <div class="modal-body">
                                <div class="employee-info">
                                    <span id="selectedEmployee"></span>
                                    <span id="selectedProject"></span>
                                </div>
                                <div class="form-group">
                                    <label>Rating (0-10)</label>
                                    <input type="number" id="employeeRating" min="0" max="10" step="0.5">
                                </div>
                                <div class="form-group">
                                    <label>Comments</label>
                                    <textarea id="employeeComments"></textarea>
                                </div>
                            </div>
                            <div class="modal-actions">
                                <button class="cancel-btn" onclick="closeRatingModal()">Cancel</button>
                                <button class="submit-btn" onclick="saveRating()">Save Rating</button>
                            </div>
                        </div>
                    </div>

                    <!-- Final Ratings Table -->
                    <div class="ratings-table">
                        <h2>Employee Evaluations</h2>
                        <div class="table-wrapper">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Project</th>
                                        <th>Rating</th>
                                        <th>Comments</th>
                                        <th>Due Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="ratingsBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <style>
                    /* Base Styles */
                    .container {
                        max-width: 1200px;
                        margin: 2rem auto;
                        padding: 0 1rem;
                    }

                    /* Add Project Button */
                    .add-project-btn {
                        background: linear-gradient(135deg, #3498db, #2980b9);
                        color: white;
                        border: none;
                        padding: 1rem 2rem;
                        border-radius: 8px;
                        cursor: pointer;
                        margin-bottom: 2rem;
                        transition: all 0.3s ease;
                        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
                    }

                    .add-project-btn:hover {
                        transform: translateY(-2px);
                        box-shadow: 0 6px 16px rgba(52, 152, 219, 0.4);
                    }

                    /* Project Form */
                    .project-form {
                        background: rgba(255, 255, 255, 0.95);
                        backdrop-filter: blur(10px);
                        border-radius: 12px;
                        padding: 0;
                        margin-bottom: 2rem;
                        max-height: 0;
                        overflow: hidden;
                        opacity: 0;
                        transform: translateY(-20px);
                        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                    }

                    .project-form.visible {
                        max-height: 800px;
                        opacity: 1;
                        transform: translateY(0);
                        padding: 2rem;
                        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
                    }

                    .form-header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 2rem;
                    }

                    .close-btn {
                        cursor: pointer;
                        color: #95a5a6;
                        transition: all 0.3s ease;
                    }

                    .close-btn:hover {
                        color: #e74c3c;
                        transform: rotate(90deg);
                    }

                    .form-group {
                        margin-bottom: 1.5rem;
                    }

                    .form-group label {
                        display: block;
                        margin-bottom: 0.5rem;
                        color: #2c3e50;
                        font-weight: 500;
                    }

                    input,
                    select,
                    textarea {
                        width: 100%;
                        padding: 1rem;
                        border: 2px solid #e0e0e0;
                        border-radius: 8px;
                        font-size: 1rem;
                        transition: all 0.3s ease;
                    }

                    input:focus,
                    select:focus,
                    textarea:focus {
                        border-color: #3498db;
                        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
                        outline: none;
                    }

                    .submit-btn {
                        position: relative;
                        background: linear-gradient(135deg, #27ae60, #219a52);
                        color: white;
                        border: none;
                        padding: 1rem 2rem;
                        border-radius: 8px;
                        cursor: pointer;
                        overflow: hidden;
                        transition: all 0.3s ease;
                        width: 100%;
                    }

                    .hover-effect {
                        position: absolute;
                        background: rgba(255, 255, 255, 0.2);
                        width: 100%;
                        height: 100%;
                        left: -100%;
                        top: 0;
                        transition: left 0.4s ease;
                    }

                    .submit-btn:hover .hover-effect {
                        left: 100%;
                    }

                    /* Projects Container */
                    .projects-container {
                        display: grid;
                        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                        gap: 1.5rem;
                    }

                    .project-card {
                        background: white;
                        border-radius: 12px;
                        padding: 1.5rem;
                        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                        cursor: pointer;
                        transition: all 0.3s ease;
                        position: relative;
                    }

                    .project-card:hover {
                        transform: translateY(-5px);
                        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
                    }

                    .project-header {
                        margin-bottom: 1rem;
                    }

                    .employee-list {
                        max-height: 0;
                        overflow: hidden;
                        transition: all 0.4s ease;
                    }

                    .project-card.active .employee-list {
                        max-height: 1000px;
                        margin-top: 1rem;
                    }

                    .employee-item {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        padding: 1rem 0;
                        border-bottom: 1px solid #eee;
                        opacity: 0;
                        transform: translateY(10px);
                        animation: employeeEntry 0.3s ease forwards;
                    }

                    @keyframes employeeEntry {
                        to {
                            opacity: 1;
                            transform: translateY(0);
                        }
                    }

                    .rate-btn {
                        background: #3498db;
                        color: white;
                        border: none;
                        padding: 0.5rem 1rem;
                        border-radius: 6px;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    }

                    .rate-btn:hover {
                        background: #2980b9;
                        transform: translateY(-2px);
                    }

                    /* Rating Modal */
                    .rating-modal {
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0, 0, 0, 0.5);
                        display: none;
                        align-items: center;
                        justify-content: center;
                        backdrop-filter: blur(3px);
                    }

                    .modal-content {
                        background: white;
                        padding: 2rem;
                        border-radius: 16px;
                        width: 90%;
                        max-width: 500px;
                        transform: scale(0.9);
                        opacity: 0;
                        animation: modalOpen 0.3s ease forwards;
                    }

                    @keyframes modalOpen {
                        to {
                            transform: scale(1);
                            opacity: 1;
                        }
                    }

                    .modal-actions {
                        display: flex;
                        gap: 1rem;
                        margin-top: 2rem;
                    }

                    /* Ratings Table */
                    .ratings-table {
                        margin-top: 3rem;
                        background: white;
                        padding: 2rem;
                        border-radius: 16px;
                        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                    }

                    table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 1rem;
                    }

                    th,
                    td {
                        padding: 1rem;
                        text-align: left;
                        border-bottom: 1px solid #eee;
                    }

                    th {
                        background: #3498db;
                        color: white;
                        position: sticky;
                        top: 0;
                    }

                    .edit-btn {
                        background: #f1c40f;
                        color: #2c3e50;
                        border: none;
                        padding: 0.5rem 1rem;
                        border-radius: 6px;
                        cursor: pointer;
                        transition: all 0.3s ease;
                    }

                    .edit-btn:hover {
                        background: #f39c12;
                    }
                </style>

                <script>
                    let projects = JSON.parse(localStorage.getItem('projects')) || [];
                    let currentEmployee = null;
                    let currentProject = null;

                    // Toggle project form
                    function toggleProjectForm() {
                        document.querySelector('.project-form').classList.toggle('visible');
                    }

                    // Handle form submission
                    document.getElementById('projectForm').addEventListener('submit', function (e) {
                        e.preventDefault();

                        const newProject = {
                            id: Date.now(),
                            name: document.getElementById('projectName').value,
                            type: document.getElementById('projectType').value,
                            dueDate: document.getElementById('dueDate').value,
                            description: document.getElementById('projectDesc').value,
                            employees: [],
                            ratings: {}
                        };

                        projects.push(newProject);
                        localStorage.setItem('projects', JSON.stringify(projects));
                        renderProjects();
                        toggleProjectForm();
                        this.reset();
                    });

                    // Render projects
                    function renderProjects() {
                        const container = document.getElementById('projectsContainer');
                        container.innerHTML = '';

                        projects.forEach(project => {
                            const card = document.createElement('div');
                            card.className = 'project-card';
                            card.innerHTML = `
                <div class="project-header">
                    <h3>${project.name}</h3>
                    <p class="project-type">${project.type}</p>
                    <p class="due-date">Due: ${new Date(project.dueDate).toLocaleDateString()}</p>
                </div>
                <div class="employee-list">
                    ${project.employees.map(employee => `
                        <div class="employee-item">
                            <span>${employee}</span>
                            <button class="rate-btn" 
                                    onclick="openRatingModal('${employee}', ${project.id})">
                                Rate
                            </button>
                        </div>
                    `).join('')}
                </div>
            `;

                            card.addEventListener('click', function (e) {
                                if (!e.target.classList.contains('rate-btn')) {
                                    this.classList.toggle('active');
                                }
                            });

                            container.appendChild(card);
                        });

                        updateRatingsTable();
                    }

                    // Rating Modal Functions
                    function openRatingModal(employee, projectId) {
                        currentEmployee = employee;
                        currentProject = projectId;
                        document.getElementById('ratingModal').style.display = 'flex';
                        document.getElementById('selectedEmployee').textContent = employee;
                        document.getElementById('selectedProject').textContent =
                            projects.find(p => p.id === projectId).name;

                        // Load existing rating if available
                        const project = projects.find(p => p.id === projectId);
                        if (project.ratings[employee]) {
                            document.getElementById('employeeRating').value = project.ratings[employee].rating;
                            document.getElementById('employeeComments').value = project.ratings[employee].comments;
                        }
                    }

                    function closeRatingModal() {
                        document.getElementById('ratingModal').style.display = 'none';
                    }

                    function saveRating() {
                        const rating = parseFloat(document.getElementById('employeeRating').value);
                        const comments = document.getElementById('employeeComments').value;

                        const project = projects.find(p => p.id === currentProject);
                        project.ratings[currentEmployee] = { rating, comments };

                        localStorage.setItem('projects', JSON.stringify(projects));
                        closeRatingModal();
                        renderProjects();
                    }

                    // Update ratings table
                    function updateRatingsTable() {
                        const tbody = document.getElementById('ratingsBody');
                        tbody.innerHTML = '';

                        projects.forEach(project => {
                            Object.entries(project.ratings).forEach(([employee, data]) => {
                                const row = document.createElement('tr');
                                row.innerHTML = `
                    <td>${employee}</td>
                    <td>${project.name}</td>
                    <td>${data.rating}/10</td>
                    <td>${data.comments}</td>
                    <td>${new Date(project.dueDate).toLocaleDateString()}</td>
                    <td>
                        <button class="edit-btn" 
                                onclick="openRatingModal('${employee}', ${project.id})">
                            Edit
                        </button>
                    </td>
                `;
                                tbody.appendChild(row);
                            });
                        });
                    }

                    // Initial load
                    renderProjects();
                </script>
            </div>



            <div class="content-section" id="calendar">
                <h2>2025 Calendar</h2>
                <div class="year-calendar" id="2025-calendar"></div>
            </div>


            <!-- Notifications Panel -->
            <div class="notifications-panel">
                <div class="notification-header">
                    <h3>Notifications</h3>
                    <i class="fas fa-times close-notifications"></i>
                </div>
                <div class="notification-list">
                    <!-- Notifications will be populated here -->
                </div>
            </div>
        </div>

        <script>
            // script.js
            document.addEventListener('DOMContentLoaded', function () {
                // Sidebar navigation
                const sidebarItems = document.querySelectorAll('.sidebar-menu li');
                const contentSections = document.querySelectorAll('.content-section');

                sidebarItems.forEach(item => {
                    item.addEventListener('click', function () {
                        const target = this.dataset.target;

                        sidebarItems.forEach(i => i.classList.remove('active'));
                        this.classList.add('active');

                        contentSections.forEach(section => {
                            section.classList.remove('active');
                            if (section.id === target) {
                                setTimeout(() => section.classList.add('active'), 50);
                            }
                        });
                    });
                });

                // Notifications
                const notificationBell = document.querySelector('.notification-bell');
                const notificationsPanel = document.querySelector('.notifications-panel');

                notificationBell.addEventListener('click', () => {
                    notificationsPanel.classList.toggle('active');
                });

                document.querySelector('.close-notifications').addEventListener('click', () => {
                    notificationsPanel.classList.remove('active');
                });

                // Sample notifications
                const notifications = [
                    'New message received',
                    'Meeting at 2 PM',
                    'System update available'
                ];

                const notificationList = document.querySelector('.notification-list');
                notifications.forEach(text => {
                    const notification = document.createElement('div');
                    notification.className = 'notification-item';
                    notification.innerHTML = `
            ${text}
            <span class="close-notification">&times;</span>
        `;
                    notificationList.appendChild(notification);
                });

                // Close individual notifications
                document.querySelectorAll('.close-notification').forEach(btn => {
                    btn.addEventListener('click', function () {
                        this.parentElement.remove();
                    });
                });

                // Calendar generation

                // Messenger functionality
                const messageInput = document.querySelector('.message-input input');
                const chatMessages = document.querySelector('.chat-messages');

                document.querySelector('.message-input button').addEventListener('click', sendMessage);

                messageInput.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') sendMessage();
                });

                function sendMessage() {
                    const message = messageInput.value.trim();
                    if (message) {
                        const messageElement = document.createElement('div');
                        messageElement.className = 'message';
                        messageElement.textContent = message;
                        chatMessages.appendChild(messageElement);
                        messageInput.value = '';
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                }
            });
            // script.js (additional features)
            document.addEventListener('DOMContentLoaded', function () {
                // Add ripple effect to buttons
                document.addEventListener('click', function (e) {
                    const target = e.target.closest('.btn-ripple');
                    if (target) {
                        const ripple = document.createElement('div');
                        ripple.style.cssText = `
                position: absolute;
                width: 20px;
                height: 20px;
                background: rgba(255, 255, 255, 0.4);
                border-radius: 50%;
                transform: translate(-50%, -50%);
                pointer-events: none;
                animation: rippleEffect 0.6s linear;
            `;

                        const rect = target.getBoundingClientRect();
                        ripple.style.left = `${e.clientX - rect.left}px`;
                        ripple.style.top = `${e.clientY - rect.top}px`;

                        target.appendChild(ripple);
                        setTimeout(() => ripple.remove(), 600);
                    }
                });

                // Dynamic theme switcher
                const themeToggle = document.createElement('div');
                themeToggle.className = 'theme-toggle';
                document.body.appendChild(themeToggle);

                themeToggle.addEventListener('click', () => {
                    document.documentElement.classList.toggle('dark-mode');
                });

                // Replace your calendar generation code with this
                const calendarContainer = document.getElementById('2025-calendar');
                const selectedDates = new Set();

                function generateYearCalendar(year) {
                    calendarContainer.innerHTML = '';

                    for (let month = 0; month < 12; month++) {
                        const monthContainer = document.createElement('div');
                        monthContainer.className = 'month-container';

                        const monthHeader = document.createElement('div');
                        monthHeader.className = 'month-header';
                        monthHeader.textContent = new Date(year, month).toLocaleString('default', { month: 'long' });

                        const calendarGrid = document.createElement('div');
                        calendarGrid.className = 'calendar-grid';

                        // Add day labels
                        ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
                            const dayElement = document.createElement('div');
                            dayElement.className = 'calendar-day label';
                            dayElement.textContent = day.substring(0, 3);
                            calendarGrid.appendChild(dayElement);
                        });

                        // Calculate days
                        const firstDay = new Date(year, month, 1);
                        const startingDay = firstDay.getDay();
                        const daysInMonth = new Date(year, month + 1, 0).getDate();

                        // Add empty cells
                        for (let i = 0; i < startingDay; i++) {
                            const emptyDay = document.createElement('div');
                            emptyDay.className = 'calendar-day empty';
                            calendarGrid.appendChild(emptyDay);
                        }

                        // Add actual days
                        for (let day = 1; day <= daysInMonth; day++) {
                            const date = new Date(year, month, day);
                            const dateString = date.toISOString().split('T')[0];
                            const dayElement = document.createElement('div');
                            dayElement.className = `calendar-day ${selectedDates.has(dateString) ? 'selected' : ''}`;
                            dayElement.textContent = day;
                            dayElement.dataset.date = dateString;

                            calendarGrid.appendChild(dayElement);
                        }

                        monthContainer.appendChild(monthHeader);
                        monthContainer.appendChild(calendarGrid);
                        calendarContainer.appendChild(monthContainer);
                    }
                }

                // Handle date selection
                calendarContainer.addEventListener('click', (e) => {
                    const dayElement = e.target.closest('.calendar-day');
                    if (dayElement && !dayElement.classList.contains('empty') && !dayElement.classList.contains('label')) {
                        const date = dayElement.dataset.date;

                        if (selectedDates.has(date)) {
                            selectedDates.delete(date);
                            dayElement.classList.remove('selected');
                        } else {
                            selectedDates.add(date);
                            dayElement.classList.add('selected');
                        }
                    }
                });

                // Generate 2025 calendar
                generateYearCalendar(2025);



                // Typing indicator
                let typingTimeout;
                messageInput.addEventListener('input', () => {
                    // Show typing indicator
                    clearTimeout(typingTimeout);
                    typingTimeout = setTimeout(() => {
                        // Hide typing indicator after 1s
                    }, 1000);
                });
            });
            // Basic functionality examples
            function showEmployeeForm() {
                document.getElementById('employeeModal').style.display = 'flex';
            }

            function closeModal() {
                document.getElementById('employeeModal').style.display = 'none';
            }

            // Drag and drop functionality
            document.querySelectorAll('.fiche-item').forEach(item => {
                item.addEventListener('dragstart', handleDragStart);
                item.addEventListener('dragend', handleDragEnd);
            });

            function handleDragStart(e) {
                e.target.classList.add('dragging');
            }

            function handleDragEnd(e) {
                e.target.classList.remove('dragging');
            }

            // Form submission
            document.getElementById('employeeForm').addEventListener('submit', function (e) {
                e.preventDefault();
                // Add employee logic here
                closeModal();
            });
            document.querySelector('.add-btn').addEventListener('click', () => {
                document.getElementById('formOverlay').style.display = 'flex';
            });

            document.getElementById('closeForm').addEventListener('click', () => {
                document.getElementById('formOverlay').style.display = 'none';
            });
            function toggleLeftSidebar() {
                const sidebar = document.querySelector('.left-sidebar');
                const hamburger = document.querySelector('.left-hamburger i');
                sidebar.classList.toggle('active');
                hamburger.classList.toggle('fa-times');
            }

            function toggleRightSidebar() {
                const sidebar = document.querySelector('.right-sidebar');
                const hamburger = document.querySelector('.right-hamburger i');
                sidebar.classList.toggle('active');
                hamburger.classList.toggle('fa-times');
            }

            function toggleSidebar(side) {
                const sidebar = document.querySelector(side === 'left' ? '.sidebar' : '.dashboard-sidebar');
                const hamburger = document.querySelector(`${side}-hamburger i`);
                sidebar.classList.toggle('active');
                hamburger.classList.toggle('fa-times');
            }

            // Close sidebar when clicking outside
            document.addEventListener('click', function (event) {
                const leftSidebar = document.querySelector('.sidebar');
                const rightSidebar = document.querySelector('.dashboard-sidebar');
                const leftHamburger = document.querySelector('.left-hamburger');
                const rightHamburger = document.querySelector('.right-hamburger');

                if (!leftSidebar.contains(event.target) && !leftHamburger.contains(event.target)) {
                    leftSidebar.classList.remove('active');
                    document.querySelector('.left-hamburger i').classList.remove('fa-times');
                }

                if (!rightSidebar.contains(event.target) && !rightHamburger.contains(event.target)) {
                    rightSidebar.classList.remove('active');
                    document.querySelector('.right-hamburger i').classList.remove('fa-times');
                }
            });
            // Initialize sidebars as visible
            document.addEventListener('DOMContentLoaded', () => {
                // Set both sidebars to active on desktop
                if (window.innerWidth > 768) {
                    document.querySelector('.sidebar').classList.add('active');
                    document.querySelector('.dashboard-sidebar').classList.add('active');
                }
            });

            function toggleSidebar(side) {
                const sidebar = document.querySelector(side === 'left' ? '.sidebar' : '.dashboard-sidebar');
                const hamburgerIcon = document.querySelector(`${side}-hamburger i`);

                // Toggle only the clicked sidebar
                sidebar.classList.toggle('active');

                // Update icon state
                hamburgerIcon.classList.toggle('fa-bars');
                hamburgerIcon.classList.toggle('fa-times');

                // Update main content margins independently
                const mainContent = document.querySelector('.main-content');
                if (side === 'left') {
                    mainContent.style.marginRight = sidebar.classList.contains('active') ? '300px' : '0';
                    mainContent.style.marginLeft = sidebar.classList.contains('active') ? '250px' : '0';
                } else {
                    mainContent.style.marginRight = sidebar.classList.contains('active') ? '300px' : '0';
                    mainContent.style.marginLeft = sidebar.classList.contains('active') ? '250px' : '0';
                }
            }

            // Close only the relevant sidebar when clicking outside (mobile)
            document.addEventListener('click', function (event) {
                const leftSidebar = document.querySelector('.sidebar');
                const rightSidebar = document.querySelector('.dashboard-sidebar');
                const leftHamburger = document.querySelector('.left-hamburger');
                const rightHamburger = document.querySelector('.right-hamburger');

                // Check for left sidebar
                if (!leftSidebar.contains(event.target) && !leftHamburger.contains(event.target)) {
                    if (leftSidebar.classList.contains('active')) {
                        leftSidebar.classList.remove('active');
                        document.querySelector('.left-hamburger i').classList.replace('fa-times', 'fa-bars');
                        document.querySelector('.main-content').style.marginLeft = '0';
                    }
                }

                // Check for right sidebar
                if (!rightSidebar.contains(event.target) && !rightHamburger.contains(event.target)) {
                    if (rightSidebar.classList.contains('active')) {
                        rightSidebar.classList.remove('active');
                        document.querySelector('.right-hamburger i').classList.replace('fa-times', 'fa-bars');
                        document.querySelector('.main-content').style.marginRight = '0';
                    }
                }
            });
            // Settings Interactions
            document.addEventListener('DOMContentLoaded', () => {
                // Theme Color Selection
                document.querySelectorAll('.color-swatch').forEach(swatch => {
                    swatch.addEventListener('click', function () {
                        document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('active'));
                        this.classList.add('active');
                        const color = this.dataset.color;
                        document.documentElement.style.setProperty('--primary-color', color);
                    });
                });

                // Dark Mode Toggle
                const darkModeToggle = document.getElementById('darkModeToggle');
                darkModeToggle.addEventListener('change', function () {
                    document.body.classList.toggle('dark-mode', this.checked);
                    localStorage.setItem('darkMode', this.checked);
                });

                // Initialize dark mode
                const isDarkMode = localStorage.getItem('darkMode') === 'true';
                darkModeToggle.checked = isDarkMode;
                document.body.classList.toggle('dark-mode', isDarkMode);

                // Custom Select Interactions
                document.querySelectorAll('.styled-select').forEach(select => {
                    select.addEventListener('focus', () => {
                        select.parentElement.classList.add('focused');
                    });
                    select.addEventListener('blur', () => {
                        select.parentElement.classList.remove('focused');
                    });
                });
            });
            //time
            let timeEntries = JSON.parse(localStorage.getItem('timeEntries')) || [];

            function addTimeEntry() {
                const entry = {
                    lastName: document.getElementById('lastName').value.trim(),
                    firstName: document.getElementById('firstName').value.trim(),
                    employeeId: document.getElementById('employeeId').value.trim(),
                    date: document.getElementById('workDate').value,
                    hours: document.getElementById('hours').value
                };

                if (!validateEntry(entry)) return;

                timeEntries.push(entry);
                localStorage.setItem('timeEntries', JSON.stringify(timeEntries));
                updateTimeTable();
                clearForm();
            }

            function validateEntry(entry) {
                const requiredFields = ['lastName', 'firstName', 'employeeId', 'date', 'hours'];
                if (requiredFields.some(field => !entry[field])) {
                    showError('Please fill all required fields');
                    return false;
                }
                return true;
            }

            function updateTimeTable() {
                const tbody = document.getElementById('timeEntries');
                tbody.innerHTML = '';

                timeEntries.forEach((entry, index) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
            <td>${entry.lastName}</td>
            <td>${entry.firstName}</td>
            <td>${entry.employeeId}</td>
            <td>${new Date(entry.date).toLocaleDateString()}</td>
            <td>${entry.hours}h</td>
            <td>
                <button class="delete-btn" onclick="deleteEntry(${index})">
                    Delete
                </button>
            </td>
        `;
                    tr.style.animation = 'newEntry 0.4s ease-out';
                    tbody.appendChild(tr);
                });
            }

            function deleteEntry(index) {
                const row = document.getElementById('timeEntries').children[index];
                row.style.animation = 'removeEntry 0.3s ease-out forwards';
                setTimeout(() => {
                    timeEntries.splice(index, 1);
                    localStorage.setItem('timeEntries', JSON.stringify(timeEntries));
                    updateTimeTable();
                }, 300);
            }

            function clearForm() {
                document.querySelectorAll('.form-input').forEach(input => {
                    input.value = '';
                    input.dispatchEvent(new Event('input'));
                });
            }

            function showError(message) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.textContent = message;
                document.body.appendChild(errorDiv);
                setTimeout(() => errorDiv.remove(), 3000);
            }

            // Initial load
            updateTimeTable();
        </script>
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
    <script>
    function toggleMessaging() {
        const messagingSection = document.getElementById('messaging-section');
        if (messagingSection.style.display === 'none') {
            messagingSection.style.display = 'block'; // Show the messaging section
        } else {
            messagingSection.style.display = 'none'; // Hide the messaging section
        }
    }

    function sendMessage(event) {
        event.preventDefault(); // Prevent the default form submission

        const input = document.querySelector('.message-input input'); // Get the input field
        const message = input.value; // Get the message text

        // Here you would typically send the message to the server via AJAX
        // For demonstration, we'll just log it to the console
        console.log("Message sent: " + message);

        // Clear the input field
        input.value = '';
    }
</script>
<script>
        // JavaScript to handle showing/hiding forms
        document.getElementById('showAddForm').onclick = function() {
            document.getElementById('formOverlay').style.display = 'block';
        };
        document.getElementById('showEditForm').onclick = function() {
            document.getElementById('editFormOverlay').style.display = 'block';
        };
        document.getElementById('showDeleteForm').onclick = function() {
            document.getElementById('deleteFormOverlay').style.display = 'block';
        };
        document.getElementById('closeAddForm').onclick = function() {
            document.getElementById('formOverlay').style.display = 'none';
        };
        document.getElementById('closeEditForm').onclick = function() {
            document.getElementById('editFormOverlay').style.display = 'none';
        };
        document.getElementById('closeDeleteForm').onclick = function() {
            document.getElementById('deleteFormOverlay').style.display = 'none';
        };
    </script>
</body>

</html>