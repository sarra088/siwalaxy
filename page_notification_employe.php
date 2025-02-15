<?php
session_start();
require 'config.php';

if (!isset($_SESSION['id_employe'])) {
    header('Location: login_employe.php');
    exit();
}

$id_employe = $_SESSION['id_employe'];

// Récupérer les notifications
$notifications = $conn->query("SELECT id_employe, message, vu FROM notifications WHERE matricule = (SELECT matricule FROM employes WHERE id_employe = $id_employe)")->fetchAll(PDO::FETCH_ASSOC);

// Marquer comme lues
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn->query("UPDATE notifications SET vu = 1 WHERE matricule = (SELECT matricule FROM employes WHERE id_employe = $id_employe)");
    header('Location: page_notification_employe.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>

    <h1>Notifications</h1>

    <?php if (!empty($notifications)): ?>
        <ul>
            <?php foreach ($notifications as $notif): ?>
                <li style="<?= $notif['vu'] ? 'color: gray;' : 'color: black; font-weight: bold;' ?>">
                    <?= htmlspecialchars($notif['message']) ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <form method="POST">
            <button type="submit">Marquer comme lues</button>
        </form>
    <?php else: ?>
        <p>Aucune notification.</p>
    <?php endif; ?>

</body>

</html>