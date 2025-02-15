<?php
session_start();
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
$employes = $conn->query("SELECT id_employe, nom, prenom FROM employes WHERE id_entreprise = $id_entreprise")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Feuilles de Temps</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f0f8ff;
            /* Couleur de fond bleu clair */
        }

        h1 {
            color: #003366;
            /* Bleu marine */
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #003366;
            padding: 10px;
            text-align: left;
        }

        th {
            background-color: #003366;
            color: white;
        }

        .form-section {
            margin-top: 20px;
            padding: 20px;
            background-color: #e6f7ff;
            /* Bleu très clair */
            border: 1px solid #003366;
            border-radius: 5px;
        }

        .form-section input,
        .form-section select {
            padding: 10px;
            margin: 5px 0;
            width: 100%;
            border: 1px solid #003366;
            border-radius: 5px;
        }

        .form-section button {
            background-color: #003366;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            border-radius: 5px;
        }

        .form-section button:hover {
            background-color: #0056b3;
            /* Couleur au survol */
        }
    </style>
</head>

<body>

    <h1>Gestion des Feuilles de Temps</h1>

    <div class="form-section">
        <h2>Ajouter une Feuille de Temps</h2>
        <form method="post">
            <select name="id_employe" required>
                <option value="">Sélectionnez un employé</option>
                <?php foreach ($employes as $employe): ?>
                    <option value="<?= $employe['id_employe'] ?>"><?= htmlspecialchars($employe['nom'] . ' ' . $employe['prenom']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="date" required>
            <input type="number" name="heures_travaillees" placeholder="Heures travaillées" required>
            <button type="submit">Ajouter</button>
        </form>
    </div>

    <h2>Liste des Feuilles de Temps</h2>
    <table>
        <thead>
            <tr>
                <th>Nom Employé</th>
                <th>Date</th>
                <th>Heures Travaillées</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($feuilles_temps as $feuille): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($feuille['nom'] . ' ' . $feuille['prenom']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($feuille['date']) ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($feuille['heures_travaillees']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</body>

</html>