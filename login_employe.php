<?php
session_start(); // Démarrer la session

error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'config.php'; // Connexion à la base de données

$error_message = ''; // Variable pour stocker le message d'erreur

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $matricule = trim($_POST['matricule']);

    // Vérifier si le matricule existe dans la base
    $sql = "SELECT * FROM employes WHERE matricule = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$matricule]);
    $employe = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($employe) {
        // Enregistrer les informations dans la session
        $_SESSION['id_employe'] = $employe['id_employe'];
        $_SESSION['matricule'] = $matricule;

        // Rediriger vers la page d'accueil
        header("Location: pageacceuilemploye.php");
        exit();
    } else {
        // Définir le message d'erreur si le matricule n'est pas trouvé
        $error_message = "❌ Registration number not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="login.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet" />
    <title>Siwalaxy</title>
    <style>
       * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: "Poppins", sans-serif;
  }
  
  body {
    background-color: #0F172A;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    height: 100vh;
  }
  
  .container {
    background-color: #1E293B;
    border-radius: 150px;
    box-shadow: 0 5px 15px #3b2a79;
    position: relative;
    overflow: hidden;
    width: 768px;
    max-width: 100%;
    min-height: 480px;
  }
  .container span {
    font-size: 12px;
  }
  
  .container a {
    color: #fff;
    font-size: 13px;
    text-decoration: underline;
    margin: 15px 0 10px;
  }
  .container a:hover{
    color: #1d74dd;
  }
  .container button {
    background-color: #5e0461;
    color: #fff;
    padding: 10px 45px;
    border: 1px solid transparent;
    border-radius: 8px;
    font-weight: 600;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    margin-top: 10px;
    cursor: pointer;
    transition: background 0.3s ease, transform 0.2s ease;
  }
  .container button:hover{
    background: #300101;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.35);
    transform: scale(1.05);
  }
  
  .container button.hidden {
    background-color: transparent;
    border-color: #fff;
  }
  
  .container form {
    background-color: #1E293B;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 0 40px;
    height: 100%;
  }
  
  .container input {
    background: transparent;
    border: none;
    margin: 8px 0;
    padding: 10px 15px;
    font-size: 13px;
    border-radius: 8px;
    width: 100%;
    outline: none;
  }
  .container p{
    color: #fff;
    font-size: small;
  }
  .container h1{
    color: #7C3AED;
  }
  .toogle-panel .toogle-right span{
    font-size: medium;
    color: #ffcc00;
  }
  
  .sign-up, .sign-in {
    position: absolute;
    top: 0;
    height: 100%;
    transition: all 0.6s ease-in-out;
  }
  
  .sign-in {
    left: 0;
    width: 50%;
    z-index: 3;
  }
  .sign-in input{
    background: #d3d4d6;
  }
  
  .container.active .sign-in {
    transform: translateX(100%);
  }
  
  .sign-up {
    left: 0;
    width: 50%;
    z-index: 1;
    opacity: 0;
  }
  
  .container.active .sign-up {
    transform: translateX(100%);
    opacity: 1;
    z-index: 5;
    animation: move 0.6s;
  }
  
  @keyframes move {
    0%, 49.99%{
      opacity: 0;
      z-index: 1;
    }
     50%, 100%{
      opacity: 1;
      z-index: 5;
    }
  }
  
  .icons {
    margin: 20px 0;
  }
  
  .icons a {
    border: 1px solid #ccc;
    border-radius: 20%;
    display: inline-flex;
    justify-content: center;
    align-items: center;
    margin: 0 3px;
    width: 40px;
    height: 40px;
  }
  
  .toogle-container {
    position: absolute;
    top: 0;
    left: 50%;
    width: 50%;
    height: 100%;
    overflow: hidden;
    border-radius: 150px;
    z-index: 1000;
    transition: all 0.6s ease-in-out;
  }
  
  .container.active .toogle-container {
    transform: translateX(-100%);
    border-radius: 150px;
  }
  
  .toogle {
    background-color: #01194a;
    height: 100%;
    background: linear-gradient(to right, #080242, #1d74dd);
    color: #fff;
    position: relative;
    left: -100%;
    width: 200%;
    transform: translateX(0);
    transition: all 0.6s ease-in-out;
  }
  
  .container.active .toogle {
    transform: translateX(50%);
  }
  
  .toogle-panel {
    position: absolute;
    width: 50%;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    flex-direction: column;
    padding: 0 30px;
    text-align: center;
    top: 0;
    transform: translateX(0);
    transition: all 0.6s ease-in-out;
  }
  
  .toogle-left {
    transform: translateX(-200%);
  }
  
  .container.active .toogle-left {
    transform: translateX(0);
  }
  
  .toogle-right {
    right: 0;
    transform: translateX(0);
  }
  
  .container.active .toogle-right {
    transform: translateX(200%);
  }

    </style>
</head>

<body>
    <div class="container" id="container">
        <!-- Formulaire de connexion -->
        <div class="sign-in">
            <form action="" method="POST">
                <h1>Welcome Back!</h1>
                <p class="subtext">Log in to access your account</p>
                <input type="text" name="matricule" placeholder="Matricule" required />
                <?php if (!empty($error_message)): ?>
                    <p class="error-message">
                        <?php echo $error_message; ?>
                    </p>
                <?php endif; ?>
                <button type="submit">Log In</button>
            </form>
        </div>

        <div class="toogle-container">
            <div class="toogle">
                <div class="toogle-panel toogle-right">
                    <video width="120%" height="auto" controls autoplay loop muted>
                        <source src="vid.mp4" type="video/mp4" />
                        Your browser does not support the video tag.
                    </video>
                </div>
            </div>
        </div>
    </div>
</body>

</html>