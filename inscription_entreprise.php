<?php
session_start(); // Démarrer la session
require 'config.php'; // Connexion à la BDD

$error_message = ''; // Variable pour stocker les messages d'erreur
$nom_societe = $email = $adresse = $pays = $ville = $code_postal = $date_fondation = $fax = $telephone = $nombre_departement = $nb_employes = ''; // Initialisation des variables

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nom_societe = $_POST['nom_societe'];
    $email = $_POST['email'];
    $adresse = $_POST['adresse'];
    $pays = $_POST['pays'];
    $ville = $_POST['ville'];
    $code_postal = $_POST['code_postal'];
    $date_fondation = !empty($_POST['date_fondation']) ? $_POST['date_fondation'] : NULL;
    $fax = !empty($_POST['fax']) ? $_POST['fax'] : NULL;
    $telephone = $_POST['telephone'];
    $nombre_departement = $_POST['nombre_departement'];
    $nb_employes = $_POST['nb_employes'];
    $mot_de_passe = $_POST['mot_de_passe'];
    $confirm_mot_de_passe = $_POST['confirmer_mot_de_passe'];

    // Vérification des conditions du mot de passe
    if ($mot_de_passe !== $confirm_mot_de_passe) {
        $error_message = "Passwords do not match.";
    } elseif (!preg_match('/[A-Z]/', $mot_de_passe) || !preg_match('/[0-9]/', $mot_de_passe) || strlen($mot_de_passe) < 8) {
        $error_message = "Password must contain at least 8 characters, one uppercase letter, and one digit.";
    } elseif (!preg_match('/^\d{4}$/', $code_postal)) {
        $error_message = "Postal Code must be exactly 4 digits.";
    } elseif (!preg_match('/^\d{8}$/', $telephone)) {
        $error_message = "Phone Number must be exactly 8 digits.";
    } elseif (!preg_match('/^\d{8}$/', $fax) && !empty($fax)) {
        $error_message = "Fax must be exactly 8 digits.";
    } else {
        // Hacher le mot de passe
        $hashed_password = password_hash($mot_de_passe, PASSWORD_DEFAULT);

        // Insertion des données de l'entreprise
        $sql = "INSERT INTO entreprises (nom_societe, email, adresse, pays, ville, code_postal, date_fondation, fax, telephone, nombre_departement, nb_employes, mot_de_passe) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->execute([$nom_societe, $email, $adresse, $pays, $ville, $code_postal, $date_fondation, $fax, $telephone, $nombre_departement, $nb_employes, $hashed_password]);

        $id_entreprise = $conn->lastInsertId();

        // Insertion des départements
        for ($i = 1; $i <= $nombre_departement; $i++) {
            if (!empty($_POST["departement_$i"])) {
                $nom_departement = $_POST["departement_$i"];
                $sql_dep = "INSERT INTO departements (id_entreprise, nom) VALUES (?, ?)";
                $stmt_dep = $conn->prepare($sql_dep);
                $stmt_dep->execute([$id_entreprise, $nom_departement]);
            }
        }

        // Stocker le message de succès dans la session
        $_SESSION['success_message'] = "Registration successful! You can now <a href='login_entreprise.php'>login here</a>.";

    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Company Registration</title>
    <style>
           @import url("https://fonts.googleapis.com/css2?family=Poppins:wght@200;300;400;500;600;700&display=swap");

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: "Poppins", sans-serif;
}

body {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
  background-color: #0F172A;
  animation: fadeIn 0.8s ease-in-out;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-20px); }
  to { opacity: 1; transform: translateY(0); }
}

.container {
  position: relative;
  max-width: 700px;
  width: 100%;
  background: #1E293B;
  padding: 40px 30px;
  border-radius: 15px;
  box-shadow: 0 12px 30px rgba(108, 92, 247, 0.3);
  transform: scale(0.95);
  animation: popIn 0.5s ease-in-out forwards;
}

@keyframes popIn {
  from { transform: scale(0.95); opacity: 0; }
  to { transform: scale(1); opacity: 1; }
}

.container header {
  font-size: 2rem;
  color: #7C3AED;
  font-weight: 600;
  text-align: center;
  margin-bottom: 25px;
  animation: fadeIn 1s ease-in-out;
}

.form {
  display: flex;
  flex-direction: column;
}

.input-box {
  position: relative;
  margin-bottom: 20px;
}

.input-box input {
  width: 100%;
  padding: 14px;
  margin-top: 5px;
  border: 2px solid #3b2a79;
  border-radius: 12px;
  font-size: 1rem;
  background-color: #2C3E50;
  color: #fff;
  transition: border-color 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
}

.input-box input:focus {
  border-color: #7C3AED;
  box-shadow: 0 0 10px rgba(124, 58, 237, 0.4);
  background-color: #34495E;
  outline: none;
}

.input-box label {
  font-weight: 600;
  color: #fff;
  position: absolute;
  top: -10px;
  left: 15px;
  background: #1E293B;
  padding: 0 8px;
  transition: all 0.3s ease;
  border-radius: 5px;
}

.form button {
  height: 55px;
  width: 100%;
  color: #fff;
  font-size: 1.2rem;
  font-weight: 600;
  margin-top: 30px;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  background: linear-gradient(135deg, #7C3AED, #3b2a79);
  transition: transform 0.3s ease, box-shadow 0.3s ease, background-color 0.3s ease;
}

.form button:hover {
  transform: translateY(-3px);
  box-shadow: 0 8px 20px rgba(108, 92, 247, 0.4);
  background: linear-gradient(135deg, #5e0461, #300101);
}

.form button:active {
  transform: translateY(0);
  box-shadow: none;
}

.checkbox-group {
  margin-top: 20px;
  display: flex;
  align-items: center;
  column-gap: 15px;
  font-size: 0.95rem;
  color: #fff;
  animation: fadeIn 1s ease-in-out;
}

.checkbox-group input {
  accent-color: #6c5ce7;
  cursor: pointer;
}

.checkbox-group label a {
  color: #7C3AED;
  text-decoration: none;
}

.checkbox-group label a:hover {
  text-decoration: underline;
}

/* Responsive layout */
@media screen and (max-width: 600px) {
  .form .input-box input,
  .form textarea,
  .form button {
    font-size: 1rem;
  }

  .form textarea {
    height: 100px;
  }
}

    </style>
</head>

<body>
    <div class="container">
        <header>Company Registration</header>

        <!-- Message d'erreur -->
        <?php if (!empty($error_message)): ?>
            <p class="error-message">
                <?php echo $error_message; ?>
            </p>
        <?php endif; ?>

        <!-- Message de succès -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <p class="success-message">
                <?php echo $_SESSION['success_message'];
                unset($_SESSION['success_message']); ?>
            </p>
        <?php endif; ?>

        <form action="inscription_entreprise.php" method="post" class="form">
            <div class="input-box">
                <input type="text" name="nom_societe" value="<?php echo htmlspecialchars($nom_societe); ?>" required
                    placeholder="Enter your Company Name" />
                <label>Company Name</label>
            </div>

            <div class="input-box">
                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required
                    placeholder="Enter your Email" />
                <label>Email</label>
            </div>

            <div class="input-box">
                <input type="text" name="adresse" value="<?php echo htmlspecialchars($adresse); ?>" required
                    placeholder="Enter your Address" />
                <label>Address</label>
            </div>

            <div class="input-box">
                <input type="text" name="pays" value="<?php echo htmlspecialchars($pays); ?>" required
                    placeholder="Enter your Country" />
                <label>Country</label>
            </div>

            <div class="input-box">
                <input type="text" name="ville" value="<?php echo htmlspecialchars($ville); ?>" required
                    placeholder="Enter your City" />
                <label>City</label>
            </div>

            <div class="input-box">
                <input type="text" name="code_postal" value="<?php echo htmlspecialchars($code_postal); ?>" required
                    placeholder="Enter Postal Code" maxlength="5" />
                <label>Postal Code</label>
            </div>

            <div class="input-box">
                <input type="date" name="date_fondation" value="<?php echo htmlspecialchars($date_fondation); ?>" />
                <label>Foundation Date</label>
            </div>

            <div class="input-box">
                <input type="tel" name="fax" value="<?php echo htmlspecialchars($fax); ?>"
                    placeholder="Enter your Fax" />
                <label>Fax</label>
            </div>

            <div class="input-box">
                <input type="tel" name="telephone" value="<?php echo htmlspecialchars($telephone); ?>" required
                    placeholder="Enter your Phone Number" />
                <label>Phone Number</label>
            </div>

            <div class="input-box">
                <input type="number" id="nombre_departement" name="nombre_departement" min="1"
                    value="<?php echo htmlspecialchars($nombre_departement); ?>" required
                    oninput="genererDepartements();" placeholder="Enter Number of Departments" />
                <label>Number of Departments</label>


                <div id="departements_container"></div>
            </div>

            <div class="input-box">
                <input type="number" name="nb_employes" value="<?php echo htmlspecialchars($nb_employes); ?>" required
                    placeholder="Enter Number of Employees" />
                <label>Number of Employees</label>
            </div>

            <div class="input-box">
                <input type="password" id="mot_de_passe" name="mot_de_passe" required placeholder="Enter Password" />
                <label>Password</label>
            </div>

            <div class="input-box">
                <input type="password" id="confirmer_mot_de_passe" name="confirmer_mot_de_passe" required
                    placeholder="Confirm Password" />
                <label>Confirm Password</label>
            </div>

            <div class="input-box">
                <input type="file" id="logo" name="logo" accept="image/*" required />
                <label for="logo">Company Logo</label>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="captcha" name="captcha" required />
                <label for="captcha">I am not a robot</label>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="accept_terms" required />
                <label>I accept the <a href="termes.html" target="_blank">terms and conditions</a></label>
            </div>

            <button type="submit">Register</button>
        </form>
    </div>

    <script>
        function genererDepartements() {
            var nbDepartements = document.getElementById("nombre_departement").value;
            var container = document.getElementById("departements_container");
            container.innerHTML = ""; // Vider l'ancien contenu

            for (var i = 1; i <= nbDepartements; i++) {
                var input = document.createElement("input");
                input.type = "text";
                input.name = "departement_" + i;
                input.placeholder = "Department Name " + i;
                input.required = true;
                container.appendChild(input);
                container.appendChild(document.createElement("br"));
            }
        }

        function validerMotDePasse() {
            var mdp = document.getElementById("mot_de_passe").value;
            var confirmMdp = document.getElementById("confirmer_mot_de_passe").value;
            var regex = /^(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$/;

            if (!regex.test(mdp)) {
                alert("Password must contain at least 8 characters, one uppercase letter, one lowercase letter, and one digit.");
                return false;
            }

            if (mdp !== confirmMdp) {
                alert("Passwords do not match.");
                return false;
            }

            return true;
        }
    </script>
</body>

</html>