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
    $stmt = $conn->prepare("DELETE FROM cours WHERE id_cours = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: seance.php");
    exit();
}

// --- Ajout / modification ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $libelle = trim($_POST['libelle'] ?? '');
    $id_matiere = (int)($_POST['id_matiere'] ?? 0);
    $matricule = (int)($_POST['matricule'] ?? 0);
    $duree = (int)($_POST['duree'] ?? 0);
    $date_cours = $_POST['date_cours'] ?: null;
    $heure_debut = $_POST['heure_debut'] ?: null;
    $salle = trim($_POST['salle'] ?? '');
    $statut = in_array($_POST['statut'] ?? '', ['Planifiée', 'En cours', 'Terminée']) ? $_POST['statut'] : 'Planifiée';
    $progression = max(0, min(100, (int)($_POST['progression'] ?? 0)));

    if ($libelle !== '' && $id_matiere && $matricule) {
        if (!empty($_POST['id_cours']) && !empty($_POST['id_seance'])) {
            // Modification
            $id_cours = (int)$_POST['id_cours'];
            $id_seance = (int)$_POST['id_seance'];
            $stmt = $conn->prepare("UPDATE seances SET libelle=? WHERE id_seance=?");
            $stmt->bind_param("si", $libelle, $id_seance);
            $stmt->execute();
            $stmt = $conn->prepare("UPDATE cours SET id_matiere=?, Matricule=?, duree=?, date_cours=?, heure_debut=?, salle=?, statut=?, progression=? WHERE id_cours=?");
            $stmt->bind_param("iiisssii", $id_matiere, $matricule, $duree, $date_cours, $heure_debut, $salle, $statut, $progression, $id_cours);
            $stmt->execute();
        } else {
            // Ajout : on crée d'abord la séance (libellé), puis le cours associé
            $stmt = $conn->prepare("INSERT INTO seances (libelle) VALUES (?)");
            $stmt->bind_param("s", $libelle);
            $stmt->execute();
            $id_seance = $conn->insert_id;

            $stmt = $conn->prepare("INSERT INTO cours (id_seance, id_matiere, Matricule, duree, date_cours, heure_debut, salle, statut, progression) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiissssi", $id_seance, $id_matiere, $matricule, $duree, $date_cours, $heure_debut, $salle, $statut, $progression);
            $stmt->execute();
        }
    }
    header("Location: seance.php");
    exit();
}

// --- Listes pour les menus déroulants du formulaire ---
$matieresList = $conn->query("SELECT id_matiere, nom_matiere FROM matieres ORDER BY nom_matiere")->fetch_all(MYSQLI_ASSOC);
$enseignantsList = $conn->query("SELECT Matricule, nom, prenom FROM enseignants ORDER BY nom")->fetch_all(MYSQLI_ASSOC);

// --- Récupération des cours/séances ---
$sql = "SELECT c.id_cours, c.id_seance, s.libelle, m.id_matiere, m.nom_matiere, e.Matricule, e.nom AS ens_nom, e.prenom AS ens_prenom,
               c.duree, c.date_cours, c.heure_debut, c.salle, c.statut, c.progression
        FROM cours c
        JOIN seances s ON c.id_seance = s.id_seance
        JOIN matieres m ON c.id_matiere = m.id_matiere
        JOIN enseignants e ON c.Matricule = e.Matricule
        ORDER BY (c.date_cours IS NULL), c.date_cours ASC, c.heure_debut ASC";
$result = $conn->query($sql);

$statutClasses = ["Planifiée" => "badge-amber", "En cours" => "badge-green", "Terminée" => "badge-sky"];
$today = new DateTime();
$tomorrow = (new DateTime())->modify('+1 day');

$seancesData = [];
while ($row = $result->fetch_assoc()) {
    $dateLabel = "Non planifiée";
    if ($row['date_cours']) {
        $d = new DateTime($row['date_cours']);
        if ($d->format('Y-m-d') === $today->format('Y-m-d')) $dateLabel = "Aujourd'hui";
        elseif ($d->format('Y-m-d') === $tomorrow->format('Y-m-d')) $dateLabel = "Demain";
        else $dateLabel = $d->format('d/m/Y');
    }
    $horaire = "—";
    if ($row['heure_debut']) {
        $debut = new DateTime($row['heure_debut']);
        $fin = (clone $debut)->modify('+' . (int)$row['duree'] . ' minutes');
        $horaire = $debut->format('H\hi') . ' › ' . $fin->format('H\hi');
    }
    $initials = strtoupper(mb_substr($row['ens_prenom'], 0, 1) . mb_substr($row['ens_nom'], 0, 1));

    $seancesData[] = [
        "id" => (int)$row['id_cours'],
        "id_seance" => (int)$row['id_seance'],
        "libelle" => $row['libelle'],
        "matiere" => $row['nom_matiere'],
        "id_matiere" => (int)$row['id_matiere'],
        "matricule" => (int)$row['Matricule'],
        "enseignant" => $row['ens_prenom'] . ' ' . $row['ens_nom'],
        "initials" => $initials,
        "duree" => (int)$row['duree'],
        "date_cours" => $row['date_cours'],
        "heure_debut" => $row['heure_debut'],
        "date" => $dateLabel,
        "horaire" => $horaire,
        "salle" => $row['salle'] ?: '—',
        "statut" => $row['statut'],
        "statutClass" => $statutClasses[$row['statut']] ?? 'badge-amber',
        "progression" => (int)$row['progression'],
        "couleur" => $row['statut'] === 'Terminée' ? 'var(--teal)' : ($row['statut'] === 'En cours' ? 'var(--teal)' : 'var(--amber)')
    ];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduClass — Séances</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
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
  .search-bar { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; padding:16px 20px; margin-bottom:24px; display:flex; gap:12px; }
  .search-inp { flex:1; padding:9px 14px; border-radius:8px; background:rgba(255,255,255,.05); border:1px solid var(--border); color:var(--text-main); outline:none; }
  .seances-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(320px,1fr)); gap:20px; }
  .seance-card { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; padding:20px; transition:transform .2s; }
  .seance-card:hover { transform:translateY(-3px); border-color:rgba(0,201,167,.3); }
  .seance-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; }
  .seance-matiere { font-weight:700; font-size:16px; }
  .seance-time { font-size:12px; color:var(--text-muted); margin-top:4px; }
  .seance-room { margin-top:10px; font-size:12px; color:var(--sky); background:rgba(76,201,240,.1); padding:4px 10px; border-radius:20px; display:inline-block; }
  .seance-teacher { display:flex; align-items:center; gap:10px; margin-top:14px; font-size:13px; }
  .avatar-sm { width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,var(--teal),var(--sky));display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:var(--navy); }
  .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
  .badge-green { background:rgba(0,201,167,.12); color:var(--teal); }
  .badge-sky { background:rgba(76,201,240,.12); color:var(--sky); }
  .badge-amber { background:rgba(255,184,48,.12); color:var(--amber); }
  .card-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:16px; padding-top:12px; border-top:1px solid var(--border); }
  .icon-btn { width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:transparent;color:var(--text-muted);cursor:pointer; }
  .seance-progress { margin-top:16px; }
  .prog-bar { height:4px; background:rgba(255,255,255,.08); border-radius:4px; margin-top:6px; }
  .prog-fill { height:100%; border-radius:4px; }
  .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); align-items:center; justify-content:center; z-index:200; }
  .modal-box { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; padding:28px; width:100%; max-width:420px; max-height:90vh; overflow-y:auto; }
  .modal-box h3 { font-family:'Playfair Display',serif; font-size:20px; margin-bottom:16px; }
  .modal-box label { display:block; font-size:12px; color:var(--text-muted); margin-bottom:6px; margin-top:14px; }
  .modal-box input, .modal-box select { width:100%; padding:10px 12px; border-radius:8px; background:rgba(255,255,255,.05); border:1px solid var(--border); color:var(--text-main); outline:none; font-size:13.5px; }
  .modal-row { display:flex; gap:10px; }
  .modal-row > div { flex:1; }
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
      <div class="nav-item"><a href="enseignant.php"><span class="nav-icon">👨‍🏫</span> Enseignants</a></div>
      <div class="nav-item active"><a href="seance.php"><span class="nav-icon">📅</span> Séances <span class="nav-badge"><?= count($seancesData) ?></span></a></div>
      <div class="sidebar-section">Gestion</div><div class="nav-item"><a href="note.php"><span class="nav-icon">📝</span> Notes</a></div>
      <div class="nav-item"><a href="evaluation.php"><span class="nav-icon">📋</span> Évaluations</a></div>
      <div class="nav-item"><a href="planning.php"><span class="nav-icon">🗓️</span> Planning</a></div>
      <div class="nav-item"><a href="abscence.php"><span class="nav-icon">⚠️</span> Absences <span class="nav-badge">5</span></a></div>
      <div class="sidebar-bottom"><button class="logout-btn" onclick="logout()">🚪 Déconnexion</button></div>
    </nav>
    <main class="main">
      <div class="page-header"><div><div class="page-title">Séances de cours</div><div class="page-sub"><?= count($seancesData) ?> cours enregistrés</div></div><button class="btn-primary" onclick="openAddModal()">+ Nouvelle séance</button></div>
      <div class="search-bar"><input class="search-inp" id="searchInput" placeholder="🔍 Rechercher par matière, enseignant, salle..."><select class="search-inp" id="statutFilter" style="max-width:160px;"><option value="">Tous statuts</option><option value="Planifiée">Planifiée</option><option value="En cours">En cours</option><option value="Terminée">Terminée</option></select></div>
      <div id="seancesContainer" class="seances-grid"></div>
    </main>
  </div>
</div>

<div id="seanceModal" class="modal-overlay">
  <div class="modal-box">
    <h3 id="modalTitle">Nouvelle séance</h3>
    <form method="POST" action="seance.php">
      <input type="hidden" name="id_cours" id="form_id_cours">
      <input type="hidden" name="id_seance" id="form_id_seance">
      <label>Libellé de la séance</label>
      <input type="text" name="libelle" id="form_libelle" placeholder="Ex: Séance du matin" required>
      <label>Matière</label>
      <select name="id_matiere" id="form_matiere" required>
        <?php foreach ($matieresList as $m): ?>
          <option value="<?= $m['id_matiere'] ?>"><?= htmlspecialchars($m['nom_matiere']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Enseignant</label>
      <select name="matricule" id="form_matricule" required>
        <?php foreach ($enseignantsList as $e): ?>
          <option value="<?= $e['Matricule'] ?>"><?= htmlspecialchars($e['prenom'] . ' ' . $e['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="modal-row">
        <div><label>Date</label><input type="date" name="date_cours" id="form_date"></div>
        <div><label>Heure de début</label><input type="time" name="heure_debut" id="form_heure"></div>
      </div>
      <div class="modal-row">
        <div><label>Durée (min)</label><input type="number" name="duree" id="form_duree" min="0" value="60" required></div>
        <div><label>Salle</label><input type="text" name="salle" id="form_salle" placeholder="Ex: Salle A12"></div>
      </div>
      <label>Statut</label>
      <select name="statut" id="form_statut">
        <option value="Planifiée">Planifiée</option>
        <option value="En cours">En cours</option>
        <option value="Terminée">Terminée</option>
      </select>
      <label>Progression (%)</label>
      <input type="number" name="progression" id="form_progression" min="0" max="100" value="0">
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
        <button type="submit" class="btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
  const seances = <?= json_encode($seancesData, JSON_UNESCAPED_UNICODE) ?>;
  function renderSeances(filter = "") {
    const container = document.getElementById("seancesContainer");
    const f = filter.toLowerCase();
    const statutFilter = document.getElementById("statutFilter").value;
    const filtered = seances.filter(s =>
      (s.matiere.toLowerCase().includes(f) || s.enseignant.toLowerCase().includes(f) || s.salle.toLowerCase().includes(f)) &&
      (statutFilter === "" || s.statut === statutFilter)
    );
    if (filtered.length === 0) {
      container.innerHTML = '<div style="color:var(--text-muted);font-size:13px;">Aucune séance trouvée.</div>';
      return;
    }
    container.innerHTML = filtered.map(s => `
      <div class="seance-card">
        <div class="seance-top"><div><div class="seance-matiere">${s.matiere}</div><div class="seance-time">📅 ${s.date} — ${s.horaire}</div></div><span class="badge ${s.statutClass}">${s.statut}</span></div>
        <div class="seance-room">📍 ${s.salle}</div>
        <div class="seance-teacher"><div class="avatar-sm">${s.initials}</div><span>${s.enseignant}</span></div>
        <div class="seance-progress"><div style="display:flex;justify-content:space-between;font-size:11px;"><span>Progression</span><span>${s.progression}%</span></div><div class="prog-bar"><div class="prog-fill" style="width:${s.progression}%; background:${s.couleur};"></div></div></div>
        <div class="card-actions"><button class="icon-btn" onclick="openEditModal(${s.id})">✏️</button><button class="icon-btn" onclick="deleteSeance(${s.id})">🗑️</button></div>
      </div>
    `).join("");
  }
  function openAddModal() {
    document.getElementById("modalTitle").innerText = "Nouvelle séance";
    document.getElementById("form_id_cours").value = "";
    document.getElementById("form_id_seance").value = "";
    document.getElementById("form_libelle").value = "";
    document.getElementById("form_matiere").value = "";
    document.getElementById("form_matricule").value = "";
    document.getElementById("form_date").value = "";
    document.getElementById("form_heure").value = "";
    document.getElementById("form_duree").value = 60;
    document.getElementById("form_salle").value = "";
    document.getElementById("form_statut").value = "Planifiée";
    document.getElementById("form_progression").value = 0;
    document.getElementById("seanceModal").style.display = "flex";
  }
  function openEditModal(id) {
    const s = seances.find(x => x.id === id);
    if (!s) return;
    document.getElementById("modalTitle").innerText = "Modifier la séance";
    document.getElementById("form_id_cours").value = s.id;
    document.getElementById("form_id_seance").value = s.id_seance;
    document.getElementById("form_libelle").value = s.libelle;
    document.getElementById("form_matiere").value = s.id_matiere;
    document.getElementById("form_matricule").value = s.matricule;
    document.getElementById("form_date").value = s.date_cours || "";
    document.getElementById("form_heure").value = s.heure_debut || "";
    document.getElementById("form_duree").value = s.duree;
    document.getElementById("form_salle").value = s.salle === '—' ? '' : s.salle;
    document.getElementById("form_statut").value = s.statut;
    document.getElementById("form_progression").value = s.progression;
    document.getElementById("seanceModal").style.display = "flex";
  }
  function closeModal() { document.getElementById("seanceModal").style.display = "none"; }
  function deleteSeance(id) { if (confirm("Supprimer cette séance ?")) window.location.href = "seance.php?delete=" + id; }
  document.getElementById("searchInput").addEventListener("input", e => renderSeances(e.target.value));
  document.getElementById("statutFilter").addEventListener("change", () => renderSeances(document.getElementById("searchInput").value));
  function updateDate() { document.getElementById("currentDate").innerText = new Date().toLocaleDateString('fr-FR', { weekday:'long', year:'numeric', month:'long', day:'numeric' }).replace(/^\w/, c => c.toUpperCase()); }
  function logout() { window.location.href = 'login.php'; }
  updateDate(); renderSeances();
</script>
</body>
</html>
