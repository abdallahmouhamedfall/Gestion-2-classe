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
    $stmt = $conn->prepare("DELETE FROM absences WHERE id_absence = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: abscence.php");
    exit();
}

// --- Ajout / modification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_etudiant = (int)($_POST['id_etudiant'] ?? 0);
    $id_seance = (int)($_POST['id_seance'] ?? 0);
    $date_absence = $_POST['date_absence'] ?? '';
    $heure_absence = $_POST['heure_absence'] ?: null;
    $type = in_array($_POST['type'] ?? '', ['Absence', 'Retard']) ? $_POST['type'] : 'Absence';
    $motif = trim($_POST['motif'] ?? '');
    $justifie = in_array($_POST['justifie'] ?? '', ['Oui', 'Non', 'Partiel']) ? $_POST['justifie'] : 'Non';

    if ($id_etudiant && $id_seance && $date_absence !== '') {
        if (!empty($_POST['id_absence'])) {
            $id = (int)$_POST['id_absence'];
            $stmt = $conn->prepare("UPDATE absences SET id_etudiant=?, id_seance=?, date_absence=?, heure_absence=?, type=?, motif=?, justifie=? WHERE id_absence=?");
            $stmt->bind_param("iisssssi", $id_etudiant, $id_seance, $date_absence, $heure_absence, $type, $motif, $justifie, $id);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO absences (id_etudiant, id_seance, date_absence, heure_absence, type, motif, justifie) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssss", $id_etudiant, $id_seance, $date_absence, $heure_absence, $type, $motif, $justifie);
            $stmt->execute();
        }
    }
    header("Location: abscence.php");
    exit();
}

// --- Listes pour les menus déroulants ---
$etudiantsList = $conn->query("SELECT id_etudiant, nom, prenom FROM etudiants ORDER BY nom, prenom")->fetch_all(MYSQLI_ASSOC);
$seancesList = $conn->query(
    "SELECT c.id_seance, m.nom_matiere, c.date_cours
     FROM cours c JOIN matieres m ON c.id_matiere = m.id_matiere
     ORDER BY c.date_cours DESC"
)->fetch_all(MYSQLI_ASSOC);

// --- Statistiques de la semaine en cours ---
$statAbsences = $conn->query("SELECT COUNT(*) AS n FROM absences WHERE type='Absence' AND YEARWEEK(date_absence,1)=YEARWEEK(CURDATE(),1)")->fetch_assoc()['n'];
$statRetards = $conn->query("SELECT COUNT(*) AS n FROM absences WHERE type='Retard' AND YEARWEEK(date_absence,1)=YEARWEEK(CURDATE(),1)")->fetch_assoc()['n'];
$statJustifies = $conn->query("SELECT COUNT(*) AS n FROM absences WHERE justifie='Oui' AND YEARWEEK(date_absence,1)=YEARWEEK(CURDATE(),1)")->fetch_assoc()['n'];

// --- Liste complète des absences/retards ---
$sql = "SELECT a.id_absence, a.date_absence, a.heure_absence, a.type, a.motif, a.justifie,
               a.id_etudiant, a.id_seance, et.nom, et.prenom,
               m.nom_matiere
        FROM absences a
        JOIN etudiants et ON a.id_etudiant = et.id_etudiant
        LEFT JOIN cours c ON c.id_seance = a.id_seance
        LEFT JOIN matieres m ON c.id_matiere = m.id_matiere
        ORDER BY a.date_absence DESC";
$result = $conn->query($sql);

$avatarColors = ['#fb923c', '#22d3ee', '#f87171', '#a78bfa', '#34d399'];
$justifieClasses = ["Oui" => "oui", "Non" => "non", "Partiel" => "partiel"];
$i = 0;
$absencesData = [];
while ($row = $result->fetch_assoc()) {
    $initials = strtoupper(mb_substr($row['prenom'], 0, 1) . mb_substr($row['nom'], 0, 1));
    $absencesData[] = [
        "id" => (int)$row['id_absence'],
        "id_etudiant" => (int)$row['id_etudiant'],
        "id_seance" => (int)$row['id_seance'],
        "name" => $row['prenom'] . ' ' . $row['nom'],
        "initials" => $initials,
        "avatarColor" => $avatarColors[$i % count($avatarColors)],
        "date" => (new DateTime($row['date_absence']))->format('d/m/Y'),
        "date_raw" => $row['date_absence'],
        "heure" => $row['heure_absence'],
        "matiere" => $row['nom_matiere'] ?: '—',
        "type" => $row['type'],
        "motif" => $row['motif'] ?: '—',
        "justifie" => $row['justifie'],
        "justifieClass" => $justifieClasses[$row['justifie']] ?? 'non'
    ];
    $i++;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absences - EduClass</title>
    <link rel="stylesheet" href="abscence.css">
    <style>
      .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); align-items:center; justify-content:center; z-index:200; }
      .modal-box { background:#131a2b; border-radius:16px; padding:28px; width:100%; max-width:420px; max-height:90vh; overflow-y:auto; }
      .modal-box h3 { font-size:18px; margin-bottom:20px; color:#fff; }
      .modal-box label { display:block; font-size:12px; color:#94a3b8; margin-bottom:6px; margin-top:14px; }
      .modal-box input, .modal-box select { width:100%; padding:10px 12px; border-radius:8px; background:#1e293b; border:none; color:#e2e8f0; outline:none; font-size:13.5px; }
      .modal-row { display:flex; gap:10px; }
      .modal-row > div { flex:1; }
      .modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:24px; }
      .btn-cancel-abs { background:transparent; border:1px solid #334155; color:#94a3b8; padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; }
      .btn-add-abs { background:linear-gradient(90deg, #14b8a6 0%, #2dd4bf 100%); border:none; padding:10px 18px; border-radius:8px; color:#0a0e1a; font-weight:700; font-size:13px; cursor:pointer; }
    </style>
</head>
<body>

    <div class="layout">
        <div class="sidebar">
            <div class="logo-row">
                <div class="logo-e" style="width: 40px; height: 40px; background: linear-gradient(135deg, #14b8a6 0%, #2dd4bf 100%); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 22px; color: #0a0e1a;">E</div>
                <div class="logo-text">EduClass</div>
            </div>
            
            <div class="section">PRINCIPAL</div>
            <a href="dashboard.php" class="nav-link">📊 Tableau de bord</a>

            <div class="section">ACADÉMIQUE</div>
            <a href="etudiant.php" class="nav-link">🎓 Étudiants</a>
            <a href="enseignant.php" class="nav-link">👨‍🏫 Enseignants</a>
            <a href="seance.php" class="nav-link">🗓️ Séances</a>

            <div class="section">GESTION</div>
            <a href="note.php" class="nav-link">📝 Notes</a>
            <a href="evaluation.php" class="nav-link">📄 Évaluations</a>
            <a href="planning.php" class="nav-link">📅 Planning</a>
            <a href="abscence.php" class="nav-link active">⚠️ Absences <span class="badge-count red"><?= count($absencesData) ?></span></a>
            <a href="logout.php" class="nav-link" style="margin-top: 20px;">🚪 Déconnexion</a>
        </div>

        <div class="main">
            <div class="top-header">
                <div>
                    <div class="page-title">Absences & retards</div>
                    <div class="page-subtitle">Gestion des présences et justificatifs</div>
                </div>
                <button class="btn-secondary" onclick="openAddModal()">+ Nouvelle absence</button>
            </div>

            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-num red"><?= $statAbsences ?></div>
                    <div class="stat-txt">Absences cette semaine</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num yellow"><?= $statRetards ?></div>
                    <div class="stat-txt">Retards signalés</div>
                </div>
                <div class="stat-box">
                    <div class="stat-num cyan"><?= $statJustifies ?></div>
                    <div class="stat-txt">Justificatifs reçus</div>
                </div>
            </div>

            <div class="table-container">
                <div class="search-row">
                    <input class="search-box" id="searchInput" placeholder="🔍 Rechercher par étudiant, matière, motif...">
                    <select class="btn-secondary" id="typeFilter"><option value="">Tous types</option><option value="Absence">Absence</option><option value="Retard">Retard</option></select>
                    <select class="btn-secondary" id="justifieFilter"><option value="">Tous justificatifs</option><option value="Oui">Oui</option><option value="Non">Non</option><option value="Partiel">Partiel</option></select>
                    <button class="btn-secondary" onclick="exportCSV()">Export 📥</button>
                </div>

                <table class="absence-table" id="absenceTable">
                    <thead>
                        <tr>
                            <th>ÉTUDIANT</th>
                            <th>DATE</th>
                            <th>MATIÈRE</th>
                            <th>TYPE</th>
                            <th>MOTIF</th>
                            <th>JUSTIFIÉ</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="absenceModal" class="modal-overlay">
      <div class="modal-box">
        <h3 id="modalTitle">Nouvelle absence</h3>
        <form method="POST" action="abscence.php">
          <input type="hidden" name="id_absence" id="form_id_absence">
          <label>Étudiant</label>
          <select name="id_etudiant" id="form_id_etudiant" required>
            <?php foreach ($etudiantsList as $e): ?>
            <option value="<?= $e['id_etudiant'] ?>"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></option>
            <?php endforeach; ?>
          </select>
          <label>Séance (matière / date)</label>
          <select name="id_seance" id="form_id_seance" required>
            <?php foreach ($seancesList as $s): ?>
            <option value="<?= $s['id_seance'] ?>"><?= htmlspecialchars($s['nom_matiere']) ?> — <?= $s['date_cours'] ? (new DateTime($s['date_cours']))->format('d/m/Y') : 'sans date' ?></option>
            <?php endforeach; ?>
          </select>
          <div class="modal-row">
            <div><label>Date</label><input type="date" name="date_absence" id="form_date_absence" required></div>
            <div><label>Heure</label><input type="time" name="heure_absence" id="form_heure_absence"></div>
          </div>
          <label>Type</label>
          <select name="type" id="form_type">
            <option value="Absence">Absence</option>
            <option value="Retard">Retard</option>
          </select>
          <label>Motif</label>
          <input type="text" name="motif" id="form_motif" placeholder="Ex: Maladie, Transport...">
          <label>Justifié</label>
          <select name="justifie" id="form_justifie">
            <option value="Non">Non</option>
            <option value="Oui">Oui</option>
            <option value="Partiel">Partiel</option>
          </select>
          <div class="modal-actions">
            <button type="button" class="btn-cancel-abs" onclick="closeModal()">Annuler</button>
            <button type="submit" class="btn-add-abs">Enregistrer</button>
          </div>
        </form>
      </div>
    </div>

    <script>
      const absences = <?= json_encode($absencesData, JSON_UNESCAPED_UNICODE) ?>;
      function renderTable(filter = "") {
        const tbody = document.getElementById("tableBody");
        const f = filter.toLowerCase();
        const typeFilter = document.getElementById("typeFilter").value;
        const justifieFilter = document.getElementById("justifieFilter").value;
        const filtered = absences.filter(a =>
          (a.name.toLowerCase().includes(f) || a.matiere.toLowerCase().includes(f) || a.motif.toLowerCase().includes(f)) &&
          (typeFilter === "" || a.type === typeFilter) &&
          (justifieFilter === "" || a.justifie === justifieFilter)
        );
        if (filtered.length === 0) {
          tbody.innerHTML = '<tr><td colspan="7" style="color:#64748b;">Aucune absence trouvée.</td></tr>';
          return;
        }
        tbody.innerHTML = filtered.map(a => `
          <tr>
            <td><div class="student-cell"><div class="avatar-small" style="background: ${a.avatarColor}">${a.initials}</div>${a.name}</div></td>
            <td>${a.date}</td>
            <td style="color: #38bdf8; font-weight: 600;">${a.matiere}</td>
            <td><span class="tag ${a.type === 'Absence' ? 'absence' : 'retard'}">${a.type}</span></td>
            <td>${a.motif}</td>
            <td><span class="tag ${a.justifieClass}">${a.justifie}</span></td>
            <td>
              <div class="action-icons">
                <span style="cursor:pointer;" onclick="openEditModal(${a.id})">✏️</span>
                <span style="cursor:pointer;" onclick="deleteAbsence(${a.id})">🗑️</span>
              </div>
            </td>
          </tr>
        `).join("");
      }
      function openAddModal() {
        document.getElementById("modalTitle").innerText = "Nouvelle absence";
        document.getElementById("form_id_absence").value = "";
        document.getElementById("form_id_etudiant").value = "";
        document.getElementById("form_id_seance").value = "";
        document.getElementById("form_date_absence").value = "";
        document.getElementById("form_heure_absence").value = "";
        document.getElementById("form_type").value = "Absence";
        document.getElementById("form_motif").value = "";
        document.getElementById("form_justifie").value = "Non";
        document.getElementById("absenceModal").style.display = "flex";
      }
      function openEditModal(id) {
        const a = absences.find(x => x.id === id);
        if (!a) return;
        document.getElementById("modalTitle").innerText = "Modifier l'absence";
        document.getElementById("form_id_absence").value = a.id;
        document.getElementById("form_id_etudiant").value = a.id_etudiant;
        document.getElementById("form_id_seance").value = a.id_seance;
        document.getElementById("form_date_absence").value = a.date_raw;
        document.getElementById("form_heure_absence").value = a.heure || "";
        document.getElementById("form_type").value = a.type;
        document.getElementById("form_motif").value = a.motif === '—' ? '' : a.motif;
        document.getElementById("form_justifie").value = a.justifie;
        document.getElementById("absenceModal").style.display = "flex";
      }
      function closeModal() { document.getElementById("absenceModal").style.display = "none"; }
      function deleteAbsence(id) { if (confirm("Supprimer cette entrée ?")) window.location.href = "abscence.php?delete=" + id; }
      function exportCSV() {
        let csv = "Étudiant,Date,Matière,Type,Motif,Justifié\n";
        absences.forEach(a => csv += `${a.name},${a.date},${a.matiere},${a.type},${a.motif},${a.justifie}\n`);
        const blob = new Blob([csv], {type:"text/csv"});
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = "absences.csv";
        link.click();
        URL.revokeObjectURL(link.href);
      }
      document.getElementById("searchInput").addEventListener("input", e => renderTable(e.target.value));
      document.getElementById("typeFilter").addEventListener("change", () => renderTable(document.getElementById("searchInput").value));
      document.getElementById("justifieFilter").addEventListener("change", () => renderTable(document.getElementById("searchInput").value));
      renderTable();
    </script>

</body>
</html>
