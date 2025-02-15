<?php
session_start();
require 'config.php';

$message = ''; // Variable pour stocker le message à afficher

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $matricule = $_POST['matricule'];
    $date_today = date('Y-m-d');

    // Check if the matricule is valid 
    if (!preg_match("/^\d+$/", $matricule)) {
        $message = "Invalid Employee ID. Please enter a valid Employee ID.";
    } else {
        // Check if the employee exists
        $stmt = $conn->prepare("SELECT id_employe FROM employes WHERE matricule = ?");
        $stmt->execute([$matricule]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($employee) {
            $id_employee = $employee['id_employe'];

            // Check if they have already logged their attendance today
            $stmt = $conn->prepare("SELECT id FROM presences WHERE id_employe = ? AND date = ?");
            $stmt->execute([$id_employee, $date_today]);

            if (!$stmt->fetch()) {
                // Insert attendance
                $stmt = $conn->prepare("INSERT INTO presences (id_employe, date, statut) VALUES (?, ?, 'present')");
                if ($stmt->execute([$id_employee, $date_today])) {
                    $message = "Attendance successfully recorded.";
                } else {
                    $message = "An error occurred while recording attendance.";
                }
            } else {
                $message = "Attendance has already been recorded for today.";
            }
        } else {
            $message = "❌ Employee ID not found."; // Message if the employee ID is not found
        }
    }
}

// Display the message under the form
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Attendance Validation</title>
    <style>
         /* Reset all elements to remove default margin/padding */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* Body Styling */
body {
    font-family: 'Poppins', sans-serif;
    background: #0F172A;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    color: #fff;
    padding: 20px;
    overflow: hidden;
}

/* Main Container */
.container {
    background: linear-gradient(135deg, #1E293B, #111827);
    padding: 40px 60px;
    border-radius: 12px;
    box-shadow: 0 15px 40px rgba(124, 58, 237, 0.3);
    text-align: center;
    width: 100%;
    max-width: 450px;
    transform: scale(0.9);
    animation: fadeIn 0.5s ease-out forwards;
}

@keyframes fadeIn {
    from {
        transform: scale(0.8);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

/* Header */
h1 {
    font-size: 34px;
    color: #A855F7;
    margin-bottom: 15px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
    animation: slideDown 0.7s ease-out;
}

@keyframes slideDown {
    from {
        transform: translateY(-20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

p {
    color: #E5E7EB;
    font-size: 14px;
    opacity: 0.8;
}

/* Input Field */
input[type="text"] {
    width: 100%;
    padding: 14px;
    border-radius: 8px;
    font-size: 18px;
    border: 2px solid transparent;
    color: #333;
    transition: all 0.3s ease-in-out;
    margin-bottom: 20px;
    outline: none;
    background: #F9FAFB;
    box-shadow: 0 2px 5px rgba(255, 255, 255, 0.1);
}

input[type="text"]:focus {
    border-color: #A855F7;
    box-shadow: 0 0 10px rgba(168, 85, 247, 0.5);
}

/* Button */
button {
    width: 100%;
    padding: 14px;
    background: linear-gradient(90deg, #7C3AED, #5B21B6);
    border: none;
    border-radius: 8px;
    font-size: 18px;
    color: white;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 5px 15px rgba(124, 58, 237, 0.3);
}

button:hover {
    background: #300101;
    transform: scale(1.05);
    box-shadow: 0 8px 20px rgba(124, 58, 237, 0.4);
}

button:focus {
    outline: none;
    box-shadow: 0 0 12px rgba(168, 85, 247, 0.8);
}

/* Mobile Responsiveness */
@media (max-width: 480px) {
    .container {
        padding: 25px;
    }
    h1 {
        font-size: 26px;
    }
    input[type="text"], button {
        font-size: 16px;
    }
}

    </style>
</head>

<body>
    <div class="container">
        <h1>Employee Attendance System</h1>
        <p>Please enter your Employee ID to log your attendance.</p>
        <br />
        <form method="POST" action="">
            <input type="text" name="matricule" placeholder="Enter Employee ID" required />
            <button type="submit">Validate</button>
        </form>
        <br>

        <!-- Display messages -->
        <?php if (!empty($message)): ?>
            <p style="color: <?php echo strpos($message, '❌') === false ? 'green' : 'red'; ?>;"><?php echo $message; ?></p>
        <?php endif; ?>
    </div>
</body>

</html>