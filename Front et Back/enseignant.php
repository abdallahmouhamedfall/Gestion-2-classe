<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

require_once(__DIR__ . "/../config/config.php");

// --- Suppression ---
if (isset($_GET['delete'])) {
    $matricule = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM enseignants WHERE Matricule = ?");
    $stmt->bind_param("i", $matricule);
    $stmt->execute();
    header("Location: enseignant.php");
    exit();
}

// --- Ajout / modification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $statut = in_array($_POST['statut'] ?? '', ['Actif', 'En congé']) ? $_POST['statut'] : 'Actif';

    if ($nom !== '' && $prenom !== '') {
        if (!empty($_POST['matricule'])) {
            $matricule = (int)$_POST['matricule'];
            $stmt = $conn->prepare("UPDATE enseignants SET nom=?, prenom=?, adresse=?, telephone=?, statut=? WHERE Matricule=?");
            $stmt->bind_param("sssssi", $nom, $prenom, $adresse, $telephone, $statut, $matricule);
            $stmt->execute();
        } else {
            $stmt = $conn->prepare("INSERT INTO enseignants (nom, prenom, adresse, telephone, statut) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $nom, $prenom, $adresse, $telephone, $statut);
            $stmt->execute();
        }
    }
    header("Location: enseignant.php");
    exit();
}

// --- Récupération des enseignants + matières enseignées + charge horaire ---
$sql = "SELECT en.Matricule, en.nom, en.prenom, en.adresse, en.telephone, en.statut,
               COUNT(c.id_cours) AS nb_seances,
               COALESCE(SUM(c.duree), 0) AS total_minutes,
               GROUP_CONCAT(DISTINCT m.nom_matiere ORDER BY m.nom_matiere SEPARATOR ', ') AS matieres
        FROM enseignants en
        LEFT JOIN cours c ON c.Matricule = en.Matricule
        LEFT JOIN matieres m ON c.id_matiere = m.id_matiere
        GROUP BY en.Matricule, en.nom, en.prenom, en.adresse, en.telephone, en.statut
        ORDER BY en.nom, en.prenom";
$result = $conn->query($sql);

$matiereClasses = ['badge-green', 'badge-sky', 'badge-amber'];
$statutClassesEns = ["Actif" => "badge-green", "En congé" => "badge-amber"];
$enseignantsData = [];
$i = 0;
while ($row = $result->fetch_assoc()) {
    $initials = strtoupper(mb_substr($row['prenom'], 0, 1) . mb_substr($row['nom'], 0, 1));
    $enseignantsData[] = [
        "matricule" => (int)$row['Matricule'],
        "nom" => $row['nom'],
        "prenom" => $row['prenom'],
        "name" => $row['prenom'] . ' ' . $row['nom'],
        "initials" => $initials,
        "adresse" => $row['adresse'] ?? '',
        "telephone" => $row['telephone'] ?? '',
        "matiere" => $row['matieres'] ?: 'Aucune matière',
        "matiereClass" => $matiereClasses[$i % 3],
        "seances" => (int)$row['nb_seances'],
        "heures" => round(((int)$row['total_minutes']) / 60, 1),
        "status" => $row['statut'],
        "statusClass" => $statutClassesEns[$row['statut']] ?? 'badge-green'
    ];
    $i++;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduClass — Enseignants</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  /* mêmes styles que etudiants.html, adaptés */
  :root {
    --navy: #0D1B2A;
    --teal: #00C9A7;
    --sky: #4CC9F0;
    --amber: #FFB830;
    --rose: #FF6B6B;
    --text-main: #E8EDF2;
    --text-muted: #8FA3B8;
    --card-bg: #162435;
    --border: rgba(255,255,255,0.07);
  }
  * { margin:0; padding:0; box-sizing:border-box; }
  body { font-family:'DM Sans',sans-serif; background:var(--navy); color:var(--text-main); }
  #dashboard { display:flex; flex-direction:column; min-height:100vh; }
  .topbar { height:64px; background:var(--card-bg); border-bottom:1px solid var(--border); display:flex; align-items:center; padding:0 28px; gap:16px; }
  .topbar-brand { font-family:'Playfair Display',serif; font-size:18px; display:flex; align-items:center; gap:10px; }
  .topbar-icon { width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--teal),var(--sky));display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:var(--navy); }
  .topbar-right { margin-left:auto; display:flex; align-items:center; gap:16px; }
  .topbar-date { font-size:12px; color:var(--text-muted); }
  .avatar { width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--amber),var(--rose));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;color:white;cursor:pointer; }
  .app-body { display:flex; flex:1; }
  .sidebar { width:220px; background:var(--card-bg); border-right:1px solid var(--border); padding:24px 0; display:flex; flex-direction:column; gap:4px; }
  .sidebar-section { padding:0 16px 8px; font-size:10px; font-weight:600; color:var(--text-muted); text-transform:uppercase; margin-top:12px; }
  .nav-item { margin:0 10px; padding:11px 14px; border-radius:10px; display:flex; align-items:center; gap:11px; font-size:13.5px; font-weight:500; color:var(--text-muted); }
  .nav-item a { text-decoration:none; color:inherit; display:flex; align-items:center; gap:11px; width:100%; }
  .nav-item:hover { background:rgba(255,255,255,.05); color:var(--text-main); }
  .nav-item.active { background:rgba(0,201,167,.1); color:var(--teal); position:relative; }
  .nav-item.active::before { content:''; position:absolute; left:-10px; top:50%; transform:translateY(-50%); width:3px; height:22px; background:var(--teal); border-radius:0 3px 3px 0; }
  .nav-icon { font-size:16px; width:20px; text-align:center; }
  .nav-badge { margin-left:auto; padding:2px 7px; border-radius:20px; background:rgba(0,201,167,.15); color:var(--teal); font-size:10px; }
  .sidebar-bottom { margin-top:auto; padding:16px 10px 0; border-top:1px solid var(--border); }
  .logout-btn { width:100%; padding:11px 14px; border-radius:10px; background:transparent; border:none; color:var(--text-muted); font-size:13px; cursor:pointer; display:flex; align-items:center; gap:10px; }
  .logout-btn:hover { background:rgba(255,107,107,.08); color:var(--rose); }
  .main { flex:1; overflow-y:auto; padding:28px 32px; }
  .page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:28px; }
  .page-title { font-family:'Playfair Display',serif; font-size:28px; font-weight:700; }
  .page-sub { font-size:13px; color:var(--text-muted); margin-top:4px; }
  .btn-primary { background:var(--teal); color:var(--navy); padding:8px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:600; }
  .table-card { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; overflow:hidden; }
  .table-search { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; gap:12px; }
  .search-inp { flex:1; padding:9px 14px; border-radius:8px; background:rgba(255,255,255,.05); border:1px solid var(--border); color:var(--text-main); outline:none; }
  table { width:100%; border-collapse:collapse; }
  th { padding:12px 20px; font-size:11px; font-weight:600; text-transform:uppercase; color:var(--text-muted); text-align:left; border-bottom:1px solid var(--border); }
  td { padding:14px 20px; font-size:13.5px; border-bottom:1px solid var(--border); }
  .td-user { display:flex; align-items:center; gap:10px; }
  .avatar-sm { width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--teal),var(--sky));display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--navy); }
  .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
  .badge-green { background:rgba(0,201,167,.12); color:var(--teal); }
  .badge-sky { background:rgba(76,201,240,.12); color:var(--sky); }
  .badge-amber { background:rgba(255,184,48,.12); color:var(--amber); }
  .icon-btn { width:28px;height:28px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text-muted);cursor:pointer; }
  .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); align-items:center; justify-content:center; z-index:200; }
  .modal-box { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; padding:28px; width:100%; max-width:400px; }
  .modal-box h3 { font-family:'Playfair Display',serif; font-size:20px; margin-bottom:20px; }
  .modal-box label { display:block; font-size:12px; color:var(--text-muted); margin-bottom:6px; margin-top:14px; }
  .modal-box input { width:100%; padding:10px 12px; border-radius:8px; background:rgba(255,255,255,.05); border:1px solid var(--border); color:var(--text-main); outline:none; font-size:13.5px; }
  .modal-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:24px; }
  .btn-cancel { background:transparent; border:1px solid var(--border); color:var(--text-muted); padding:9px 16px; border-radius:8px; cursor:pointer; font-size:13px; }
</style>
</head>
<body>
<div id="dashboard">
   <div class="topbar"><div class="topbar-brand"><div class="topbar-icon">E</div> EduClass</div><div class="topbar-right"><div class="topbar-date" id="currentDate"></div><div class="avatar" onclick="logout()">AD</div></div></div>
  <div class="app-body">
    <nav class="sidebar">
      <div class="sidebar-section">Principal</div><div class="nav-item"><a href="dashboard.php"><span class="nav-icon">📊</span> Tableau de bord</a></div>
      <div class="sidebar-section">Académique</div><div class="nav-item"><a href="etudiant.php"><span class="nav-icon">🎓</span> Étudiants</a></div>
      <div class="nav-item active"><a href="enseignant.php"><span class="nav-icon">👨‍🏫</span> Enseignants <span class="nav-badge"><?= count($enseignantsData) ?></span></a></div>
      <div class="nav-item"><a href="seance.php"><span class="nav-icon">📅</span> Séances</a></div>
      <div class="sidebar-section">Gestion</div><div class="nav-item"><a href="note.php"><span class="nav-icon">📝</span> Notes</a></div>
      <div class="nav-item"><a href="evaluation.php"><span class="nav-icon">📋</span> Évaluations</a></div>
      <div class="nav-item"><a href="planning.php"><span class="nav-icon">🗓️</span> Planning</a></div>
      <div class="nav-item"><a href="abscence.php"><span class="nav-icon">⚠️</span> Absences <span class="nav-badge">5</span></a></div>
      <div class="sidebar-bottom"><button class="logout-btn" onclick="logout()">🚪 Déconnexion</button></div>
    </nav>
    <main class="main">
      <div class="page-header"><div><div class="page-title">Enseignants</div><div class="page-sub">Corps enseignant — <?= count($enseignantsData) ?> professeurs</div></div><button class="btn-primary" onclick="openAddModal()">+ Ajouter enseignant</button></div>
      <div class="table-card">
        <div class="table-search"><input class="search-inp" id="searchInput" placeholder="🔍 Rechercher un enseignant..."><select class="search-inp" id="matiereFilter" style="max-width:180px;"><option value="">Toutes matières</option><?php foreach ($conn->query("SELECT DISTINCT nom_matiere FROM matieres ORDER BY nom_matiere") as $m): ?><option value="<?= htmlspecialchars($m['nom_matiere']) ?>"><?= htmlspecialchars($m['nom_matiere']) ?></option><?php endforeach; ?></select><button class="icon-btn" onclick="exportCSV()">📎</button></div>
        <table><thead><tr><th>Enseignant</th><th>Matière(s)</th><th>Cours donnés</th><th>Heures totales</th><th>Statut</th><th>Actions</th></tr></thead><tbody id="tableBody"></tbody></table>
      </div>
    </main>
  </div>
</div>

<div id="enseignantModal" class="modal-overlay">
  <div class="modal-box">
    <h3 id="modalTitle">Ajouter un enseignant</h3>
    <form method="POST" action="enseignant.php">
      <input type="hidden" name="matricule" id="form_matricule">
      <label>Nom</label>
      <input type="text" name="nom" id="form_nom" required>
      <label>Prénom</label>
      <input type="text" name="prenom" id="form_prenom" required>
      <label>Téléphone</label>
      <input type="text" name="telephone" id="form_telephone">
      <label>Adresse</label>
      <input type="text" name="adresse" id="form_adresse">
      <label>Statut</label>
      <select name="statut" id="form_statut">
        <option value="Actif">Actif</option>
        <option value="En congé">En congé</option>
      </select>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
        <button type="submit" class="btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div><script>
    const enseignants = <?= json_encode($enseignantsData, JSON_UNESCAPED_UNICODE) ?>;
    function renderTable(filter = "") {
      const tbody = document.getElementById("tableBody");
      const f = filter.toLowerCase();
      const matiereFilter = document.getElementById("matiereFilter").value;
      const filtered = enseignants.filter(e =>
        (e.name.toLowerCase().includes(f) || e.matiere.toLowerCase().includes(f)) &&
        (matiereFilter === "" || e.matiere.includes(matiereFilter))
      );
      if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="color:var(--text-muted);">Aucun enseignant trouvé.</td></tr>';
        return;
      }
      tbody.innerHTML = filtered.map(e => `
        <tr>
          <td><div class="td-user"><div class="avatar-sm">${e.initials}</div><div><div class="td-name">${e.name}</div><div class="td-email" style="font-size:11px;color:var(--text-muted)">${e.telephone}</div></div></div></td>
          <td><span class="badge ${e.matiereClass}">${e.matiere}</span></td>
          <td>${e.seances}</td><td>${e.heures}h</td>
          <td><span class="badge ${e.statusClass}">${e.status}</span></td>
          <td><button class="icon-btn" onclick="openViewModal(${e.matricule})">👁</button><button class="icon-btn" onclick="openEditModal(${e.matricule})">✏️</button><button class="icon-btn" onclick="deleteEnseignant(${e.matricule})">🗑️</button></td>
        </tr>
      `).join("");
    }
    function openViewModal(matricule) {
      const e = enseignants.find(x => x.matricule === matricule);
      if (!e) return;
      alert(`${e.name}\nMatière(s): ${e.matiere}\nTéléphone: ${e.telephone}\nAdresse: ${e.adresse || '—'}\nCours donnés: ${e.seances}\nHeures totales: ${e.heures}h\nStatut: ${e.status}`);
    }
    document.getElementById("matiereFilter").addEventListener("change", () => renderTable(document.getElementById("searchInput").value));
    function openAddModal() {
      document.getElementById("modalTitle").innerText = "Ajouter un enseignant";
      document.getElementById("form_matricule").value = "";
      document.getElementById("form_nom").value = "";
      document.getElementById("form_prenom").value = "";
      document.getElementById("form_telephone").value = "";
      document.getElementById("form_adresse").value = "";
      document.getElementById("form_statut").value = "Actif";
      document.getElementById("enseignantModal").style.display = "flex";
    }
    function openEditModal(matricule) {
      const e = enseignants.find(x => x.matricule === matricule);
      if (!e) return;
      document.getElementById("modalTitle").innerText = "Modifier l'enseignant";
      document.getElementById("form_matricule").value = e.matricule;
      document.getElementById("form_nom").value = e.nom;
      document.getElementById("form_prenom").value = e.prenom;
      document.getElementById("form_telephone").value = e.telephone;
      document.getElementById("form_adresse").value = e.adresse;
      document.getElementById("form_statut").value = e.status;
      document.getElementById("enseignantModal").style.display = "flex";
    }
    function closeModal() { document.getElementById("enseignantModal").style.display = "none"; }
    function deleteEnseignant(matricule) { if (confirm("Supprimer cet enseignant ?")) window.location.href = "enseignant.php?delete=" + matricule; }
    function exportCSV() { let csv = "Nom,Matière,Cours donnés,Heures,Statut\n"; enseignants.forEach(e => csv += `${e.name},${e.matiere},${e.seances},${e.heures},${e.status}\n`); const blob = new Blob([csv], {type:"text/csv"}); const a = document.createElement("a"); a.href = URL.createObjectURL(blob); a.download = "enseignants.csv"; a.click(); URL.revokeObjectURL(a.href); }
    document.getElementById("searchInput").addEventListener("input", e => renderTable(e.target.value));
    function updateDate() { document.getElementById("currentDate").innerText = new Date().toLocaleDateString('fr-FR', { weekday:'long', year:'numeric', month:'long', day:'numeric' }).replace(/^\w/, c => c.toUpperCase()); }
    function logout() { window.location.href = 'login.php'; }
    updateDate(); renderTable();
</script>
</body>
</html>