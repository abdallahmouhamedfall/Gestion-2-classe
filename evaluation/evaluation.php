<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

require_once(__DIR__ . "/../config/config.php");

// --- Suppression ---
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM evaluations WHERE id_evaluation = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: evaluation.php");
    exit();
}

// --- Ajout / modification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $libelle = trim($_POST['libelle'] ?? '');
    $id_matiere = (int)($_POST['id_matiere'] ?? 0);
    $date_eval = $_POST['date_eval'] ?: null;
    $duree_min = (int)($_POST['duree_min'] ?? 0);
    $type = in_array($_POST['type'] ?? '', ['Ecrit', 'Pratique', 'Oral']) ? $_POST['type'] : 'Ecrit';
    $statut = in_array($_POST['statut'] ?? '', ['A venir', 'En cours', 'Corrigée']) ? $_POST['statut'] : 'A venir';

    if ($libelle !== '' && $id_matiere) {
        if (!empty($_POST['id_evaluation'])) {
            $id = (int)$_POST['id_evaluation'];
            $stmt = $conn->prepare("UPDATE evaluations SET libelle=?, id_matiere=?, date_eval=?, duree_min=?, type=?, statut=? WHERE id_evaluation=?");
            $stmt->bind_param("sisissi", $libelle, $id_matiere, $date_eval, $duree_min, $type, $statut, $id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO evaluations (libelle, id_matiere, date_eval, duree_min, type, statut) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sisiss", $libelle, $id_matiere, $date_eval, $duree_min, $type, $statut);
            $stmt->execute();
        }
    }
    header("Location: evaluation.php");
    exit();
}

$matieresList = $conn->query("SELECT id_matiere, nom_matiere FROM matieres ORDER BY nom_matiere")->fetch_all(MYSQLI_ASSOC);

$sql = "SELECT ev.id_evaluation, ev.libelle, ev.id_matiere, ev.date_eval, ev.duree_min, ev.type, ev.statut, m.nom_matiere
        FROM evaluations ev
        LEFT JOIN matieres m ON ev.id_matiere = m.id_matiere
        ORDER BY (ev.date_eval IS NULL), ev.date_eval ASC";
$result = $conn->query($sql);

$typeClasses = ["Ecrit" => "exam", "Pratique" => "pratique", "Oral" => "oral"];
$statutClasses = ["A venir" => "upcoming", "En cours" => "initiated", "Corrigée" => "completed"];

function formatDuree($min) {
    $min = (int)$min;
    if ($min <= 0) return "—";
    if ($min < 60) return $min . "min";
    $h = intdiv($min, 60);
    $reste = $min % 60;
    return $reste > 0 ? $h . "h" . $reste : $h . "h";
}

$evaluationsData = [];
foreach ($result as $row) {
    $evaluationsData[] = [
        "id" => (int)$row['id_evaluation'],
        "libelle" => $row['libelle'],
        "id_matiere" => (int)$row['id_matiere'],
        "matiere" => $row['nom_matiere'] ?: '—',
        "date_eval" => $row['date_eval'],
        "dateLabel" => $row['date_eval'] ? (new DateTime($row['date_eval']))->format('d/m/Y') : '—',
        "duree_min" => (int)$row['duree_min'],
        "dureeLabel" => formatDuree($row['duree_min']),
        "type" => $row['type'],
        "typeClass" => $typeClasses[$row['type']] ?? 'exam',
        "statut" => $row['statut'],
        "statutClass" => $statutClasses[$row['statut']] ?? 'upcoming'
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>evaluations</title>
    <link rel="stylesheet" href="evaluation.css">
    <style>
      .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); align-items:center; justify-content:center; z-index:200; }
      .modal-box { background:var(--bg-card); border:1px solid rgba(255,255,255,.05); border-radius:16px; padding:28px; width:100%; max-width:420px; }
      .modal-box h3 { font-size:18px; margin-bottom:20px; color:var(--text-primary); }
      .modal-box label { display:block; font-size:12px; color:var(--text-muted); margin-bottom:6px; margin-top:14px; }
      .modal-box input, .modal-box select { width:100%; padding:10px 12px; border-radius:8px; background:var(--bg-input); border:1px solid rgba(255,255,255,.05); color:var(--text-primary); outline:none; font-size:13.5px; }
      .modal-row { display:flex; gap:10px; }
      .modal-row > div { flex:1; }
      .modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:24px; }
      .btn-cancel-eval { background:transparent; border:1px solid rgba(255,255,255,.1); color:var(--text-muted); padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; }
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

            <h4>Acad&eacute;mique</h4>
            <a href="etudiant.php">&#127891; Etudiants</a>
            <a href="enseignant.php">&#128104;&#8205;&#127979; Enseignants</a>
            <a href="seance.php">&#128197; Seances</a>

            <h4>Gestion</h4>
            <a href="note.php">&#128221; Notes</a>
            <a href="evaluation.php" class="active">&#128203; Evaluations <span><?= count($evaluationsData) ?></span></a>
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
            <div class="hero-content">
                <div>
                    <h1>&Eacute;valuations</h1>
                    <p>Calendrier des examens, controles et devoirs</p>
                </div>
                <button class="btn-add-eval" onclick="openAddModal()">+ Nouvelle Evaluation</button>
            </div>
        </section>

        <section class="evaluations-section">
            <div class="toolbar-section">
                <div class="search-bar">
                    <span class="search-icon" aria-hidden="true">&#128269;</span>
                    <input type="text" placeholder="Rechercher une &eacute;valuation (matiere, type, statut)..." class="search-input">
                </div>
                <div class="toolbar-actions">
                    <select class="btn-filter" id="typeFilter"><option value="">Tous types</option><option value="Ecrit">Écrit</option><option value="Pratique">Pratique</option><option value="Oral">Oral</option></select>
                    <select class="btn-filter" id="statutFilter"><option value="">Tous statuts</option><option value="A venir">À venir</option><option value="En cours">En cours</option><option value="Corrigée">Corrigée</option></select>
                    <button class="btn-export" onclick="exportEvalCSV()">Export &#9662;</button>
                </div>
            </div>

            <article class="evaluations-card">
                <div class="table-wrapper">
                    <table class="evaluations-table" id="evalTable">
                        <thead>
                            <tr>
                                <th>Evaluation</th>
                                <th>Matière</th>
                                <th>Date</th>
                                <th>Durée</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($evaluationsData)): ?>
                            <tr><td colspan="7" style="color:var(--text-muted);">Aucune évaluation enregistrée.</td></tr>
                            <?php else: foreach ($evaluationsData as $e): ?>
                            <tr data-search="<?= htmlspecialchars(strtolower($e['libelle'] . ' ' . $e['matiere'] . ' ' . $e['type'] . ' ' . $e['statut'])) ?>" data-type="<?= htmlspecialchars($e['type']) ?>" data-statut="<?= htmlspecialchars($e['statut']) ?>">
                                <td><?= htmlspecialchars($e['libelle']) ?></td>
                                <td><?= htmlspecialchars($e['matiere']) ?></td>
                                <td><?= $e['dateLabel'] ?></td>
                                <td><?= $e['dureeLabel'] ?></td>
                                <td><span class="type-badge <?= $e['typeClass'] ?>"><?= htmlspecialchars($e['type']) ?></span></td>
                                <td><span class="status-badge <?= $e['statutClass'] ?>"><?= htmlspecialchars($e['statut']) ?></span></td>
                                <td>
                                    <div class="action-icons">
                                        <button class="icon-btn" aria-label="Modifier" onclick='openEditModal(<?= json_encode($e, JSON_UNESCAPED_UNICODE) ?>)'>&#9998;&#65039;</button>
                                        <button class="icon-btn" aria-label="Supprimer" onclick="deleteEval(<?= $e['id'] ?>)">&#128465;&#65039;</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <div id="evalModal" class="modal-overlay">
          <div class="modal-box">
            <h3 id="evalModalTitle">Nouvelle évaluation</h3>
            <form method="POST" action="evaluation.php">
              <input type="hidden" name="id_evaluation" id="form_id_evaluation">
              <label>Libellé</label>
              <input type="text" name="libelle" id="form_libelle" placeholder="Ex: DS Maths #3" required>
              <label>Matière</label>
              <select name="id_matiere" id="form_id_matiere" required>
                <?php foreach ($matieresList as $m): ?>
                <option value="<?= $m['id_matiere'] ?>"><?= htmlspecialchars($m['nom_matiere']) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="modal-row">
                <div><label>Date</label><input type="date" name="date_eval" id="form_date_eval"></div>
                <div><label>Durée (min)</label><input type="number" name="duree_min" id="form_duree_min" min="0" value="60"></div>
              </div>
              <label>Type</label>
              <select name="type" id="form_type">
                <option value="Ecrit">Écrit</option>
                <option value="Pratique">Pratique</option>
                <option value="Oral">Oral</option>
              </select>
              <label>Statut</label>
              <select name="statut" id="form_statut">
                <option value="A venir">À venir</option>
                <option value="En cours">En cours</option>
                <option value="Corrigée">Corrigée</option>
              </select>
              <div class="modal-actions">
                <button type="button" class="btn-cancel-eval" onclick="closeModal()">Annuler</button>
                <button type="submit" class="btn-add-eval">Enregistrer</button>
              </div>
            </form>
          </div>
        </div>
    </main>
    <script>
      function openAddModal() {
        document.getElementById('evalModalTitle').innerText = 'Nouvelle évaluation';
        document.getElementById('form_id_evaluation').value = '';
        document.getElementById('form_libelle').value = '';
        document.getElementById('form_id_matiere').value = '';
        document.getElementById('form_date_eval').value = '';
        document.getElementById('form_duree_min').value = 60;
        document.getElementById('form_type').value = 'Ecrit';
        document.getElementById('form_statut').value = 'A venir';
        document.getElementById('evalModal').style.display = 'flex';
      }
      function openEditModal(e) {
        document.getElementById('evalModalTitle').innerText = "Modifier l'évaluation";
        document.getElementById('form_id_evaluation').value = e.id;
        document.getElementById('form_libelle').value = e.libelle;
        document.getElementById('form_id_matiere').value = e.id_matiere;
        document.getElementById('form_date_eval').value = e.date_eval || '';
        document.getElementById('form_duree_min').value = e.duree_min;
        document.getElementById('form_type').value = e.type;
        document.getElementById('form_statut').value = e.statut;
        document.getElementById('evalModal').style.display = 'flex';
      }
      function closeModal() { document.getElementById('evalModal').style.display = 'none'; }
      function deleteEval(id) { if (confirm('Supprimer cette évaluation ?')) window.location.href = 'evaluation.php?delete=' + id; }
      function applyFilters() {
        const f = document.querySelector('.search-input').value.toLowerCase();
        const type = document.getElementById('typeFilter').value;
        const statut = document.getElementById('statutFilter').value;
        document.querySelectorAll('#evalTable tbody tr').forEach(tr => {
          const s = tr.getAttribute('data-search') || '';
          const matchSearch = s.includes(f);
          const matchType = type === '' || tr.getAttribute('data-type') === type;
          const matchStatut = statut === '' || tr.getAttribute('data-statut') === statut;
          tr.style.display = (matchSearch && matchType && matchStatut) ? '' : 'none';
        });
      }
      function exportEvalCSV() {
        let csv = "Evaluation,Matiere,Date,Duree,Type,Statut\n";
        document.querySelectorAll('#evalTable tbody tr').forEach(tr => {
          if (tr.style.display === 'none') return;
          const cells = tr.querySelectorAll('td');
          if (cells.length < 6) return;
          csv += Array.from(cells).slice(0,6).map(td => td.innerText.replace(/\n/g,' ')).join(',') + "\n";
        });
        const blob = new Blob([csv], {type:"text/csv"});
        const a = document.createElement("a");
        a.href = URL.createObjectURL(blob);
        a.download = "evaluations.csv";
        a.click();
        URL.revokeObjectURL(a.href);
      }
      document.querySelector('.search-input').addEventListener('input', applyFilters);
      document.getElementById('typeFilter').addEventListener('change', applyFilters);
      document.getElementById('statutFilter').addEventListener('change', applyFilters);
    </script>
</body>

</html>
