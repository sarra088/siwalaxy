<?php
session_start();
require 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code_saisi = $_POST['code'];
    $new_password = $_POST['new_password'];

    if (!isset($_SESSION['reset_code']) || !isset($_SESSION['reset_email'])) {
        echo "Aucune demande de réinitialisation trouvée.";
        exit;
    }

    if ($code_saisi == $_SESSION['reset_code']) {
        // Hasher le nouveau mot de passe
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Mettre à jour le mot de passe dans la base de données
        $sql = "UPDATE entreprises SET mot_de_passe = ? WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$hashed_password, $_SESSION['reset_email']]);

        echo "Mot de passe réinitialisé avec succès ! <a href='login_entreprise.php'>Se connecter</a>";

        // Supprimer les données de session après réinitialisation
        session_unset();
        session_destroy();
    } else {
        echo "Code incorrect.";
    }
}
?>

<form action="" method="POST">
    <input type="text" name="code" placeholder="Entrez le code reçu" required><br>
    <input type="password" name="new_password" placeholder="Nouveau mot de passe" required><br>
    <button type="submit">Réinitialiser</button>
</form>