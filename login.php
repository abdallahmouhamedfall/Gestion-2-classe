<?php
session_start();

require_once(__DIR__ . "/../config/config.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    $sql = "SELECT * FROM utilisateurs WHERE email = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows == 1) {

        $user = $result->fetch_assoc();

        if (password_verify($password, $user["mot_de_passe"])) {

            $_SESSION["admin"] = true;
            $_SESSION["id_utilisateur"] = $user["id_utilisateur"];
            $_SESSION["nom"] = $user["nom"];
            $_SESSION["prenom"] = $user["prenom"];
            $_SESSION["role"] = $user["role"];

            header("Location: dashboard.php");
            exit();

        } else {

            $erreur = "Mot de passe incorrect.";

        }

    } else {

        $erreur = "Utilisateur introuvable.";

    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduClass - Connexion</title>
    <link rel="stylesheet" href="login.css">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">

        <div class="logo-head">
            <div class="logo-e">E</div>
            <div>
                <div class="logo-name">EduClass</div>
                <div class="logo-sub">GESTION ACADÉMIQUE</div>
            </div>
        </div>

        <div class="title">Bienvenue</div>
        <div class="subtitle">Connectez-vous à votre espace de gestion</div>

        <div class="role-box">
            <span class="role-icon">🧑‍💼</span>
            <div class="role-text">Administrateur</div>
        </div>

        <?php
        if (isset($erreur)) {
            echo "<p style='color:red;text-align:center;'>$erreur</p>";
        }
        ?>

        <form action="" method="POST">

    <div class="field-label">IDENTIFIANT</div>
    <input
    type="email"
    name="email"
    class="field-input"
    required
>

    <div class="field-label">MOT DE PASSE</div>
    <input
    type="password"
    name="password"
    class="field-input"
    placeholder="••••"
    required
>

    <button type="submit" class="btn-login">
        Se connecter →
    </button>

</form>

        <div class="forgot">
            Mot de passe oublié ? <a href="#">Réinitialiser</a>
        </div>

    </div>
</div>

</body>
</html>
