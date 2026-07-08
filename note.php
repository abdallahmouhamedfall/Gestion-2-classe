<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

require_once(__DIR__ . "/../config/config.php");

// --- Ajout d'une note ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_etudiant = (int)($_POST['id_etudiant'] ?? 0);
    $id_matiere = (int)($_POST['id_matiere'] ?? 0);
    $libelleEval = trim($_POST['evaluation'] ?? '');
    $note = (float)($_POST['note'] ?? -1);

    if ($id_etudiant && $id_matiere && $libelleEval !== '' && $note >= 0 && $note <= 20) {
        // Récupère l'évaluation existante ou en crée une nouvelle
        $stmt = $conn->prepare("SELECT id_evaluation FROM evaluations WHERE libelle = ?");
        $stmt->bind_param("s", $libelleEval);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        if ($r) {
            $id_evaluation = $r['id_evaluation'];
        } else {
            $stmt = $conn->prepare("INSERT INTO evaluations (libelle) VALUES (?)");
            $stmt->bind_param("s", $libelleEval);
            $stmt->execute();
            $id_evaluation = $conn->insert_id;
        }

        $stmt = $conn->prepare("INSERT INTO notes (id_etudiant, id_matiere, id_evaluation, note) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $id_etudiant, $id_matiere, $id_evaluation, $note);
        $stmt->execute();
    }
    header("Location: note.php");
    exit();
}

// --- Listes pour le formulaire ---
$etudiantsList = $conn->query("SELECT id_etudiant, nom, prenom FROM etudiants ORDER BY nom, prenom")->fetch_all(MYSQLI_ASSOC);
$matieresList = $conn->query("SELECT id_matiere, nom_matiere, heure_totale FROM matieres ORDER BY nom_matiere")->fetch_all(MYSQLI_ASSOC);

// --- Matrice des moyennes par étudiant / matière ---
$matrix = [];
$resMatrix = $conn->query("SELECT id_etudiant, id_matiere, AVG(note) AS moy FROM notes GROUP BY id_etudiant, id_matiere");
while ($r = $resMatrix->fetch_assoc()) {
    $matrix[$r['id_etudiant']][$r['id_matiere']] = round((float)$r['moy'], 1);
}

// --- Moyenne générale par étudiant, pondérée par heure_totale de chaque matière ---
$moyennes = [];
foreach ($matrix as $idEtu => $matieresNotes) {
    $sommePonderee = 0;
    $sommeCoef = 0;
    foreach ($matieresNotes as $idMat => $avg) {
        $heureTotale = 0;
        foreach ($matieresList as $m) {
            if ($m['id_matiere'] == $idMat) { $heureTotale = (int)$m['heure_totale']; break; }
        }
        $sommePonderee += $avg * $heureTotale;
        $sommeCoef += $heureTotale;
    }
    if ($sommeCoef > 0) {
        $moyennes[$idEtu] = round($sommePonderee / $sommeCoef, 1);
    }
}

function noteClass($val) {
    if ($val >= 16) return 'excellent';
    if ($val >= 14) return 'good';
    if ($val >= 12) return 'average';
    if ($val >= 10) return 'neutral';
    return 'poor';
}

// --- Construction des lignes du tableau ---
$studentsData = [];
foreach ($etudiantsList as $e) {
    $id = $e['id_etudiant'];
    $row = ["name" => $e['prenom'] . ' ' . $e['nom'], "notes" => [], "hasNotes" => isset($moyennes[$id])];
    foreach ($matieresList as $m) {
        $val = $matrix[$id][$m['id_matiere']] ?? null;
        $row["notes"][] = ["val" => $val !== null ? $val : "—", "class" => $val !== null ? noteClass($val) : 'neutral'];
    }
    $row["moyenne"] = $moyennes[$id] ?? "—";
    $row["moyenneClass"] = isset($moyennes[$id]) ? noteClass($moyennes[$id]) : 'neutral';
    $studentsData[] = $row;
}

// --- Distribution des moyennes générales ---
$distribution = ["excellent" => 0, "good" => 0, "average" => 0, "neutral" => 0, "poor" => 0];
$totalAvecNote = 0;
foreach ($moyennes as $moy) {
    $distribution[noteClass($moy)]++;
    $totalAvecNote++;
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notes</title>
    <link rel="stylesheet" href="note.css">
    <style>
      .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); align-items:center; justify-content:center; z-index:200; }
      .modal-box { background:var(--bg-card); border:1px solid var(--border-color); border-radius:16px; padding:28px; width:100%; max-width:400px; }
      .modal-box h3 { font-size:18px; margin-bottom:20px; }
      .modal-box label { display:block; font-size:12px; color:var(--text-secondary); margin-bottom:6px; margin-top:14px; }
      .modal-box input, .modal-box select { width:100%; padding:10px 12px; border-radius:8px; background:rgba(255,255,255,.05); border:1px solid var(--border-color); color:var(--text-primary); outline:none; font-size:13.5px; }
      .modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:24px; }
      .btn-cancel { background:transparent; border:1px solid var(--border-color); color:var(--text-secondary); padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; }
      .btn-add-note { background:var(--color-excellent); color:#0b131f; border:none; padding:10px 18px; border-radius:8px; font-weight:600; font-size:13px; cursor:pointer; }
    </style>
</head>

<body>
    <aside class="sidebar">
        <div class="logo">
            <span class="logo-icon">E</span>
            <div>
                <h2>EduClass</h2>
                <p>Gestion Academique</p>
            </div>
        </div>

        <nav>
            <h4>Principal</h4>
            <a href="dashboard.php">&#128202; Tableau de bord</a>

            <h4>Academique</h4>
            <a href="etudiant.php">&#127891; Etudiants <span><?= count($etudiantsList) ?></span></a>
            <a href="enseignant.php">&#128104;&#8205;&#127979; Enseignants</a>
            <a href="seance.php">&#128197; Seances</a>

            <h4>Gestion</h4>
            <a href="note.php" class="active">&#128221; Notes</a>
            <a href="evaluation.php">&#128203; Evaluations</a>
            <a href="planning.php">&#128467;&#65039; Planning</a>
            <a href="abscence.php">&#9888;&#65039; Absences <span>5</span></a>
        </nav>

        <button class="logout-btn" type="button">&#128682; Deconnexion</button>
    </aside>

    <main class="main">
        <header class="page-header">
            <p class="date">Mercredi 10 juin 2026</p>
            <div class="header-actions">
                <button class="icon-button" aria-label="Notifications">&#128276;</button>
                <div class="avatar">AD</div>
            </div>
        </header>

        <section class="hero">
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <div>
                    <h1>Notes</h1>
                    <p>Relevés de notes</p>
                </div>
                <button class="btn-add-note" onclick="document.getElementById('noteModal').style.display='flex'">+ Ajouter une note</button>
            </div>
        </section>

        <section class="content-grid">
            <article class="notes-card">
                <div class="card-header">
                    <h3>&#128202; Notes par etudiant</h3>
                    <div class="search-bar">
                        <span class="search-icon" aria-hidden="true">&#128269;</span>
                        <input type="text" placeholder="Filtrer &eacute;tudiants..." class="search-input">
                    </div>
                </div>

                <div class="table-wrapper">
                    <table class="notes-table" id="notesTable">
                        <thead>
                            <tr>
                                <th>Etudiants</th>
                                <?php foreach ($matieresList as $m): ?>
                                <th><?= htmlspecialchars($m['nom_matiere']) ?></th>
                                <?php endforeach; ?>
                                <th>Moyenne</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($studentsData)): ?>
                            <tr><td colspan="<?= count($matieresList) + 2 ?>" style="color:var(--text-secondary);">Aucun étudiant enregistré.</td></tr>
                            <?php else: foreach ($studentsData as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['name']) ?></td>
                                <?php foreach ($row['notes'] as $n): ?>
                                <td><span class="note-value <?= $n['class'] ?>"><?= $n['val'] ?></span></td>
                                <?php endforeach; ?>
                                <td><span class="note-value <?= $row['moyenneClass'] ?>"><?= $row['moyenne'] ?></span></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="distribution-card">
                <div class="card-header">
                    <h3>Distribution des moyennes generales</h3>
                </div>

                <div class="distribution-content">
                    <?php
                    $labels = [
                        "excellent" => "Tres bien (&ge;16)",
                        "good" => "Bien (14&ndash;16)",
                        "average" => "Assez bien (12&ndash;14)",
                        "neutral" => "Passable (10&ndash;12)",
                        "poor" => "Insuffisant (&lt;10)"
                    ];
                    ?>
                    <div class="distribution-bars">
                        <?php foreach ($labels as $key => $label): $count = $distribution[$key]; $pct = $totalAvecNote > 0 ? round(($count / $totalAvecNote) * 100) : 0; ?>
                        <div class="bar-row">
                            <div class="bar-meta">
                                <span><?= $label ?></span>
                                <span><?= $count ?> élèves</span>
                            </div>
                            <div class="bar-track"><span class="bar-fill <?= $key ?>" style="width: <?= $pct ?>%;"></span></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="distribution-legend-dots">
                        <?php foreach ($labels as $key => $label): ?>
                        <div class="legend-dot-item">
                            <span class="dot <?= $key ?>"></span>
                            <span class="dot-label"><?= $label ?></span>
                            <span class="dot-count"><?= $distribution[$key] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
        </section>

        <div id="noteModal" class="modal-overlay">
          <div class="modal-box">
            <h3>Ajouter une note</h3>
            <form method="POST" action="note.php">
              <label>Étudiant</label>
              <select name="id_etudiant" required>
                <?php foreach ($etudiantsList as $e): ?>
                <option value="<?= $e['id_etudiant'] ?>"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></option>
                <?php endforeach; ?>
              </select>
              <label>Matière</label>
              <select name="id_matiere" required>
                <?php foreach ($matieresList as $m): ?>
                <option value="<?= $m['id_matiere'] ?>"><?= htmlspecialchars($m['nom_matiere']) ?></option>
                <?php endforeach; ?>
              </select>
              <label>Évaluation</label>
              <input type="text" name="evaluation" placeholder="Ex: DS #1, Contrôle continu..." required>
              <label>Note (/20)</label>
              <input type="number" name="note" min="0" max="20" step="0.25" required>
              <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('noteModal').style.display='none'">Annuler</button>
                <button type="submit" class="btn-add-note">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
    </main>
    <script>
      document.querySelector('.search-input').addEventListener('input', function(e) {
        const f = e.target.value.toLowerCase();
        document.querySelectorAll('#notesTable tbody tr').forEach(tr => {
          const name = tr.children[0]?.innerText.toLowerCase() || '';
          tr.style.display = name.includes(f) ? '' : 'none';
        });
      });
    </script>
</body>

</html>
