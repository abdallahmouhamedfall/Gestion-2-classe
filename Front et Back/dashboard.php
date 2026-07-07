<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

require_once(__DIR__ . "/../config/config.php");

// --- Statistiques générales ---
$nbEtudiants = $conn->query("SELECT COUNT(*) AS n FROM etudiants")->fetch_assoc()['n'];
$nbEnseignants = $conn->query("SELECT COUNT(*) AS n FROM enseignants")->fetch_assoc()['n'];

$rowMoy = $conn->query("SELECT AVG(note) AS moyenne FROM notes")->fetch_assoc();
$moyenneGenerale = $rowMoy['moyenne'] !== null ? round((float)$rowMoy['moyenne'], 1) : null;

$absencesSemaine = $conn->query(
    "SELECT COUNT(*) AS n FROM absences WHERE YEARWEEK(date_absence, 1) = YEARWEEK(CURDATE(), 1)"
)->fetch_assoc()['n'];

// --- Répartition des moyennes par matière ---
$matieresChart = [];
$resMat = $conn->query(
    "SELECT m.nom_matiere, AVG(n.note) AS moyenne
     FROM matieres m LEFT JOIN notes n ON n.id_matiere = m.id_matiere
     GROUP BY m.id_matiere, m.nom_matiere"
);
while ($r = $resMat->fetch_assoc()) {
    $moy = $r['moyenne'] !== null ? round((float)$r['moyenne'], 1) : 0;
    $matieresChart[] = ["label" => $r['nom_matiere'], "moyenne" => $moy, "height" => min(100, round(($moy / 20) * 100))];
}

// --- Taux de présence (approximatif : absences vs séances programmées x étudiants) ---
$nbCours = $conn->query("SELECT COUNT(*) AS n FROM cours")->fetch_assoc()['n'];
$totalAbsences = $conn->query("SELECT COUNT(*) AS n FROM absences")->fetch_assoc()['n'];
$capaciteTotale = $nbCours * max($nbEtudiants, 1);
$tauxAbsence = $capaciteTotale > 0 ? round(($totalAbsences / $capaciteTotale) * 100) : 0;
$tauxPresence = 100 - $tauxAbsence;

// --- Prochains cours (pas de date/heure/salle dans le schéma actuel) ---
$prochainesSeances = [];
$resSeances = $conn->query(
    "SELECT m.nom_matiere, e.nom AS ens_nom, e.prenom AS ens_prenom, c.duree
     FROM cours c
     JOIN matieres m ON c.id_matiere = m.id_matiere
     JOIN enseignants e ON c.Matricule = e.Matricule
     ORDER BY c.id_cours DESC LIMIT 3"
);
while ($r = $resSeances->fetch_assoc()) {
    $initials = strtoupper(mb_substr($r['ens_prenom'], 0, 1) . mb_substr($r['ens_nom'], 0, 1));
    $prochainesSeances[] = [
        "matiere" => $r['nom_matiere'],
        "enseignant" => $r['ens_prenom'] . ' ' . $r['ens_nom'],
        "initials" => $initials,
        "duree" => $r['duree']
    ];
}
?>
<?php
require_once("../config/auth.php");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduClass — Tableau de bord</title>
<!--<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">-->
<link rel="stylesheet" href="dashboard.css">
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
  .topbar {
    height:64px; background:var(--card-bg); border-bottom:1px solid var(--border);
    display:flex; align-items:center; padding:0 28px; gap:16px; position:sticky; top:0; z-index:100;
  }
  .topbar-brand { font-family:'Playfair Display',serif; font-size:18px; display:flex; align-items:center; gap:10px; }
  .topbar-icon { width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--teal),var(--sky));display:flex;align-items:center;justify-content:center;font-size:15px;font-weight:700;color:var(--navy); }
  .topbar-right { margin-left:auto; display:flex; align-items:center; gap:16px; }
  .topbar-date { font-size:12px; color:var(--text-muted); }
  .notif-btn { width:36px;height:36px;border-radius:10px;background:rgba(255,255,255,.05);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;position:relative; }
  .notif-dot { width:7px;height:7px;border-radius:50%;background:var(--rose);position:absolute;top:7px;right:7px; }
  .avatar { width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--amber),var(--rose));display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:600;color:white;cursor:pointer; }
  .app-body { display:flex; flex:1; overflow:hidden; }
  .sidebar {
    width:220px; background:var(--card-bg); border-right:1px solid var(--border);
    padding:24px 0; display:flex; flex-direction:column; gap:4px; flex-shrink:0;
  }
  .sidebar-section { padding:0 16px 8px; font-size:10px; font-weight:600; color:var(--text-muted); text-transform:uppercase; margin-top:12px; }
  .nav-item {
    margin:0 10px; padding:11px 14px; border-radius:10px; display:flex; align-items:center; gap:11px;
    font-size:13.5px; font-weight:500; color:var(--text-muted); position:relative;
  }
  .nav-item a { text-decoration:none; color:inherit; display:flex; align-items:center; gap:11px; width:100%; }
  .nav-item:hover { background:rgba(255,255,255,.05); color:var(--text-main); }
  .nav-item.active { background:rgba(0,201,167,.1); color:var(--teal); }
  .nav-item.active::before { content:''; position:absolute; left:-10px; top:50%; transform:translateY(-50%); width:3px; height:22px; background:var(--teal); border-radius:0 3px 3px 0; }
  .nav-icon { font-size:16px; width:20px; text-align:center; }
  .nav-badge { margin-left:auto; padding:2px 7px; border-radius:20px; background:rgba(0,201,167,.15); color:var(--teal); font-size:10px; }
  .nav-badge.rose { background:rgba(255,107,107,.15); color:var(--rose); }
  .sidebar-bottom { margin-top:auto; padding:16px 10px 0; border-top:1px solid var(--border); }
  .logout-btn {
    width:100%; padding:11px 14px; border-radius:10px; background:transparent; border:none;
    color:var(--text-muted); font-size:13px; cursor:pointer; display:flex; align-items:center; gap:10px;
  }
  .logout-btn:hover { background:rgba(255,107,107,.08); color:var(--rose); }
  .main { flex:1; overflow-y:auto; padding:28px 32px; }
  .page-header { margin-bottom:28px; }
  .page-title { font-family:'Playfair Display',serif; font-size:28px; font-weight:700; }
  .page-sub { font-size:13px; color:var(--text-muted); margin-top:4px; }
  .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:18px; margin-bottom:28px; }
  .stat-card { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; padding:22px 20px; transition:transform .2s; }
  .stat-card:hover { transform:translateY(-2px); }
  .stat-icon { font-size:22px; margin-bottom:14px; }
  .stat-val { font-family:'Playfair Display',serif; font-size:30px; font-weight:700; }
  .stat-label { font-size:12px; color:var(--text-muted); margin-top:3px; }
  .stat-trend { font-size:11px; margin-top:8px; display:flex; align-items:center; gap:4px; }
  .trend-up { color:var(--teal); } .trend-down { color:var(--rose); }
  .stat-bar { height:3px; background:rgba(255,255,255,.08); border-radius:2px; margin-top:12px; }
  .stat-bar-fill { height:100%; border-radius:2px; }
  .charts-row { display:grid; grid-template-columns:1.6fr 1fr; gap:18px; margin-bottom:24px; }
  .chart-card { background:var(--card-bg); border:1px solid var(--border); border-radius:16px; padding:22px; }
  .chart-card h4 { font-size:14px; font-weight:600; margin-bottom:18px; }
  .bar-chart { display:flex; align-items:flex-end; gap:10px; height:100px; }
  .bar-wrap { flex:1; display:flex; flex-direction:column; align-items:center; gap:6px; }
  .bar { width:100%; border-radius:6px 6px 0 0; }
  .bar-label { font-size:10px; color:var(--text-muted); }
  .donut-wrap { display:flex; align-items:center; gap:18px; }
  .donut { position:relative; width:90px; height:90px; }
  .donut svg { transform:rotate(-90deg); }
  .donut-val { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-family:'Playfair Display',serif; font-size:18px; font-weight:700; }
  .donut-legend { display:flex; flex-direction:column; gap:8px; }
  .legend-item { display:flex; align-items:center; gap:8px; font-size:12px; }
  .legend-dot { width:8px; height:8px; border-radius:50%; }
  .section-hdr { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
  .section-title { font-size:15px; font-weight:600; }
  .btn-sm { padding:8px 16px; border-radius:8px; border:none; cursor:pointer; font-size:12px; font-weight:600; background:rgba(255,255,255,.06); color:var(--text-muted); border:1px solid var(--border); }
  .btn-sm:hover { background:rgba(255,255,255,.1); color:var(--text-main); }
  .seances-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
  .seance-card { background:var(--card-bg); border:1px solid var(--border); border-radius:14px; padding:18px; transition:transform .2s; cursor:pointer; }
  .seance-card:hover { transform:translateY(-2px); border-color:rgba(0,201,167,.2); }
  .seance-top { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; }
  .seance-matiere { font-weight:600; font-size:14px; }
  .seance-time { font-size:12px; color:var(--text-muted); margin-top:3px; }
  .seance-room { margin-top:8px; font-size:11px; color:var(--sky); background:rgba(76,201,240,.08); padding:3px 8px; border-radius:6px; display:inline-block; }
  .seance-teacher { display:flex; align-items:center; gap:8px; margin-top:12px; font-size:12px; }
  .avatar-sm { width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--teal),var(--sky));display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:var(--navy); }
  .avatar-sm.amber { background:linear-gradient(135deg,var(--amber),#FF8C42); }
  .avatar-sm.rose { background:linear-gradient(135deg,var(--rose),#c0392b); }
  .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:600; }
  .badge-green { background:rgba(0,201,167,.12); color:var(--teal); }
  .badge-sky { background:rgba(76,201,240,.12); color:var(--sky); }
  .badge-amber { background:rgba(255,184,48,.12); color:var(--amber); }
  .seance-progress { margin-top:14px; }
  .prog-bar { height:4px; background:rgba(255,255,255,.08); border-radius:2px; margin-top:4px; }
  .prog-fill { height:100%; border-radius:2px; }
  @media(max-width:1100px){ .stats-grid{grid-template-columns:repeat(2,1fr);} .seances-grid{grid-template-columns:1fr;} .charts-row{grid-template-columns:1fr;} }
</style>
</head>
<body>
<div id="dashboard">
  <div class="topbar">
    <div class="topbar-brand"><div class="topbar-icon">E</div> EduClass</div>
    <div class="topbar-right"><div class="topbar-date" id="currentDate"></div><div class="notif-btn">🔔<div class="notif-dot"></div></div><div class="avatar" onclick="logout()">AD</div></div>
  </div>
  <div class="app-body">
    <nav class="sidebar">
      <div class="sidebar-section">Principal</div>
      <div class="nav-item active"><a href="dashboard.php"><span class="nav-icon">📊</span> Tableau de bord</a></div>
      <div class="sidebar-section">Académique</div>
      <div class="nav-item"><a href="etudiant.php"><span class="nav-icon">🎓</span> Étudiants <span class="nav-badge"><?= $nbEtudiants ?></span></a></div>
      <div class="nav-item"><a href="enseignant.php"><span class="nav-icon">👨‍🏫</span> Enseignants <span class="nav-badge"><?= $nbEnseignants ?></span></a></div>
      <div class="nav-item"><a href="seance.php"><span class="nav-icon">📅</span> Séances</a></div>
      <div class="sidebar-section">Gestion</div>
      <div class="nav-item"><a href="note.php"><span class="nav-icon">📝</span> Notes</a></div>
      <div class="nav-item"><a href="evaluation.php"><span class="nav-icon">📋</span> Évaluations</a></div>
      <div class="nav-item"><a href="planning.php"><span class="nav-icon">🗓️</span> Planning</a></div>
      <div class="nav-item"><a href="abscence.php"><span class="nav-icon">⚠️</span> Absences <span class="nav-badge rose"><?= $absencesSemaine ?></span></a></div>
      <div class="sidebar-bottom"><button class="logout-btn" onclick="logout()">🚪 Déconnexion</button></div>
    </nav>
    <main class="main">
      <div class="page-header"><div class="page-title">Tableau de bord</div><div class="page-sub">Vue d'ensemble de la classe — Promotion 2025/2026</div></div>
      <div class="stats-grid">
        <div class="stat-card" style="--accent-color:var(--teal)"><div class="stat-icon">🎓</div><div class="stat-val"><?= $nbEtudiants ?></div><div class="stat-label">Étudiants inscrits</div><div class="stat-bar"><div class="stat-bar-fill" style="width:100%;background:var(--teal)"></div></div></div>
        <div class="stat-card" style="--accent-color:var(--sky)"><div class="stat-icon">👨‍🏫</div><div class="stat-val"><?= $nbEnseignants ?></div><div class="stat-label">Enseignants actifs</div><div class="stat-bar"><div class="stat-bar-fill" style="width:100%;background:var(--sky)"></div></div></div>
        <div class="stat-card" style="--accent-color:var(--amber)"><div class="stat-icon">📊</div><div class="stat-val"><?= $moyenneGenerale !== null ? $moyenneGenerale : '—' ?></div><div class="stat-label">Moyenne générale /20</div><div class="stat-bar"><div class="stat-bar-fill" style="width:<?= $moyenneGenerale !== null ? round(($moyenneGenerale/20)*100) : 0 ?>%;background:var(--amber)"></div></div></div>
        <div class="stat-card" style="--accent-color:var(--rose)"><div class="stat-icon">⚠️</div><div class="stat-val"><?= $absencesSemaine ?></div><div class="stat-label">Absences cette semaine</div><div class="stat-bar"><div class="stat-bar-fill" style="width:<?= min(100, $absencesSemaine * 10) ?>%;background:var(--rose)"></div></div></div>
      </div>
      <div class="charts-row">
        <div class="chart-card"><h4>Répartition des moyennes par matière</h4><div class="bar-chart">
        <?php if (empty($matieresChart)): ?>
          <div style="color:var(--text-muted);font-size:12px;">Aucune matière enregistrée.</div>
        <?php else: foreach ($matieresChart as $mc): ?>
          <div class="bar-wrap"><div class="bar" style="height:<?= $mc['height'] ?>%;background:linear-gradient(to top,var(--teal),rgba(0,201,167,.4))"></div><div class="bar-label"><?= htmlspecialchars($mc['label']) ?></div></div>
        <?php endforeach; endif; ?>
        </div></div>
        <div class="chart-card"><h4>Taux de présence</h4><div class="donut-wrap"><div class="donut"><svg viewBox="0 0 80 80" width="90" height="90"><circle cx="40" cy="40" r="30" fill="none" stroke="rgba(255,255,255,.07)" stroke-width="10"/><circle cx="40" cy="40" r="30" fill="none" stroke="var(--teal)" stroke-width="10" stroke-dasharray="<?= round(($tauxPresence/100)*188) ?> 188" stroke-linecap="round"/><circle cx="40" cy="40" r="30" fill="none" stroke="var(--rose)" stroke-width="10" stroke-dasharray="<?= round(($tauxAbsence/100)*188) ?> 188" stroke-dashoffset="-<?= round(($tauxPresence/100)*188) ?>" stroke-linecap="round"/></svg><div class="donut-val"><?= $tauxPresence ?>%</div></div><div class="donut-legend"><div class="legend-item"><div class="legend-dot" style="background:var(--teal)"></div> Présents <?= $tauxPresence ?>%</div><div class="legend-item"><div class="legend-dot" style="background:var(--rose)"></div> Absents <?= $tauxAbsence ?>%</div></div></div></div>
      </div>
      <div class="section-hdr"><div class="section-title">Derniers cours enregistrés</div><a href="seance.php" class="btn-sm" style="text-decoration:none;">Voir tout →</a></div>
      <div class="seances-grid">
        <?php if (empty($prochainesSeances)): ?>
          <div style="color:var(--text-muted);font-size:13px;">Aucun cours programmé pour le moment.</div>
        <?php else: foreach ($prochainesSeances as $s): ?>
          <div class="seance-card">
            <div class="seance-top"><div><div class="seance-matiere"><?= htmlspecialchars($s['matiere']) ?></div><div class="seance-time"><?= (int)$s['duree'] ?> min</div></div></div>
            <div class="seance-teacher"><div class="avatar-sm"><?= htmlspecialchars($s['initials']) ?></div> <?= htmlspecialchars($s['enseignant']) ?></div>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </main>
  </div>
</div>
<script>
  function updateDate() {
     const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };    const today = new Date().toLocaleDateString('fr-FR', options);    document.getElementById('currentDate').innerText = today.charAt(0).toUpperCase() + today.slice(1);  }  function logout() { window.location.href = 'login.php'; }  updateDate();
     </script>
   </html>