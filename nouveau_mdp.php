<?php
session_start(); // Démarrer la session

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php'; // Connexion à la base de données

$error = ''; // Variable pour stocker les messages d'erreur

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_SESSION['reset_email'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Vérifier si les mots de passe correspondent
    if ($new_password !== $confirm_password) {
        $error = "Les mots de passe ne correspondent pas.";
    } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
        $error = "Le mot de passe doit contenir au moins une lettre majuscule et un chiffre.";
    } else {
        // Hacher le mot de passe
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Mettre à jour le mot de passe dans la bonne table
        $stmt = $conn->prepare("UPDATE entreprises SET mot_de_passe = ? WHERE email = ?");
        $stmt->execute([$hashed_password, $email]);

        // Supprimer les données de réinitialisation après utilisation
        unset($_SESSION['reset_code']);
        unset($_SESSION['reset_email']);

        // Stocker le message de succès dans la session
        $_SESSION['success_message'] = "Mot de passe mis à jour avec succès !";

        // Rediriger vers la page de connexion
        header("Location: login_entreprise.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New password</title>
    <style>
          /* General */
body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
    padding: 0;
    color: #ffffff;
    background: #0F172A;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    overflow: hidden;
}

/* Container */
.container {
    max-width: 400px;
    width: 100%;
    padding: 30px;
    background: #1E293B;
    border-radius: 15px;
    box-shadow: 0 15px 40px rgba(59, 42, 121, 0.5);
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.container:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 50px rgba(59, 42, 121, 0.7);
}

/* Logo */
.logo {
    position: absolute;
    top: 20px;
    left: 20px;
    width: 100px;
    height: auto;
    transition: transform 0.3s ease;
}

.logo:hover {
    transform: scale(1.1);
}

/* Title */
h2 {
    font-size: 2rem;
    color: #7C3AED;
    margin-bottom: 20px;
    animation: fadeIn 1s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Error Messages */
.error-message {
    color: #ff4d4d;
    margin-bottom: 15px;
    font-size: 0.9rem;
    animation: shake 0.5s ease-in-out;
}

@keyframes shake {
    0%, 100% {
        transform: translateX(0);
    }
    25% {
        transform: translateX(-10px);
    }
    50% {
        transform: translateX(10px);
    }
    75% {
        transform: translateX(-10px);
    }
}

/* Form */
form {
    display: flex;
    flex-direction: column;
}

input {
    padding: 12px;
    margin: 10px 0;
    font-size: 1rem;
    border: none;
    border-radius: 8px;
    outline: none;
    background: #2D3A4F;
    color: #ffffff;
    transition: background 0.3s ease, box-shadow 0.3s ease;
}

input:focus {
    background: #3C4B63;
    box-shadow: 0 0 10px rgba(124, 58, 237, 0.5);
}

input[type="password"] {
    background: #ffffff;
    color: #000000;
}

input[type="password"]:focus {
    background: #f0f0f0;
    box-shadow: 0 0 10px rgba(124, 58, 237, 0.5);
}

button {
    padding: 12px;
    font-size: 1rem;
    font-weight: bold;
    background-color: #7C3AED;
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.3s ease, box-shadow 0.3s ease;
}

button:hover {
    background-color: #5e0461;
    transform: translateY(-3px);
    box-shadow: 0 5px 15px rgba(124, 58, 237, 0.4);
}

button:active {
    transform: translateY(0);
    box-shadow: 0 3px 10px rgba(124, 58, 237, 0.4);
}
    </style>
</head>

<body>
    <!-- Logo en haut à gauche -->
    <img src="logo.png" alt="Logo Siwalaxy" class="logo">

    <div class="container">
        <h2>Set a new password</h2>

        <!-- Message d'erreur -->
        <?php if (!empty($error)): ?>
            <p class="error-message">
                <?php echo $error; ?>
            </p>
        <?php endif; ?>

        <!-- Formulaire -->
        <form action="" method="POST">
            <input type="password" name="new_password" required placeholder="New password">
            <input type="password" name="confirm_password" required placeholder="
Confirm the password">
            <button type="submit">Change the password</button>
        </form>
    </div>
</body>

</html>