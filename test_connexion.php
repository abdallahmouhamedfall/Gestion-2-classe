<?php

require_once __DIR__ . "/../config/config.php";

if ($conn) {
    echo "<h2 style='color:green'>Connexion à la base de données réussie ✅</h2>";
} else {
    echo "<h2 style='color:red'>Erreur de connexion ❌</h2>";
}