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
    $stmt = $conn->prepare("DELETE FROM etudiants WHERE id_etudiant = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: etudiant.php");
    exit();
}

// Traitement du formulaire : ajout ou modification d'un étudiant
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');

    if ($nom !== '' && $prenom !== '') {
        if (!empty($_POST['id_etudiant'])) {
            // Modification d'un étudiant existant
            $id = (int)$_POST['id_etudiant'];
            $stmt = $conn->prepare("UPDATE etudiants SET nom=?, prenom=?, adresse=?, telephone=? WHERE id_etudiant=?");
            $stmt->bind_param("ssssi", $nom, $prenom, $adresse, $telephone, $id);
            $stmt->execute();
        } else {
            // Ajout d'un nouvel étudiant
            $stmt = $conn->prepare("INSERT INTO etudiants (nom, prenom, adresse, telephone) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $nom, $prenom, $adresse, $telephone);
            $stmt->execute();
        }
    }
    header("Location: etudiant.php");
    exit();
}

// Récupération des étudiants + nb notes + nb absences (sous-requêtes simples, sans FROM imbriqué)
$sql = "SELECT e.id_etudiant, e.nom, e.prenom, e.email, e.telephone, e.adresse,
               (SELECT COUNT(*) FROM notes n WHERE n.id_etudiant = e.id_etudiant) AS nb_notes,
               (SELECT COUNT(*) FROM absences a WHERE a.id_etudiant = e.id_etudiant) AS nb_absences
        FROM etudiants e
        ORDER BY e.nom, e.prenom";

$result = $conn->query($sql);

// Moyenne par matière de chaque étudiant (avec heure_totale pour pondérer), calculée en PHP
$parMatiere = $conn->query(
    "SELECT n.id_etudiant, n.id_matiere, AVG(n.note) AS avg_note, m.heure_totale
     FROM notes n JOIN matieres m ON n.id_matiere = m.id_matiere
     GROUP BY n.id_etudiant, n.id_matiere, m.heure_totale"
);
$ponderation = []; // id_etudiant => ["somme" => x, "coef" => y]
while ($r = $parMatiere->fetch_assoc()) {
    $id = $r['id_etudiant'];
    if (!isset($ponderation[$id])) $ponderation[$id] = ["somme" => 0, "coef" => 0];
    $ponderation[$id]["somme"] += (float)$r['avg_note'] * (int)$r['heure_totale'];
    $ponderation[$id]["coef"] += (int)$r['heure_totale'];
}

$etudiantsData = [];
while ($row = $result->fetch_assoc()) {
    $nbNotes = (int)$row['nb_notes'];
    $id = $row['id_etudiant'];
    $moyenne = 0;
    if ($nbNotes > 0 && isset($ponderation[$id]) && $ponderation[$id]["coef"] > 0) {
        $moyenne = round($ponderation[$id]["somme"] / $ponderation[$id]["coef"], 1);
    }
    $absences = (int)$row['nb_absences'];

    if ($nbNotes === 0) {
        // Pas encore de note enregistrée : l'étudiant est bien inscrit en base, donc Actif par défaut
        $status = "Actif"; $statusClass = "badge-green"; $moyenneClass = "neutral";
    } elseif ($moyenne < 10 || $absences >= 10) {
        $status = "En difficulté"; $statusClass = "badge-rose"; $moyenneClass = "bad";
    } elseif ($absences >= 4 || $moyenne < 12) {
        $status = "À suivre"; $statusClass = "badge-amber"; $moyenneClass = "avg";
    } else {
        $status = "Actif"; $statusClass = "badge-green"; $moyenneClass = "good";
    }

    $initials = mb_substr($row['prenom'], 0, 1) . mb_substr($row['nom'], 0, 1);

    $etudiantsData[] = [
        "id" => (int)$row['id_etudiant'],
        "nom" => $row['nom'],
        "prenom" => $row['prenom'],
        "name" => $row['prenom'] . " " . $row['nom'],
        "initials" => strtoupper($initials),
         "email" => $row['email'] ?? '',
        "telephone" => $row['telephone'] ?? '',
        "adresse" => $row['adresse'] ?? '',
        "matricule" => sprintf("ETU-%03d", $row['id_etudiant']),
        "moyenne" => $nbNotes === 0 ? "—" : $moyenne,
        "absences" => $absences,
        "status" => $status,
        "statusClass" => $statusClass,
        "moyenneClass" => $moyenneClass
    ];
}
?>

<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>EduClass — Étudiants</title><link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"><style>  /* mêmes variables et styles que dashboard, adaptés pour le tableau */  :root {    --navy: #0D1B2A;    --teal: #00C9A7;    --sky: #4CC9F0;    --amber: #FFB830;    --rose: #FF6B6B;    --text-main: #E8EDF2;    --text-muted: #8FA3B8;    --card-bg: #162435;    --border: rgba(255,255,255,0.07);  }  * { margin:0; padding:0; box-sizing:border-box; }  body { font-family:'DM Sans',sans-serif; background:var(--navy); color:var(--text-main); }  #dashboard { display:flex; flex-direction:column; min-height:100vh; }  .topbar { height:64px; background:var(--card-bg); border-bottom:1px solid var(--border); display:flex; align-items:center; padding:0 28px; gap:16px; }  .topbar-brand { font-family:'Playfair Display',serif; font-size:18px; display:flex; align-items:center; gap:10px; }  .topbar-icon { width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--teal),var(--sky));display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:var(--navy); }  .topbar-right { margin-left:auto; display:flex; align-items:center; gap:16px; }  .topbar-date { font-size:12px; color:var(--text-muted); }  .avatar { width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--amber),var(--rose));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;color:white;cursor:pointer; }  .app-body { display:flex; flex:1; }  .sidebar { width:220px; background:var(--card-bg); border-right:1px solid var(--border); padding:24px 0; display:flex; flex-direction:column; gap:4px; }  .sidebar-section { padding:0 16px 8px; font-size:10px; font-weight:600; color:var(--text-muted); text-transform:uppercase; margin-top:12px; }  .nav-item { margin:0 10px; padding:11px 14px; border-radius:10px; dsiplay:flex; align-items:center; gap:11px; font-size:13.5px; font-weight:500; color:var(--text-muted); }  .nav-item a { text-decoration:none; color:inherit; display:flex; align-items:center; gap:11px; width:100%; }  .nav-item:hover { background:rgba(255,255,255,.05); color:var(--text-main); }  .nav-item.active { background:rgba(0,201,167,.1); color:var(--teal); position:relative; }  .nav-item.active::before { content:''; position:absolute; left:-10px; top:50%; transform:translateY(-50%); width:3px; height:22px; background:var(--teal); border-radius:0 3px 3px 0; }  .nav-icon { font-size:16px; width:20px; text-align:center; }  .nav-badge { margin-left:auto; padding:2px 7px; border-radius:20px; background:rgba(0,201,167,.15); color:var(--teal); font-size:10px; }  .sidebar-bottom { margin-top:auto; padding:16px 10px 0; border-top:1px solid var(--border); }  .logout-btn { width:100%; padding:11px 14px; border-radius:10px; background:transparent; border:none; color:var(--text-muted); font-size:13px; cursor:pointer; display:flex; align-items:center; gap:10px; }  .logout-btn:hover { background:rgba(255,107,107,.08); color:var(--rose); }  .main { flex:1; overflow-y:auto; padding:28px 32px; }  .page-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:28px; }  .page-title { font-family:'Playfair Display',serif; font-size:28px; font-weight:700; }  .page-sub { font-size:13px; color:var(--text-muted); margin-top:4px; }  .btn-primary { background:var(--teal); color:var(--navy); padding:8px 16px; border-radius:8px; border:none; cursor:pointer; font-weight:600; }  .table-card { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; overflow:hidden; }  .table-search { padding:16px 20px; border-bottom:1px solid var(--border); display:flex; gap:12px; }  .search-inp { flex:1; padding:9px 14px; border-radius:8px; background:rgba(255,255,255,.05); border:1px solid var(--border); color:var(--text-main); outline:none; }  table { width:100%; border-collapse:collapse; }  th { padding:12px 20px; font-size:11px; font-weight:600; text-transform:uppercase; color:var(--text-muted); text-align:left; border-bottom:1px solid var(--border); }  td { padding:14px 20px; font-size:13.5px; border-bottom:1px solid var(--border); }  tr:last-child td { border-bottom:none; }  .td-user { display:flex; align-items:center; gap:10px; }  .avatar-sm { width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--teal),var(--sky));display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--navy); }  .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }  .badge-green { background:rgba(0,201,167,.12); color:var(--teal); }  .badge-amber { background:rgba(255,184,48,.12); color:var(--amber); }  .badge-rose { background:rgba(255,107,107,.12); color:var(--rose); }  .good { color:var(--teal); font-weight:600; }  .avg { color:var(--amber); }  .bad { color:var(--rose); }  .neutral { color:var(--text-muted); }  .icon-btn { width:28px;height:28px;border-radius:6px;border:1px solid var(--border);background:transparent;color:var(--text-muted);cursor:pointer; }
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
      <div class="sidebar-section">Académique</div><div class="nav-item active"><a href="etudiant.php"><span class="nav-icon">🎓</span> Étudiants <span class="nav-badge"><?= count($etudiantsData) ?></span></a></div>
      <div class="nav-item"><a href="enseignant.php"><span class="nav-icon">👨‍🏫</span> Enseignants <span class="nav-badge">4</span></a></div>
      <div class="nav-item"><a href="seance.php"><span class="nav-icon">📅</span> Séances</a></div>
      <div class="sidebar-section">Gestion</div><div class="nav-item"><a href="note.php"><span class="nav-icon">📝</span> Notes</a></div>
      <div class="nav-item"><a href="evaluation.php"><span class="nav-icon">📋</span> Évaluations</a></div>
      <div class="nav-item"><a href="planning.php"><span class="nav-icon">🗓️</span> Planning</a></div>
      <div class="nav-item"><a href="abscence.php"><span class="nav-icon">⚠️</span> Absences <span class="nav-badge">5</span></a></div>
      <div class="sidebar-bottom"><button class="logout-btn" onclick="logout()">🚪 Déconnexion</button></div>
    </nav>
    <main class="main">
      <div class="page-header"><div><div class="page-title">Étudiants</div><div class="page-sub"><?= count($etudiantsData) ?> étudiants inscrits</div></div><button class="btn-primary" onclick="openAddModal()">+ Ajouter étudiant</button></div>
      <div class="table-card">
        <div class="table-search"><input class="search-inp" id="searchInput" placeholder="🔍 Rechercher un étudiant..."><select class="search-inp" id="statusFilter" style="max-width:160px;"><option value="">Tous statuts</option><option value="Actif">Actif</option><option value="À suivre">À suivre</option><option value="En difficulté">En difficulté</option></select><button class="icon-btn" onclick="exportCSV()">📎</button></div>
        <table id="etudiantsTable"><thead><tr><th>Étudiant</th><th>Matricule</th><th>Téléphone</th><th>Moyenne</th><th>Absences</th><th>Statut</th><th>Actions</th></thead><tbody id="tableBody"></tbody></table>
      </div>
    </main>
  </div>
</div>

<div id="etudiantModal" class="modal-overlay">
  <div class="modal-box">
    <h3 id="modalTitle">Ajouter un étudiant</h3>
    <form method="POST" action="etudiant.php">
      <input type="hidden" name="id_etudiant" id="form_id">
      <label>Nom</label>
      <input type="text" name="nom" id="form_nom" required>
      <label>Prénom</label>
      <input type="text" name="prenom" id="form_prenom" required>
      <label>Email</label>
      <input type="text" name="email" id="form_email">
      <label>Téléphone</label>
      <input type="text" name="telephone" id="form_telephone">
      <label>Adresse</label>
      <input type="text" name="adresse" id="form_adresse">
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
        <button type="submit" class="btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
  const etudiants = <?= json_encode($etudiantsData, JSON_UNESCAPED_UNICODE) ?>;
  function renderTable(filter = "") {
    const tbody = document.getElementById("tableBody");
    const f = filter.toLowerCase();
    const statusFilter = document.getElementById("statusFilter").value;
    const filtered = etudiants.filter(e =>
      (e.name.toLowerCase().includes(f) || e.matricule.toLowerCase().includes(f)) &&
      (statusFilter === "" || e.status === statusFilter)
    );
    tbody.innerHTML = filtered.map(e => `
      <tr>
        <td><div class="td-user"><div class="avatar-sm">${e.initials}</div>
        <div><div class="td-name">${e.name}</div>
        <div class="td-email" style="display:block; font-size:11px;color:var(--text-muted); margin-top:4px">${e.email}</div></div></div></td>
        <td>${e.matricule}</td><td>${e.telephone}</td>
        <td class="${e.moyenneClass}">${e.moyenne}</td><td>${e.absences}</td>
        <td><span class="badge ${e.statusClass}">${e.status}</span></td>
        <td><button class="icon-btn" onclick="openViewModal(${e.id})">👁</button><button class="icon-btn" onclick="openEditModal(${e.id})">✏️</button><button class="icon-btn" onclick="deleteEtudiant(${e.id})">🗑️</button></td>
       </tr>
    `).join("");
  }
  function openViewModal(id) {
    const e = etudiants.find(x => x.id === id);
    if (!e) return;
    alert(`${e.name}\nMatricule: ${e.matricule}\nTéléphone: ${e.telephone}\nAdresse: ${e.adresse || '—'}\nMoyenne: ${e.moyenne}\nAbsences: ${e.absences}\nStatut: ${e.status}`);
  }
  function deleteEtudiant(id) { if (confirm("Supprimer cet étudiant ? Cette action supprimera aussi ses notes et absences.")) window.location.href = "etudiant.php?delete=" + id; }
  document.getElementById("statusFilter").addEventListener("change", () => renderTable(document.getElementById("searchInput").value));
  function openAddModal() {
    document.getElementById("modalTitle").innerText = "Ajouter un étudiant";
    document.getElementById("form_id").value = "";
    document.getElementById("form_nom").value = "";
    document.getElementById("form_prenom").value = "";
    document.getElementById("form_email").value = "";
    document.getElementById("form_telephone").value = "";
    document.getElementById("form_adresse").value = "";
    document.getElementById("etudiantModal").style.display = "flex";
  }
  function openEditModal(id) {
    const e = etudiants.find(x => x.id === id);
    if (!e) return;
    document.getElementById("modalTitle").innerText = "Modifier l'étudiant";
    document.getElementById("form_id").value = e.id;
    document.getElementById("form_nom").value = e.nom;
    document.getElementById("form_prenom").value = e.prenom;
    document.getElementById("form_email").value = e.email;
    document.getElementById("form_telephone").value = e.telephone;
    document.getElementById("form_adresse").value = e.adresse;
    document.getElementById("etudiantModal").style.display = "flex";
  }
  function closeModal() { document.getElementById("etudiantModal").style.display = "none"; }
  function exportCSV() { let csv = "Nom,Matricule,Téléphone,Moyenne,Absences,Statut\n"; etudiants.forEach(e => csv += `${e.name},${e.matricule},${e.telephone},${e.moyenne},${e.absences},${e.status}\n`); const blob = new Blob([csv], {type:"text/csv"}); const a = document.createElement("a"); a.href = URL.createObjectURL(blob); a.download = "etudiants.csv"; a.click(); URL.revokeObjectURL(a.href); }
  document.getElementById("searchInput").addEventListener("input", e => renderTable(e.target.value));
  function updateDate() { document.getElementById("currentDate").innerText = new Date().toLocaleDateString('fr-FR', { weekday:'long', year:'numeric', month:'long', day:'numeric' }).replace(/^\w/, c => c.toUpperCase()); }
  function logout() { window.location.href = 'login.php'; }
  updateDate(); renderTable();
</script>
</body>
</html>
