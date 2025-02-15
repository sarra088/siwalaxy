<?php
session_start();
include 'config.php'; // Connexion à la base

// Vérifier si l'entreprise est connectée
if (!isset($_SESSION['id_entreprise'])) {
    header('Location: login_entreprise.php');
    exit();
}

$id_entreprise = $_SESSION['id_entreprise'];

// Récupérer les départements de l'entreprise connectée
$departements = $conn->prepare("SELECT id_departement, nom FROM departements WHERE id_entreprise = ?");
$departements->execute([$id_entreprise]);
$departements = $departements->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Vérifier que toutes les données sont présentes
    if (
        empty($_POST['matricule']) || empty($_POST['nom']) || empty($_POST['prenom']) ||
        empty($_POST['num_cin']) || empty($_POST['email']) || empty($_POST['num_tel']) ||
        empty($_POST['id_departement']) || empty($_POST['date_naissance']) || empty($_POST['titre'])
    ) {
        echo "❌ Tous les champs doivent être remplis.";
        exit();
    }

    // Récupération des valeurs avec validation
    $matricule = $_POST['matricule'];
    $num_cin = $_POST['num_cin'];
    $num_tel = $_POST['num_tel'];

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

    // Autres champs
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $email = $_POST['email'];
    $id_departement = $_POST['id_departement'];
    $date_naissance = $_POST['date_naissance'];
    $titre = $_POST['titre'];

    // Requête SQL pour insérer l'employé
    $sql = "INSERT INTO employes (matricule, nom, prenom, num_cin, email, num_tel, id_departement, date_naissance, titre, id_entreprise) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    if ($stmt->execute([$matricule, $nom, $prenom, $num_cin, $email, $num_tel, $id_departement, $date_naissance, $titre, $id_entreprise])) {
        echo "✅ Employé ajouté avec succès !";
    } else {
        echo "❌ Erreur lors de l'ajout de l'employé.";
    }
}
?>

<!-- Formulaire d'ajout d'un employé -->
<form method="post">
    Matricule (4 chiffres) : <input type="text" name="matricule" pattern="\d{4}" required><br>
    Nom : <input type="text" name="nom" required><br>
    Prénom : <input type="text" name="prenom" required><br>
    Numéro CIN (8 chiffres) : <input type="text" name="num_cin" pattern="\d{8}" required><br>
    Email : <input type="email" name="email" required><br>
    Numéro de téléphone (8 chiffres) : <input type="text" name="num_tel" pattern="\d{8}" required><br>
    Date de naissance : <input type="date" name="date_naissance" required><br>
    Titre : <input type="text" name="titre" required><br>

    Département :
    <select name="id_departement" required>
        <option value="" disabled selected>Sélectionnez un département</option>
        <?php foreach ($departements as $departement): ?>
            <option value="<?= $departement['id_departement'] ?>"><?= htmlspecialchars($departement['nom']) ?></option>
        <?php endforeach; ?>
    </select><br>

    <button type="submit">Ajouter</button>
</form>