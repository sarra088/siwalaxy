<?php
session_start();
require 'config.php';

$error = ''; // Variable pour stocker les messages d'erreur

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $code_saisi = $_POST['code'];

    // Vérifier si le code correspond à celui stocké en session
    if (isset($_SESSION['reset_code']) && $_SESSION['reset_code'] == $code_saisi) {
        // Redirection vers la page pour entrer un nouveau mot de passe
        header("Location: nouveau_mdp.php");
        exit();
    } else {
        $error = "Code incorrect, veuillez réessayer.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
    Reset password</title>
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

input[type="text"], input[type="password"] {
    background: #ffffff;
    color: #000000;
}

input[type="text"]:focus, input[type="password"]:focus {
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
        <h2>Enter the reset code</h2>

        <?php if (!empty($error)): ?>
            <p style="color:red;">
                <?php echo $error; ?>
            </p>
        <?php endif; ?>

        <!-- Formulaire -->
        <form action="" method="POST">
            <input type="text" name="code" placeholder="Enter the code received" required>
            <button type="submit">
            Reset</button>
        </form>
    </div>
</body>

</html>