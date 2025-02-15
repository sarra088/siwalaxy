<?php
// Démarrer la session
session_start();

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['matricule'])) {
    header('Location: login_employe.php');
    exit();
}

// Inclure le fichier de configuration
require 'config.php';

// Récupérer la matricule depuis la session
$matricule = $_SESSION['matricule'];

try {
    // Récupérer l'ID de l'employé à partir du matricule
    $stmt = $conn->prepare("SELECT id_employe FROM employes WHERE matricule = :matricule");
    $stmt->execute(['matricule' => $matricule]);
    $employe = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$employe) {
        throw new Exception("Employé introuvable !");
    }

    $id_employe = $employe['id_employe']; // Stocker l'ID employé

    // Récupérer le nombre de notifications non lues
    $stmt = $conn->prepare("SELECT COUNT(*) AS notif_count FROM notifications WHERE matricule = :matricule AND vu = 0");
    $stmt->execute(['matricule' => $matricule]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notif_count = $result['notif_count'] ?? 0;
    // Récupérer le dernier message
    $lastMessage = $conn->query("
    SELECT m.*, e.nom, e.prenom 
    FROM messages m 
    LEFT JOIN employes e ON m.id_employe = e.id_employe 
    WHERE m.id_entreprise = (SELECT id_entreprise FROM employes WHERE matricule = '$matricule') 
    ORDER BY m.date_envoi DESC 
    LIMIT 1
")->fetch(PDO::FETCH_ASSOC);

    // Déterminer si le dernier message est de l'entreprise ou de l'employé
    if (!$lastMessage) {
        // Si aucun message n'est trouvé, on met des valeurs par défaut
        $lastMessageFromCompany = false;
        $lastMessageFromEmployee = false;
    } else {
        $lastMessageFromCompany = $lastMessage['id_employe'] === null; // Vérifie si c'est un message de l'entreprise

    }
    // Compter les messages non lus
    $stmt = $conn->prepare("SELECT COUNT(*) AS unread_count FROM messages WHERE id_entreprise = (SELECT id_entreprise FROM employes WHERE matricule = :matricule) AND lu = 0");
    $stmt->execute(['matricule' => $matricule]);
    $unread_result = $stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = $unread_result['unread_count'] ?? 0;

} catch (PDOException $e) {
    echo "Erreur : " . $e->getMessage();
    exit();
} catch (Exception $e) {
    echo "Erreur : " . $e->getMessage();
    exit();
}
// Récupérer le nom et le prénom de l'employé
$stmt = $conn->prepare("SELECT nom, prenom FROM employes WHERE matricule = :matricule");
$stmt->execute(['matricule' => $matricule]);
$employeDetails = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$employeDetails) {
    throw new Exception("Détails de l'employé introuvables !");
}

$nom = $employeDetails['nom'];
$prenom = $employeDetails['prenom'];
// Récupérer les messages
$messages = $conn->query("
    SELECT m.*, e.nom, e.prenom 
    FROM messages m 
    LEFT JOIN employes e ON m.id_employe = e.id_employe 
    WHERE m.id_entreprise = (SELECT id_entreprise FROM employes WHERE matricule = '$matricule') 
    ORDER BY m.date_envoi ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Gérer l'envoi de message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    $message = $_POST['message'];
    $id_entreprise = (int) $conn->query("SELECT id_entreprise FROM employes WHERE matricule = '$matricule'")->fetchColumn(); // ID de l'entreprise

    try {
        $stmt = $conn->prepare("INSERT INTO messages (id_employe, id_entreprise, message, lu) VALUES (:id_employe, :id_entreprise, :message, 0)");
        $stmt->execute(['id_employe' => $id_employe, 'id_entreprise' => $id_entreprise, 'message' => $message]);

        // Récupérer le message inséré pour l'afficher
        $newMessage = [
            'nom' => 'Vous',
            // Nom de l'employé
            'prenom' => '',
            // Prénom de l'employé
            'message' => $message,
            'date_envoi' => date('Y-m-d H:i:s'),
            // Date actuelle
            'id_employe' => $id_employe
        ];

        // Retourner le message sous forme de HTML
        echo "<div class='message-employe'><strong>{$newMessage['nom']}:</strong> {$newMessage['message']} <span style='color: gray;'>(" . date('H:i', strtotime($newMessage['date_envoi'])) . ")</span></div>";
        exit(); // Terminer le script après l'envoi
    } catch (PDOException $e) {
        echo "Erreur lors de l'envoi du message : " . $e->getMessage();
        exit();
    }
}
// Récupérer l'ID de l'entreprise à partir de la session ou d'une autre source
$stmt = $conn->prepare("SELECT id_entreprise FROM employes WHERE matricule = :matricule");
$stmt->execute(['matricule' => $matricule]); // Assurez-vous que $matricule est défini
$employe = $stmt->fetch(PDO::FETCH_ASSOC);

if ($employe) {
    $id_entreprise = $employe['id_entreprise']; // Définir la variable
} else {
    echo "Employé introuvable.";
    exit();
}
// Récupérer les informations de l'entreprise
$stmt = $conn->prepare("SELECT * FROM entreprises WHERE id_entreprise = :id_entreprise");
$stmt->execute(['id_entreprise' => $id_entreprise]); // Assurez-vous que $id_entreprise est défini
$entreprise = $stmt->fetch(PDO::FETCH_ASSOC);


if ($entreprise) {
    $nom_societe = $entreprise['nom_societe'];
    $date_fondation = $entreprise['date_fondation']; // Récupérer la date de fondation
    $nb_employes = $entreprise['nb_employes'];
    $nombre_departement = $entreprise['nombre_departement'];
    // Ajoutez d'autres champs si nécessaire
} else {
    echo "Entreprise introuvable.";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Siwalaxy</title>
    <link rel="stylesheet" href="emp.css">
    <script src="script.js" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body>
    <div class="dashboard">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="logo">
                <h2>Do It</h2>
            </div>
            <ul class="menu">
                <li class="active"><i class="fas fa-chart-pie" data-target="dashboard"></i> Dashboard</li>
                <li><i class="fas fa-folder" data-target="project"></i> Projects</li>
                <li><i class="fas fa-tasks" data-target="tasks"></i> Tasks</li>

            </ul>

            <a href="login_employe.php" class="logout"><i class="fas fa-sign-out-alt"></i> Log Out</a>
        </aside>
        <!--content section-->

        <section class="projects-section hidden" id="project">
            <div class="project-cards">
                <!-- Project Card 1 -->
                <div class="project-card">
                    <div class="project-header">
                        <h3>Website Redesign</h3>
                        <span class="deadline">Due: 15 Feb 2025</span>
                    </div>
                    <div class="project-body">
                        <p>Redesign company homepage with modern UI/UX principles</p>
                        <div class="progress-status">
                            <div class="status-badge in-progress">In Progress</div>
                            <div class="time-remaining">3 days remaining</div>
                        </div>
                    </div>
                    <div class="project-footer">
                        <div class="file-upload-container">
                            <input type="file" id="file-upload-1" class="file-input" hidden>
                            <label for="file-upload-1" class="upload-btn">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Upload File
                            </label>
                            <span class="file-name"></span>
                        </div>

                    </div>
                </div>

                <!-- Add more project cards as needed -->
            </div>
        </section>

        <section class="tasks-section hidden" id="tasks">
            <h2 class="section-title">Company Tasks</h2>
            <div class="tasks-container">
                <!-- Task List 1 -->
                <div class="task-list">
                    <div class="list-header">
                        <h3>Tasks</h3>
                        <button class="add-task-btn">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                    </div>

                    <div class="task-items">
                        <!-- Task Item 1 -->
                        <div class="task-item">
                            <div class="task-content">
                                <input type="checkbox" id="task1" class="task-check">
                                <label for="task1" class="task-text">
                                    <span>Create social media campaign for Q4</span>
                                    <div class="task-meta">
                                        <span class="task-due">📅 25 Nov 2024</span>
                                        <span class="priority-indicator priority-high">⏱ High</span>
                                    </div>
                                    <p class="task-desc">Develop cross-platform campaign for holiday season</p>
                                </label>
                            </div>
                            <div class="task-meta">
                                <span class="status-badge in-progress">In Progress</span>
                                <i class="fas fa-ellipsis-v task-menu"></i>
                            </div>
                        </div>

                        <!-- Task Item 2 -->
                        <div class="task-item completed">
                            <div class="task-content">
                                <input type="checkbox" id="task2" class="task-check" checked>
                                <label for="task2" class="task-text">
                                    <span>Update website banner</span>
                                    <div class="task-meta">
                                        <span class="task-due">📅 15 Dec 2024</span>
                                        <span class="priority-indicator priority-medium">⏱ Medium</span>
                                    </div>
                                </label>
                            </div>
                            <div class="task-meta">
                                <span class="status-badge completed">Completed</span>
                                <i class="fas fa-ellipsis-v task-menu"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="task-modal">

                </div>
                <div class="overlay"></div>
            </div>


        </section>


        <!-- Notification Icon -->
        <!-- Icône de notification avec badge -->
        <div class="notification-icon" onclick="window.location.href='page_notification_employe.php'">
            <i class="fas fa-bell"></i>
            <?php if ($notif_count > 0): ?>
                <span class="badge">
                    <?= $notif_count ?>
                </span> <!-- Affiche le nombre de notifications non lues -->
            <?php endif; ?>
        </div><br>

        <!-- Bouton pour afficher la messagerie -->
        <button class="toggle-messaging" onclick="toggleMessaging()">
            Ouvrir Messagerie
            <?php if ($unread_count > 0 && $lastMessageFromCompany): ?>
                <span style="color: green; font-weight: bold;">●</span>
                <!-- Point vert uniquement si dernier message de l'entreprise -->
            <?php endif; ?>
        </button>
        \
        <main class="main">

            <!-- Header -->
            <header class="header">
                <div class="welcome">
                    <h1>Hello,
                        <?= htmlspecialchars($prenom . ' ' . $nom) ?>
                    </h1>
                    <p>Get professional planning services</p>
                </div>
                <div class="avatar">
                    <a href="profile.php"><img src="icon.png" alt="Profile Picture"></a>
                    <div class="status-indicator"></div>
                </div>
            </header>
            <!-- Task Overview -->
            <section class="tasks">
                <div class="task-card">
                    <h2><i class="fas fa-tasks"></i> Your Tasks</h2>
                    <ul>
                        <li><i class="fas fa-palette"></i>Design Homepage <span class="date">Today</span></li>
                        <li><i class="fas fa-video"></i>Dribble Tutorial <span class="date">Aug 10</span></li>
                        <li><i class="fas fa-file-signature"></i>Onboarding Design <span class="date">Aug 11</span></li>
                    </ul>
                    <a href="#" class="view-all">View All <i class="fas fa-chevron-right"></i></a>
                </div>

                <div class="task-progress">
                    <h2><i class="fas fa-chart-line"></i> Task Progress</h2>
                    <div class="progress-item">
                        <p>Design Homepage</p>
                        <div class="progress-bar"><span style="width: 70%;"></span></div>
                    </div>
                    <div class="progress-item">
                        <p>Dribble Tutorial</p>
                        <div class="progress-bar"><span style="width: 50%;"></span></div>
                    </div>
                    <div class="progress-item">
                        <p>Onboarding Design</p>
                        <div class="progress-bar"><span style="width: 80%;"></span></div>
                    </div>
                    <a href="#" class="view-all">View All <i class="fas fa-chevron-right"></i></a>
                </div>
            </section>

            <!-- Calendar and Events -->
            <section class="calendar-events">
                <div class="events">
                    <h2><i class="fas fa-calendar-alt"></i> Public Holidays</h2>
                    <ul>
                        <?php
                        $jours_feries = [
                            "01-01" => "New Year's Day",
                            "03-20" => "Independence Day",
                            "04-09" => "Martyrs' Day",
                            "05-01" => "Labor Day",
                            "07-25" => "Republic Day",
                            "08-13" => "Women's Day",
                            "10-15" => "Evacuation Day",
                            "12-17" => "Revolution Day",
                        ];

                        foreach ($jours_feries as $date => $nom) {
                            // Convertir la date au format "dd MMM"
                            $date_obj = DateTime::createFromFormat("d-m", $date);
                            $date_affichee = $date_obj ? $date_obj->format("d M") : $date;
                            ?>
                            <li>
                                <i class="fas fa-flag"></i>
                                <?= $nom ?> <span class="time">
                                    <?= $date_affichee ?>
                                </span>
                            </li>
                        <?php } ?>
                    </ul>
                    <a href="#" class="view-all">View All <i class="fas fa-chevron-right"></i></a>
                </div>
            </section>

            <!-- Quick Actions -->
            <section class="quick-actions">
                <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                <div class="actions-grid">
                    <button class="action-btn" id="leaveBtn">
                        <i class="fas fa-calendar-minus"></i>
                        Request Leave
                    </button>
                    <button class="action-btn" id="reportBtn">
                        <i class="fas fa-file-import"></i>
                        Submit Report
                    </button>
                    <button class="action-btn" id="projectBtn">
                        <i class="fas fa-plus-circle"></i>
                        New Project
                    </button>
                    <button class="action-btn" id="timeBtn">
                        <i class="fas fa-clock"></i>
                        Time Tracking
                    </button>
                </div>
            </section>

            <div class="modal" id="leaveModal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h3>🏖️ Request Leave</h3><br>
                    <form method="POST" action="demander_conge.php">
                        <label for="employeeId" style="color: white;">Employee ID:</label> <br> <br>
                        <input type="text" name="matricule" placeholder="Enter Employee ID" required /><br>
                        <label style="color: white;">Leave Duration:</label><br><br>
                        <input type="date" name="date_debut" required />
                        <input type="date" name="date_fin" required /><br>
                        <button type="submit" class="submit-btn">Submit Request</button>
                    </form>

                </div>
            </div>
            <div class="modal" id="reportModal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h3>📁 Submit Report</h3>
                    <form id="reportForm">
                        <div class="form-group">
                            <label>Report Title:</label>
                            <input type="text" required>
                        </div>
                        <div class="form-group">
                            <label>Upload File:</label>
                            <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" required>
                        </div>
                        <div class="form-group">
                            <label>Comments:</label>
                            <textarea rows="4"></textarea>
                        </div>
                        <button type="submit" class="submit-btn">Upload Report</button>
                    </form>
                </div>
            </div>
            <!-- New Creative Section -->
            <section class="creative-section">
                <div class="metrics-card">
                    <h2><i class="fas fa-chart-pie"></i> Company Information</h2>
                    <div class="metric-grid">
                        <div class="metric-item">
                            <div class="metric-value">
                                <?= htmlspecialchars($nom_societe) ?>
                            </div>
                            <div class="metric-label">Company Name</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">
                                <?= htmlspecialchars(date('Y', strtotime($date_fondation))) ?>
                                <!-- Affiche uniquement l'année -->
                            </div>
                            <div class="metric-label">Foundation Year</div> <!-- Nouveau champ -->
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">
                                <?= htmlspecialchars($nb_employes) ?>
                            </div>
                            <div class="metric-label">Number of Employees</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value">
                                <?= htmlspecialchars($nombre_departement) ?>
                            </div>
                            <div class="metric-label">Number of Departments</div>
                        </div>
                    </div>
                </div>
                <div class="quote-card">
                    <p class="quote-text">"Productivity is never an accident. It is always the result of a commitment to
                        excellence, intelligent planning, and focused effort."</p>
                    <p class="quote-author">- Paul J. Meyer</p>
                </div>
            </section>


            <!-- Icône de message avec badge -->
            <div class="message-icon" onclick="toggleMessaging()">
                <i class="fas fa-comment"></i>
                <?php if ($unread_count > 0): ?>
                    <span class="badge" style="color: red;">
                        <?= $unread_count ?>
                    </span>
                <?php endif; ?>
            </div>

            <!-- Discussion -->
            <div class="section" id="messaging-section" style="display: none;">
                <h2>Discussion</h2>
                <?php if ($unread_count > 0): ?>
                    <?php if ($lastMessageFromCompany): ?>
                        <div class="new-message-alert">Vous avez
                            <?= $unread_count ?> nouveau(x) message(s) de l'entreprise.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                <form id="message-form" method="post" onsubmit="sendMessage(event)">
                    <textarea name="message" placeholder="Écrivez votre message ici..." required></textarea>
                    <button type="submit">Envoyer</button>
                </form>
                <div id="messages-list">
                    <?php
                    foreach ($messages as $msg) {
                        $styleClass = $msg['id_employe'] ? 'message-employe' : 'message-entreprise'; // Classe CSS selon l'expéditeur
                        echo "<div class='$styleClass'><strong>{$msg['nom']} {$msg['prenom']}:</strong> {$msg['message']} <span style='color: gray;'>(" . date('H:i', strtotime($msg['date_envoi'])) . ")</span></div>";
                    }
                    ?>
                </div>
            </div>
            <!-- Footer -->
            <footer class="dashboard-footer">
                <div class="social-links">
                    <a href="https://www.instagram.com/siwa_laxy?utm_source=ig_web_button_share_sheet&igsh=ZDNlZDc0MzIxNw=="
                        class="social-link" data-tooltip="instagram"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-link" data-tooltip="facebook"><i class="fab fa-facebook"></i></a>
                    <a href="mailto:siwalaxy@gmail.com" class="social-link" data-tooltip="Email"><i
                            class="far fa-envelope"></i></a>
                </div>
            </footer>


            <style>
                /* Modal Styles */
                .modal {
                    display: none;
                    position: fixed;
                    z-index: 1000;
                    left: 0;
                    top: 0;
                    width: 100%;
                    height: 100%;
                    background-color: rgba(0, 0, 0, 0.4);
                }

                .modal-content {
                    background: white;
                    margin: 10% auto;
                    padding: 25px;
                    width: 90%;
                    max-width: 500px;
                    border-radius: 12px;
                    position: relative;
                    animation: modalSlide 0.3s ease-out;
                }

                .close {
                    position: absolute;
                    right: 20px;
                    top: 15px;
                    font-size: 28px;
                    cursor: pointer;
                }

                .form-group {
                    margin-bottom: 15px;
                }

                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 500;
                }

                input,
                textarea,
                select {
                    width: 100%;
                    padding: 10px;
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    margin-bottom: 10px;
                }

                .submit-btn {
                    background: #4f46e5;
                    color: white;
                    padding: 12px 25px;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    width: 100%;
                    font-size: 16px;
                    transition: background 0.3s ease;
                }

                .timer-display {
                    font-size: 3em;
                    text-align: center;
                    margin: 20px 0;
                }

                @keyframes modalSlide {
                    from {
                        transform: translateY(-50px);
                        opacity: 0;
                    }

                    to {
                        transform: translateY(0);
                        opacity: 1;
                    }
                }

                .new-message-alert {
                    color: red;
                    font-weight: bold;
                    display:
                        <?= ($unread_count > 0) ? 'block' : 'none' ?>
                    ;
                    /* Affiche le message si des messages non lus */
                    margin-bottom: 10px;
                }

                .section {
                    margin: 20px 0;
                }

                textarea {
                    width: 100%;
                    height: 50px;
                }

                #messages-list {
                    max-height: 300px;
                    overflow-y: auto;
                    border: 1px solid #ccc;
                    padding: 10px;
                    margin-top: 10px;
                }

                #messages-list div {
                    margin-bottom: 10px;
                    padding: 10px;
                    border-radius: 8px;
                    color: white;
                }

                .message-employe {
                    background-color: darkmagenta;
                    /* Bleu ciel */
                }

                .message-entreprise {
                    background-color: violet;
                    /* Bleu marine */
                }

                .toggle-messaging {
                    cursor: pointer;
                    background-color: #007BFF;
                    color: white;
                    padding: 10px;
                    border: none;
                    border-radius: 5px;
                    margin: 10px 0;
                }

                .toggle-messaging:hover {
                    background-color: #0056b3;
                }

                button {
                    background-color: #ff4757;
                    /* Couleur de fond du bouton */
                    color: #ffffff;
                    /* Couleur du texte du bouton */
                    border: none;
                    /* Pas de bordure */
                    border-radius: 4px;
                    /* Coins arrondis */
                    padding: 10px 15px;
                    /* Espacement interne */
                    cursor: pointer;
                    /* Curseur pointer */
                    transition: background-color 0.3s;
                    /* Transition pour l'effet hover */
                }
            </style>

            <!-- Your existing content -->

            <!-- Time Tracking Modal -->
            <div class="modal" id="timeModal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h3>⏱️ Time Tracking</h3>
                    <div class="timer-display">
                        <span id="hours">00</span>:<span id="minutes">00</span>:<span id="seconds">00</span>
                    </div>
                    <button class="submit-btn" id="startTimer">Start Working Day</button>
                </div>
            </div>

            <!-- New Project Modal -->
            <div class="modal" id="projectModal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h3>🛠️ New Project</h3>
                    <form id="projectForm">
                        <div class="form-group">
                            <label>Project Name:</label>
                            <input type="text" required>
                        </div>
                        <div class="form-group">
                            <label>Project Description:</label>
                            <textarea rows="4" required></textarea>
                        </div>
                        <button type="submit" class="submit-btn">Create Project</button>
                    </form>
                </div>
            </div>

            <!-- JavaScript -->
            <script>
                // Modal Handling
                const modals = {
                    leave: document.getElementById('leaveModal'),
                    report: document.getElementById('reportModal'),
                    project: document.getElementById('projectModal'),
                    time: document.getElementById('timeModal')
                };

                // Timer Variables
                let timerInterval;
                let secondsWorked = 0;

                // Open Modals
                document.getElementById('leaveBtn').onclick = () => showModal('leave');
                document.getElementById('reportBtn').onclick = () => showModal('report');
                document.getElementById('projectBtn').onclick = () => showModal('project');
                document.getElementById('timeBtn').onclick = () => showModal('time');

                // Close Modals
                document.querySelectorAll('.close').forEach(closeBtn => {
                    closeBtn.onclick = () => closeAllModals();
                });

                // Close when clicking outside
                window.onclick = (event) => {
                    if (event.target.classList.contains('modal')) closeAllModals();
                };

                // Form Submissions
                document.getElementById('leaveForm').onsubmit = (e) => {
                    e.preventDefault();
                    const formData = {
                        name: e.target.querySelector('[type="text"]').value,
                        days: e.target.querySelector('[type="number"]').value,
                        reason: e.target.querySelector('textarea').value
                    };
                    alert('Leave request submitted!\n' + JSON.stringify(formData, null, 2));
                    closeAllModals();
                };

                document.getElementById('reportForm').onsubmit = (e) => {
                    e.preventDefault();
                    const file = e.target.querySelector('[type="file"]').files[0];
                    alert(`Report submitted!\nFile: ${file.name}\nSize: ${(file.size / 1024).toFixed(2)}KB`);
                    closeAllModals();
                };

                document.getElementById('projectForm').onsubmit = (e) => {
                    e.preventDefault();
                    const formData = {
                        name: e.target.querySelector('[type="text"]').value,
                        description: e.target.querySelector('textarea').value
                    };
                    alert('New project created!\n' + JSON.stringify(formData, null, 2));
                    closeAllModals();
                };

                // Timer Functions
                document.getElementById('startTimer').onclick = () => {
                    if (!timerInterval) {
                        timerInterval = setInterval(updateTimer, 1000);
                        this.textContent = 'Stop Timer';
                    } else {
                        clearInterval(timerInterval);
                        timerInterval = null;
                        this.textContent = 'Start Working Day';
                    }
                };

                function updateTimer() {
                    secondsWorked++;
                    const hours = Math.floor(secondsWorked / 3600).toString().padStart(2, '0');
                    const minutes = Math.floor((secondsWorked % 3600) / 60).toString().padStart(2, '0');
                    const seconds = (secondsWorked % 60).toString().padStart(2, '0');
                    document.getElementById('hours').textContent = hours;
                    document.getElementById('minutes').textContent = minutes;
                    document.getElementById('seconds').textContent = seconds;
                }

                function showModal(type) {
                    closeAllModals();
                    modals[type].style.display = 'block';
                }

                function closeAllModals() {
                    Object.values(modals).forEach(modal => modal.style.display = 'none');
                }
            </script>

        </main>

    </div>

    <!--------------------------------------------------->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const menuItems = document.querySelectorAll('.menu li');

            // Sidebar menu hover animation
            menuItems.forEach(item => {
                item.addEventListener('click', () => {
                    document.querySelector('.menu li.active').classList.remove('active');
                    item.classList.add('active');
                });
            });
        });
        document.addEventListener("DOMContentLoaded", () => {
            const menuItems = document.querySelectorAll('.menu li');

            menuItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    // Ripple effect
                    const ripple = document.createElement('div');
                    ripple.style.cssText = `
            position: absolute;
            width: 20px;
            height: 20px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            animation: ripple 0.6s linear;
          `;

                    const rect = item.getBoundingClientRect();
                    ripple.style.left = `${e.clientX - rect.left}px`;
                    ripple.style.top = `${e.clientY - rect.top}px`;

                    item.appendChild(ripple);

                    setTimeout(() => ripple.remove(), 600);

                    // Active state
                    document.querySelector('.menu li.active').classList.remove('active');
                    item.classList.add('active');
                });
            });

            // Animate progress bars
            document.querySelectorAll('.progress-bar span').forEach(bar => {
                bar.style.width = bar.style.width; // Trigger transition
            });
        });
        // Update the JavaScript
        document.addEventListener("DOMContentLoaded", () => {
            // Section switching
            const sections = {
                dashboard: document.querySelector('.main'),
                projects: document.querySelector('.projects-section')
            };

            document.querySelectorAll('.menu li').forEach(item => {
                item.addEventListener('click', function () {
                    const target = this.textContent.toLowerCase().trim();
                    Object.values(sections).forEach(section => section.classList.add('hidden'));
                    sections[target].classList.remove('hidden');

                    // Add animation
                    sections[target].style.animation = 'none';
                    requestAnimationFrame(() => {
                        sections[target].style.animation = 'slideUp 0.5s ease';
                    });
                });
            });

            // File upload handling
            document.querySelectorAll('.file-input').forEach(input => {
                input.addEventListener('change', function (e) {
                    const fileName = this.files[0]?.name || '';
                    const container = this.closest('.file-upload-container');
                    container.querySelector('.file-name').textContent = fileName;

                    // Simulate upload delay
                    setTimeout(() => {
                        container.nextElementSibling.classList.remove('hidden');
                        showConfetti();
                    }, 1500);
                });
            });
        });

        function showConfetti() {
            const confetti = document.createElement('div');
            confetti.className = 'confetti-effect';
            confetti.innerHTML = `
    <div class="confetti"></div>
    <div class="confetti"></div>
    <div class="confetti"></div>
    <div class="confetti"></div>
    <div class="confetti"></div>
  `;
            document.body.appendChild(confetti);

            setTimeout(() => confetti.remove(), 3000);
        }
        document.addEventListener("DOMContentLoaded", () => {
            const sections = {
                dashboard: document.querySelector('.main'),
                project: document.querySelector('.projects-section'),
                tasks: document.querySelector('.tasks-section'), // Add corresponding HTML section
                calendar: document.querySelector('.calendar-section'), // Add corresponding HTML section
                settings: document.querySelector('.settings-section') // Add corresponding HTML section
            };

            document.querySelectorAll('.menu li').forEach(item => {
                item.addEventListener('click', function () {
                    // Get target from icon's data attribute
                    const target = this.querySelector('i').dataset.target;

                    // Hide all sections
                    Object.values(sections).forEach(section => {
                        if (section) section.classList.add('hidden');
                    });

                    // Show target section
                    if (sections[target]) {
                        sections[target].classList.remove('hidden');
                        // Add animation
                        sections[target].style.animation = 'none';
                        requestAnimationFrame(() => {
                            sections[target].style.animation = 'slideUp 0.5s ease';
                        });
                    }

                    // Update active state
                    document.querySelector('.menu li.active').classList.remove('active');
                    this.classList.add('active');
                });
            });


        });
        const s = {
            dashboard: document.querySelector('.main'),
            project: document.querySelector('.projects-section'),
            tasks: document.querySelector('.tasks-section'),
            calendar: document.querySelector('.calendar-section'),
            settings: document.querySelector('.settings-section')
        };
        const target = this.querySelector('i').dataset.target;
        Object.values(sections).forEach(section => {
            if (section) section.classList.add('hidden');
        });
        if (sections[target]) {
            sections[target].classList.remove('hidden');
            // ... animation code ...
        }
        function openTaskModal() {
            document.getElementById('taskModal').classList.add('active');
            document.getElementById('overlay').classList.add('active');
        }

        function closeTaskModal() {
            document.getElementById('taskModal').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
        }

        function toggleTask(checkbox) {
            const taskItem = checkbox.closest('.task-item');
            const taskText = checkbox.nextElementSibling;

            if (checkbox.checked) {
                taskItem.style.opacity = '0.6';
                taskText.style.textDecoration = 'line-through';
                taskItem.style.transform = 'scale(0.98)';
            } else {
                taskItem.style.opacity = '1';
                taskText.style.textDecoration = 'none';
                taskItem.style.transform = 'scale(1)';
            }
        }

        function addTask() {
            const input = document.getElementById('newTask');
            const taskText = input.value.trim();

            if (taskText) {
                const taskList = document.getElementById('taskList');
                const newTask = document.createElement('li');
                newTask.className = 'task-item';
                newTask.innerHTML = `
            <input type="checkbox" class="task-checkbox" onchange="toggleTask(this)">
            <span>${taskText}</span>
        `;
                taskList.appendChild(newTask);
                input.value = '';

                // Trigger animation
                setTimeout(() => {
                    newTask.style.animation = 'taskEntry 0.4s forwards';
                }, 10);
            }
        }

        // Close modal when clicking outside
        document.getElementById('overlay').addEventListener('click', closeTaskModal);

        // Handle Enter key
        document.getElementById('newTask').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') addTask();
        });
        // Update the sections configuration
        const sections = {
            dashboard: document.querySelector('.main'),
            project: document.querySelector('.projects-section'),
            tasks: document.querySelector('.tasks-section'), // Corrected selector
            calendar: document.querySelector('.calendar-section'),
            settings: document.querySelector('.settings-section')
        };

        // Update the click handler
        document.querySelectorAll('.menu li').forEach(item => {
            item.addEventListener('click', function () {
                const target = this.querySelector('i').dataset.target;

                Object.values(sections).forEach(section => {
                    if (section) section.classList.add('hidden');
                });

                if (sections[target]) {
                    sections[target].classList.remove('hidden');
                    sections[target].style.animation = 'none';
                    requestAnimationFrame(() => {
                        sections[target].style.animation = 'slideUp 0.5s ease';
                    });
                }

                document.querySelector('.menu li.active').classList.remove('active');
                this.classList.add('active');
            });
        });
        document.addEventListener("DOMContentLoaded", () => {
            const sections = {
                dashboard: document.querySelector('.main'),
                project: document.querySelector('.projects-section'),
                tasks: document.querySelector('#tasks-section'), // Corrected selector
                calendar: document.querySelector('.calendar-section'),
                settings: document.querySelector('.settings-section')
            };

            document.querySelectorAll('.menu li').forEach(item => {
                item.addEventListener('click', function () {
                    const target = this.querySelector('i').dataset.target;

                    // Hide all sections
                    Object.values(sections).forEach(section => {
                        if (section) section.classList.add('hidden');
                    });

                    // Show target section
                    if (sections[target]) {
                        sections[target].classList.remove('hidden');
                        sections[target].style.animation = 'none';
                        requestAnimationFrame(() => {
                            sections[target].style.animation = 'slideUp 0.5s ease';
                        });
                    }

                    // Update active state
                    document.querySelector('.menu li.active').classList.remove('active');
                    this.classList.add('active');
                });
            });
        });
        // Add task functionality
        document.addEventListener('DOMContentLoaded', () => {
            const addTaskBtns = document.querySelectorAll('.add-task-btn, .floating-add-btn');

            addTaskBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const newTask = createTaskElement('New Task', 'Due: DD MMM YYYY');
                    const taskItems = document.querySelector('.task-items');
                    taskItems.appendChild(newTask);
                });
            });

            // Task checkbox functionality
            document.querySelectorAll('.task-check').forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    const taskItem = this.closest('.task-item');
                    taskItem.classList.toggle('completed', this.checked);
                });
            });
        });

        function createTaskElement(taskText, dueDate) {
            const taskId = `task-${Date.now()}`;

            const taskItem = document.createElement('div');
            taskItem.className = 'task-item';
            taskItem.innerHTML = `
            <div class="task-content">
                <input type="checkbox" id="${taskId}" class="task-check">
                <label for="${taskId}" class="task-text">
                    <span>${taskText}</span>
                    <span class="task-due">${dueDate}</span>
                </label>
            </div>
            <div class="task-meta">
                <span class="status-badge in-progress">In Progress</span>
                <i class="fas fa-ellipsis-v task-menu"></i>
            </div>
        `;

            // Add checkbox functionality to new task
            taskItem.querySelector('.task-check').addEventListener('change', function () {
                this.closest('.task-item').classList.toggle('completed', this.checked);
            });

            return taskItem;
        }
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.querySelector('.task-modal');
            const overlay = document.querySelector('.overlay');
            const openModalBtns = document.querySelectorAll('.add-task-btn, .floating-add-btn');
            const closeModalBtn = document.querySelector('.close-modal');

            // Modal Handling
            const toggleModal = () => {
                modal.classList.toggle('active');
                overlay.classList.toggle('active');
            };

            openModalBtns.forEach(btn => btn.addEventListener('click', toggleModal));
            closeModalBtn.addEventListener('click', toggleModal);
            overlay.addEventListener('click', toggleModal);

            // Task Creation
            document.querySelector('.create-task-btn').addEventListener('click', () => {
                const title = document.querySelector('.task-title').value;
                const desc = document.querySelector('.task-desc').value;
                const date = document.querySelector('.task-date').value;
                const priority = document.querySelector('.task-priority').value;

                if (title) {
                    const newTask = createTaskElement(title, date, priority, desc);
                    document.querySelector('.task-items').appendChild(newTask);
                    toggleModal();
                }
            });
        });

        function createTaskElement(title, dueDate, priority, description) {
            const taskId = `task-${Date.now()}`;
            const priorityColors = {
                low: 'priority-low',
                medium: 'priority-medium',
                high: 'priority-high'
            };

            const taskItem = document.createElement('div');
            taskItem.className = 'task-item';
            taskItem.innerHTML = `
    <div class="task-content">
      <input type="checkbox" id="${taskId}" class="task-check">
      <label for="${taskId}" class="task-text">
        <span>${title}</span>
        <div class="task-meta">
          ${dueDate ? `<span class="task-due">📅 ${dueDate}</span>` : ''}
          ${priority ? `<span class="priority-indicator ${priorityColors[priority]}">⏱ ${priority}</span>` : ''}
        </div>
        ${description ? `<p class="task-desc">${description}</p>` : ''}
      </label>
    </div>
    <div class="task-meta">
      <span class="status-badge in-progress">In Progress</span>
      <i class="fas fa-ellipsis-v task-menu"></i>
    </div>
  `;

            taskItem.querySelector('.task-check').addEventListener('change', function () {
                this.closest('.task-item').classList.toggle('completed', this.checked);
            });

            return taskItem;
        }
        document.querySelector('.contact-btn').addEventListener('mousemove', function (e) {
            const rect = this.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            this.style.setProperty('--x', `${x}px`);
            this.style.setProperty('--y', `${y}px`);
        });
    </script>

    <script>
        function toggleMessaging() {
            const messagingSection = document.getElementById('messaging-section');
            if (messagingSection.style.display === 'none') {
                messagingSection.style.display = 'block';
                markMessagesAsRead(); // Marquer les messages comme lus lorsque la section est ouverte
            } else {
                messagingSection.style.display = 'none';
            }
        }

        function markMessagesAsRead() {
            fetch('mark_messages_as_read.php', { // Créez un fichier PHP pour marquer les messages comme lus
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `matricule=${encodeURIComponent('<?= $matricule ?>')}` // Envoyer le matricule pour identifier l'utilisateur
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Erreur lors de la mise à jour des messages');
                    }
                })
                .catch(error => {
                    console.error(error);
                });
        }

        function sendMessage(event) {
            event.preventDefault(); // Empêche le rechargement de la page

            const formData = new FormData(event.target); // Récupère les données du formulaire

            fetch('pageacceuilemploye.php', { // Envoie le message à la même page
                method: 'POST',
                body: formData
            })
                .then(response => {
                    if (response.ok) {
                        return response.text(); // Récupère la réponse en texte
                    }
                    throw new Error('Erreur lors de l\'envoi du message');
                })
                .then(data => {
                    // Ajoute le nouveau message à la liste des messages
                    const messagesList = document.getElementById('messages-list');
                    messagesList.innerHTML += data; // Ajoute le nouveau message
                    event.target.reset(); // Réinitialise le formulaire
                })
                .catch(error => {
                    alert(error.message);
                });
        }
        let timer;
        let hours = 0, minutes = 0, seconds = 0;
        let running = false; // Vérifie si le chrono est en cours

        document.getElementById("startTimer").addEventListener("click", function () {
            if (!running) { // Démarrer seulement si ce n'est pas déjà en cours
                running = true;
                timer = setInterval(updateTimer, 1000);
                this.textContent = "Stop Working Day"; // Change le texte du bouton
            } else {
                running = false;
                clearInterval(timer);
                this.textContent = "Start Working Day"; // Remet le texte d'origine
            }
        });

        function updateTimer() {
            seconds++;
            if (seconds == 60) {
                seconds = 0;
                minutes++;
                if (minutes == 60) {
                    minutes = 0;
                    hours++;
                }
            }

            // Met à jour l'affichage du chrono
            document.getElementById("hours").textContent = formatTime(hours);
            document.getElementById("minutes").textContent = formatTime(minutes);
            document.getElementById("seconds").textContent = formatTime(seconds);
        }

        // Formate les nombres pour avoir toujours 2 chiffres (ex: 09 au lieu de 9)
        function formatTime(time) {
            return time < 10 ? "0" + time : time;
        }

    </script>
</body>

</html>