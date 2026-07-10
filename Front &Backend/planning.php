<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit();
}

require_once(__DIR__ . "/../config/config.php");

// --- Calcul de la semaine affichée (lundi -> vendredi) ---
$offset = isset($_GET['semaine']) ? (int)$_GET['semaine'] : 0;
$today = new DateTime();
$monday = (clone $today)->modify('monday this week')->modify($offset . ' week');
$friday = (clone $monday)->modify('+4 days');

function strftime_fr($date) {
    $mois = ["janvier","février","mars","avril","mai","juin","juillet","août","septembre","octobre","novembre","décembre"];
    return $mois[(int)$date->format('n') - 1] . " " . $date->format('Y');
}
$weekRangeLabel = "Semaine du " . $monday->format('d') . " au " . $friday->format('d') . " " . strftime_fr($friday);

// --- Récupération des cours de la semaine ---
$sql = "SELECT c.date_cours, c.heure_debut, c.salle, m.nom_matiere, e.nom AS ens_nom, e.prenom AS ens_prenom
        FROM cours c
        JOIN matieres m ON c.id_matiere = m.id_matiere
        JOIN enseignants e ON c.Matricule = e.Matricule
        WHERE c.date_cours BETWEEN ? AND ?
        ORDER BY c.heure_debut ASC";
$stmt = $conn->prepare($sql);
$m = $monday->format('Y-m-d');
$f = $friday->format('Y-m-d');
$stmt->bind_param("ss", $m, $f);
$stmt->execute();
$result = $stmt->get_result();

$colorClasses = ['math', 'phys', 'hist', 'lang', 'info'];
function colorForMatiere($nom) {
    global $colorClasses;
    return $colorClasses[crc32($nom) % count($colorClasses)];
}

$planningData = [];
$timeSlotsSet = [];
while ($row = $result->fetch_assoc()) {
    $d = new DateTime($row['date_cours']);
    $dayIndex = ((int)$d->format('N')) - 1; // 0=Lundi ... 4=Vendredi
    if ($dayIndex < 0 || $dayIndex > 4) continue;
    $heure = $row['heure_debut'] ? (new DateTime($row['heure_debut']))->format('H\hi') : '—';
    $timeSlotsSet[$heure] = true;
    $planningData[] = [
        "day" => $dayIndex,
        "time" => $heure,
        "matiere" => $row['nom_matiere'],
        "salle" => $row['salle'] ?: '—',
        "enseignant" => $row['ens_prenom'] . ' ' . $row['ens_nom'],
        "class" => colorForMatiere($row['nom_matiere'])
    ];
}
$timeSlots = array_keys($timeSlotsSet);
sort($timeSlots);
if (empty($timeSlots)) $timeSlots = ["08h00", "10h15", "13h30"];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>EduClass — Planning</title>
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
  .week-nav { display:flex; gap:12px; }
  .week-btn { background:rgba(255,255,255,.05); border:1px solid var(--border); border-radius:8px; padding:6px 12px; font-size:12px; cursor:pointer; color:var(--text-muted); }
  .week-btn:hover { background:rgba(0,201,167,.1); color:var(--teal); }
  .planning-card { background:var(--card-bg); border:1px solid var(--border); border-radius:20px; padding:20px; overflow-x:auto; }
  .planning-grid { display:grid; grid-template-columns:60px repeat(5, minmax(140px,1fr)); gap:2px; min-width:700px; }
  .plan-header { background:rgba(255,255,255,.04); padding:12px 8px; text-align:center; font-size:12px; font-weight:600; text-transform:uppercase; color:var(--text-muted); border-radius:8px 8px 0 0; }
  .plan-time { padding:12px 6px; text-align:right; font-size:11px; color:var(--text-muted); display:flex; align-items:center; justify-content:flex-end; }
  .plan-cell { background:rgba(255,255,255,.02); border-radius:8px; min-height:70px; border:1px solid var(--border); padding:6px; }
  .plan-event { border-radius:8px; padding:8px 6px; font-size:11px; font-weight:500; display:flex; flex-direction:column; gap:4px; }
  .plan-event strong { font-size:12px; }
  .plan-event.math { background:rgba(0,201,167,.12); color:var(--teal); border-left:3px solid var(--teal); }
  .plan-event.phys { background:rgba(76,201,240,.12); color:var(--sky); border-left:3px solid var(--sky); }
  .plan-event.hist { background:rgba(255,184,48,.12); color:var(--amber); border-left:3px solid var(--amber); }
  .plan-event.lang { background:rgba(255,107,107,.12); color:var(--rose); border-left:3px solid var(--rose); }
  .plan-event.info { background:rgba(168,85,247,.12); color:#c084fc; border-left:3px solid #c084fc; }
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
      <div class="nav-item"><a href="seance.php"><span class="nav-icon">📅</span> Séances</a></div>
      <div class="sidebar-section">Gestion</div><div class="nav-item"><a href="note.php"><span class="nav-icon">📝</span> Notes</a></div>
      <div class="nav-item"><a href="evaluation.php"><span class="nav-icon">📋</span> Évaluations</a></div>
      <div class="nav-item active"><a href="planning.php"><span class="nav-icon">🗓️</span> Planning</a></div>
      <div class="nav-item"><a href="abscence.php"><span class="nav-icon">⚠️</span> Absences <span class="nav-badge">5</span></a></div>
      <div class="sidebar-bottom"><button class="logout-btn" onclick="logout()">🚪 Déconnexion</button></div>
    </nav>
    <main class="main">
      <div class="page-header"><div><div class="page-title">Planning hebdomadaire</div><div class="page-sub" id="weekRange"><?= $weekRangeLabel ?></div></div><div class="week-nav"><a href="planning.php?semaine=<?= $offset - 1 ?>" class="week-btn" style="text-decoration:none;">← Semaine précédente</a><a href="planning.php?semaine=<?= $offset + 1 ?>" class="week-btn" style="text-decoration:none;">Semaine suivante →</a></div></div>
      <div class="planning-card"><div class="planning-grid" id="planningGrid"></div></div>
    </main>
  </div>
</div>
<script>
  const dayNames = ["Lundi", "Mardi", "Mercredi", "Jeudi", "Vendredi"];
  const timeSlots = <?= json_encode($timeSlots) ?>;
  const planningData = <?= json_encode($planningData, JSON_UNESCAPED_UNICODE) ?>;
  function renderPlanning() {
    let html = '<div></div>'; for(let i=0;i<dayNames.length;i++) html += `<div class="plan-header">${dayNames[i]}</div>`;
    for(let time of timeSlots) {
      html += `<div class="plan-time">${time}</div>`;
      for(let d=0; d<dayNames.length; d++) {
        const events = planningData.filter(ev => ev.day === d && ev.time === time);
        let cell = '<div class="plan-cell">';
        events.forEach(ev => { cell += `<div class="plan-event ${ev.class}"><strong>${ev.matiere}</strong><small>${ev.salle} · ${ev.enseignant}</small></div>`; });
        cell += '</div>';
        html += cell;
      }
    }
    document.getElementById("planningGrid").innerHTML = html;
  }
  function updateDate() { document.getElementById("currentDate").innerText = new Date().toLocaleDateString('fr-FR', { weekday:'long', year:'numeric', month:'long', day:'numeric' }).replace(/^\w/, c => c.toUpperCase()); }
  function logout() { window.location.href = 'login.php'; }
  updateDate(); renderPlanning();
</script>
</body>
</html>