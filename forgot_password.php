<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Inclure les fichiers PHPMailer (car pas d'installation via Composer)
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

session_start(); // Démarrer la session

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require 'config.php';

    $email = $_POST['email'];

    // Vérifier si l'email existe
    $stmt = $conn->prepare("SELECT id_entreprise FROM entreprises WHERE email = ?");
    $stmt->execute([$email]);
    $entreprise = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($entreprise) {
        // Générer un code aléatoire à 6 chiffres
        $code = rand(100000, 999999);

        // Enregistrer le code dans la session
        $_SESSION['reset_code'] = $code;
        $_SESSION['reset_email'] = $email;

        // Envoyer l'email avec PHPMailer
        $mail = new PHPMailer(true);
        try {
            // Configuration du serveur SMTP
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com'; // Utilisation de Gmail
            $mail->SMTPAuth = true;
            $mail->Username = 'siwalaxy@gmail.com'; // Remplace par ton adresse Gmail
            $mail->Password = 'pfvq ytmw pegy gfxe'; // Remplace par un mot de passe d'application Gmail
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Paramètres de l'email
            $mail->setFrom('siwalaxy@gmail.com', 'SIWALAXY');
            $mail->addAddress($email);
            $mail->Subject = 'Code de réinitialisation de mot de passe';
            $mail->Body = "Bonjour,\n\nVotre code de réinitialisation est : $code\n\nMerci.";

            $mail->send();

            // Rediriger vers la page de vérification
            header("Location: verify_code.php");
            exit(); // Terminer le script après la redirection
        } catch (Exception $e) {
            $message = "Échec de l'envoi de l'email. Erreur : {$mail->ErrorInfo}";
        }
    } else {
        $message = "Aucun compte trouvé avec cet email.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password</title>
    <style>
           /* Global Styles */
body {
    font-family: 'Poppins', sans-serif;
    margin: 0;
    padding: 0;
    color: #ffffff;
    background: #0F172A;
    background-attachment: fixed;
    overflow-x: hidden;
}

/* Logo */
.logo {
    position: absolute;
    top: 20px;
    left: 20px;
    width: 100px;
    height: auto;
    opacity: 0.9;
    transition: transform 0.3s ease-in-out;
}
.logo:hover {
    transform: scale(1.1) rotate(5deg);
}

/* Header */
header {
    text-align: center;
    padding: 30px 10px;
    background: linear-gradient(135deg, #1E293B, #3b2a79);
    box-shadow: 0px 4px 10px rgba(59, 42, 121, 0.6);
    margin-bottom: 40px;
    animation: fadeInDown 1s ease-in-out;
}
header h1 {
    font-size: 2.5rem;
    color: #ffffff;
    letter-spacing: 1px;
}

/* Main Content */
.content {
    max-width: 500px;
    margin: 0 auto;
    padding: 30px;
    background: #1E293B;
    border-radius: 10px;
    box-shadow: 0px 5px 15px rgba(124, 58, 237, 0.4);
    transition: transform 0.3s ease-in-out;
}
.content:hover {
    transform: translateY(-5px);
}
.content h2 {
    font-size: 2rem;
    text-align: center;
    color: #7C3AED;
    margin-bottom: 20px;
}
.content p {
    font-size: 1rem;
    text-align: center;
    margin-bottom: 20px;
}

/* Form */
form {
    display: flex;
    flex-direction: column;
    gap: 15px;
}
label {
    font-size: 1rem;
    font-weight: bold;
    color: #ffffff;
}
input[type="email"] {
    padding: 10px;
    font-size: 1rem;
    border: none;
    border-radius: 5px;
    outline: none;
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
    transition: box-shadow 0.3s ease-in-out;
}
input[type="email"]:focus {
    box-shadow: 0px 0px 10px rgba(124, 58, 237, 0.7);
}
input[type="email"]::placeholder {
    color: #cccccc;
}
button {
    padding: 12px;
    font-size: 1rem;
    font-weight: bold;
    background: linear-gradient(135deg, #7C3AED, #5e0461);
    border: none;
    border-radius: 5px;
    cursor: pointer;
    color: #ffffff;
    transition: all 0.3s ease;
    box-shadow: 0px 4px 10px rgba(124, 58, 237, 0.4);
}
button:hover {
    background: linear-gradient(135deg, #5e0461, #300101);
    transform: scale(1.05);
}

/* Footer */
footer {
    text-align: center;
    padding: 15px;
    background-color: rgba(0, 0, 0, 0.6);
    color: #ffffff;
    margin-top: 40px;
    animation: fadeInUp 1s ease-in-out;
}
a {
    text-decoration: none;
    color: #ffffff;
    transition: color 0.3s ease-in-out;
}
a:hover {
    color: #7C3AED;
}

/* Animations */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

    </style>
</head>

<body>

    <!-- Logo -->
    <img src="logo.png" alt="Logo Siwalaxy" class="logo">

    <!-- En-tête -->
    <header>
        <h1>Forgot password</h1>
    </header>

    <!-- Contenu principal -->
    <div class="content">
        <h2>Reset your password</h2>
        <p>Please enter your registered email address:</p>
        <form action="" method="POST">
            <label for="email">
            Email address</label>
            <input type="email" id="email" name="email" placeholder="Enter your email address" required>
            <button type="submit">
            Send</button>
        </form>
        <?php if (!empty($message)): ?>
            <p>
                <?php echo $message; ?>
            </p>
        <?php endif; ?>
    </div>

    <!-- Pied de page -->
    <footer>
        &copy; 2025 Siwalaxy - Simplify your human resources.
    </footer>

</body>

</html>