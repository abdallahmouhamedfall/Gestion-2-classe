

CREATE DATABASE educlass;
USE educlass;

 Table : matieres
CREATE TABLE `matieres` (
  `id_matiere` int(11) NOT NULL AUTO_INCREMENT,
  `nom_matiere` varchar(50) NOT NULL,
  `heure_totale` int(11) NOT NULL,
  PRIMARY KEY (`id_matiere`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


Table : seances
CREATE TABLE `seances` (
  `id_seance` int(11) NOT NULL AUTO_INCREMENT,
  `libelle` varchar(50) NOT NULL,
  PRIMARY KEY (`id_seance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Table : enseignants
CREATE TABLE `enseignants` (
  `Matricule` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adresse` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `statut` enum('Actif','En congé') NOT NULL DEFAULT 'Actif',
  PRIMARY KEY (`Matricule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


Table : etudiants
CREATE TABLE `etudiants` (
  `id_etudiant` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `adresse` varchar(100) DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id_etudiant`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Table : evaluations
CREATE TABLE `evaluations` (
  `id_evaluation` int(11) NOT NULL AUTO_INCREMENT,
  `libelle` varchar(50) NOT NULL,
  `id_matiere` int(11) DEFAULT NULL,
  `date_eval` date DEFAULT NULL,
  `duree_min` int(11) DEFAULT NULL,
  `type` enum('Ecrit','Pratique','Oral') NOT NULL DEFAULT 'Ecrit',
  `statut` enum('A venir','En cours','Corrigée') NOT NULL DEFAULT 'A venir',
  PRIMARY KEY (`id_evaluation`),
  KEY `fk_eval_matiere` (`id_matiere`),
  CONSTRAINT `fk_eval_matiere` FOREIGN KEY (`id_matiere`) REFERENCES `matieres` (`id_matiere`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Table : cours
CREATE TABLE `cours` (
  `id_cours` int(11) NOT NULL AUTO_INCREMENT,
  `id_seance` int(11) NOT NULL,
  `id_matiere` int(11) NOT NULL,
  `Matricule` int(11) NOT NULL,
  `duree` int(11) NOT NULL,
  `date_cours` date DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `salle` varchar(20) DEFAULT NULL,
  `statut` enum('Planifiée','En cours','Terminée') NOT NULL DEFAULT 'Planifiée',
  `progression` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_cours`),
  KEY `fk_cours_seances` (`id_seance`),
  KEY `fk_cours_matieres` (`id_matiere`),
  KEY `fk_cours_enseignants` (`Matricule`),
  CONSTRAINT `fk_cours_enseignants` FOREIGN KEY (`Matricule`) REFERENCES `enseignants` (`Matricule`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cours_matieres` FOREIGN KEY (`id_matiere`) REFERENCES `matieres` (`id_matiere`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cours_seances` FOREIGN KEY (`id_seance`) REFERENCES `seances` (`id_seance`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Table : absences
CREATE TABLE `absences` (
  `id_absence` int(11) NOT NULL AUTO_INCREMENT,
  `id_etudiant` int(11) NOT NULL,
  `id_seance` int(11) NOT NULL,
  `date_absence` date NOT NULL,
  `heure_absence` time DEFAULT NULL,
  `type` enum('Absence','Retard') NOT NULL DEFAULT 'Absence',
  `motif` varchar(255) DEFAULT NULL,
  `justifie` enum('Oui','Non','Partiel') NOT NULL DEFAULT 'Non',
  PRIMARY KEY (`id_absence`),
  KEY `fk_absences_etudiants` (`id_etudiant`),
  KEY `fk_absences_seances` (`id_seance`),
  CONSTRAINT `fk_absences_etudiants` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiants` (`id_etudiant`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_absences_seances` FOREIGN KEY (`id_seance`) REFERENCES `seances` (`id_seance`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Table : notes
CREATE TABLE `notes` (
  `id_note` int(11) NOT NULL AUTO_INCREMENT,
  `id_etudiant` int(11) NOT NULL,
  `id_matiere` int(11) NOT NULL,
  `id_evaluation` int(11) NOT NULL,
  `note` decimal(5,2) NOT NULL,
  PRIMARY KEY (`id_note`),
  KEY `fk_notes_etudiants` (`id_etudiant`),
  KEY `fk_notes_matieres` (`id_matiere`),
  KEY `fk_notes_evaluations` (`id_evaluation`),
  CONSTRAINT `fk_notes_etudiants` FOREIGN KEY (`id_etudiant`) REFERENCES `etudiants` (`id_etudiant`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notes_evaluations` FOREIGN KEY (`id_evaluation`) REFERENCES `evaluations` (`id_evaluation`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notes_matieres` FOREIGN KEY (`id_matiere`) REFERENCES `matieres` (`id_matiere`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


 Table : utilisateurs
CREATE TABLE `utilisateurs` (
  `id_utilisateur` int(11) NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) NOT NULL,
  `prenom` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `mot_de_passe` varchar(255) NOT NULL,
  `role` enum('admin','professeur') NOT NULL,
  PRIMARY KEY (`id_utilisateur`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

 =========================================================
 EDUCLASS - MIGRATIONS
 Modifications appliquées sur la base au fil du projet,
 dans l'ordre chronologique.

   1. Ajout de la colonne email sur enseignants (APPLIQUÉE)

ALTER TABLE `enseignants`
  ADD COLUMN `email` VARCHAR(100) NULL AFTER `prenom`;


 2. Ajout de la colonne email sur etudiants (APPLIQUÉE)

ALTER TABLE `etudiants`
  ADD COLUMN `email` VARCHAR(100) NULL AFTER `prenom`;


   3. Liaison utilisateurs <-> enseignants (EN ATTENTE)
   Nécessaire pour le multi-utilisateurs (profs connectés
 avec accès restreint à leurs propres cours/notes/absences).

ALTER TABLE `utilisateurs`
  ADD COLUMN `matricule_enseignant` INT NULL AFTER `role`,
  ADD CONSTRAINT `fk_util_enseignant`
    FOREIGN KEY (`matricule_enseignant`)
    REFERENCES `enseignants` (`Matricule`)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- =========================================================
 EDUCLASS - REQUÊTES SIMPLES
 Une seule table à la fois : SELECT, INSERT, UPDATE, DELETE


          1.ETUDIANTS

- Lister tous les étudiants
SELECT * FROM etudiants;

- Rechercher un étudiant par nom ou prénom
SELECT * FROM etudiants
WHERE nom LIKE '%Ndiaye%' OR prenom LIKE '%Ndiaye%';

- Ajouter un étudiant
INSERT INTO etudiants (nom, prenom, email, adresse, telephone)
VALUES ('Diop', 'Awa', 'awa.diop@mail.com', 'Dakar', '770000020');

- Modifier un étudiant
UPDATE etudiants
SET telephone = '770000099', adresse = 'Ngor'
WHERE id_etudiant = 1;

- Supprimer un étudiant
DELETE FROM etudiants WHERE id_etudiant = 6;


        2.ENSEIGNANTS 

- Lister tous les enseignants
SELECT * FROM enseignants;

- Lister les enseignants actifs uniquement
SELECT * FROM enseignants WHERE statut = 'Actif';

- Ajouter un enseignant
INSERT INTO enseignants (nom, prenom, email, adresse, telephone, statut)
VALUES ('Sow', 'Ibrahima', 'ibrahima.sow@mail.com', 'Dakar', '770000030', 'Actif');

- Modifier le statut d'un enseignant
UPDATE enseignants SET statut = 'En congé' WHERE Matricule = 3;

- Supprimer un enseignant
DELETE FROM enseignants WHERE Matricule = 2;


          3.MATIERES 

- Lister toutes les matières
SELECT * FROM matieres;

- Ajouter une matière
INSERT INTO matieres (nom_matiere, heure_totale)
VALUES ('Histoire-Géo', 40);

- Modifier le volume horaire d'une matière
UPDATE matieres SET heure_totale = 50 WHERE id_matiere = 1;


        4.SEANCES 

- Lister toutes les séances
SELECT * FROM seances;

- Ajouter une séance
INSERT INTO seances (libelle) VALUES ('Rattrapage');


         5.COURS 

- Lister tous les cours
SELECT * FROM cours;

- Lister les cours d'aujourd'hui
SELECT * FROM cours WHERE date_cours = CURDATE();

- Lister les cours planifiés
SELECT * FROM cours WHERE statut = 'Planifiée';

- Ajouter un cours
INSERT INTO cours (id_seance, id_matiere, Matricule, duree, date_cours, heure_debut, salle, statut, progression)
VALUES (1, 1, 1, 60, '2026-07-15', '09:00:00', 'Salle B04', 'Planifiée', 0);

- Modifier la progression d'un cours
UPDATE cours SET progression = 100, statut = 'Terminée' WHERE id_cours = 3;

- Supprimer un cours
DELETE FROM cours WHERE id_cours = 8;


           6.EVALUATIONS 

- Lister toutes les évaluations
SELECT * FROM evaluations;

- Lister les évaluations à venir
SELECT * FROM evaluations WHERE statut = 'A venir';

- Ajouter une évaluation
INSERT INTO evaluations (libelle, id_matiere, date_eval, duree_min, type, statut)
VALUES ('Contrôle Anglais', 5, '2026-07-20', 60, 'Ecrit', 'A venir');

- Modifier le statut d'une évaluation
UPDATE evaluations SET statut = 'Corrigée' WHERE id_evaluation = 4;


          7.NOTES 

- Lister toutes les notes
SELECT * FROM notes;

- Ajouter une note
INSERT INTO notes (id_etudiant, id_matiere, id_evaluation, note)
VALUES (1, 1, 2, 14.5);

- Modifier une note
UPDATE notes SET note = 16.0 WHERE id_note = 1;

- Supprimer une note
DELETE FROM notes WHERE id_note = 1;


        8.ABSENCES 

- Lister toutes les absences
SELECT * FROM absences;

- Lister les absences non justifiées
SELECT * FROM absences WHERE justifie = 'Non';

- Ajouter une absence
INSERT INTO absences (id_etudiant, id_seance, date_absence, heure_absence, type, motif, justifie)
VALUES (2, 4, '2026-07-10', '08:00:00', 'Absence', '', 'Non');

- Justifier une absence
UPDATE absences SET justifie = 'Oui', motif = 'Certificat médical' WHERE id_absence = 3;


          9.UTILISATEURS 

- Lister tous les utilisateurs
SELECT id_utilisateur, nom, prenom, email, role FROM utilisateurs;

- Trouver un utilisateur par email (pour le login)
SELECT * FROM utilisateurs WHERE email = 'admin@educlass.com';

- Ajouter un utilisateur (mot de passe déjà haché côté PHP)
INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe, role)
VALUES ('Fall', 'Moussa', 'moussa.fall@educlass.com', '$2b$10$hashexemple...', 'professeur');

- Modifier le mot de passe d'un utilisateur
UPDATE utilisateurs SET mot_de_passe = '$2b$10$nouveauHash...' WHERE id_utilisateur = 1;

 =========================================================
 EDUCLASS - REQUÊTES AVANCÉES
 Jointures, sous-requêtes, agrégations, calculs de moyennes



   1. Liste des cours avec le nom de l'enseignant, la matière
 et le libellé de la séance (jointures multiples)
   
SELECT
  c.id_cours,
  m.nom_matiere,
  CONCAT(e.prenom, ' ', e.nom) AS enseignant,
  s.libelle AS seance,
  c.date_cours,
  c.heure_debut,
  c.salle,
  c.statut,
  c.progression
FROM cours c
JOIN matieres m   ON c.id_matiere = m.id_matiere
JOIN enseignants e ON c.Matricule = e.Matricule
JOIN seances s    ON c.id_seance = s.id_seance
ORDER BY c.date_cours, c.heure_debut;



 2. Moyenne par matière pour chaque étudiant

SELECT
  et.id_etudiant,
  CONCAT(et.prenom, ' ', et.nom) AS etudiant,
  m.nom_matiere,
  ROUND(AVG(n.note), 2) AS moyenne_matiere
FROM notes n
JOIN etudiants et ON n.id_etudiant = et.id_etudiant
JOIN matieres m   ON n.id_matiere = m.id_matiere
GROUP BY et.id_etudiant, m.id_matiere
ORDER BY etudiant, m.nom_matiere;



 3. Moyenne générale pondérée par étudiant
 (pondération = heure_totale de la matière)

SELECT
  et.id_etudiant,
  CONCAT(et.prenom, ' ', et.nom) AS etudiant,
  ROUND(
    SUM(n.note * m.heure_totale) / SUM(m.heure_totale)
  , 2) AS moyenne_generale_ponderee
FROM notes n
JOIN etudiants et ON n.id_etudiant = et.id_etudiant
JOIN matieres m   ON n.id_matiere = m.id_matiere
GROUP BY et.id_etudiant
ORDER BY moyenne_generale_ponderee DESC;



 4. Étudiants n'ayant reçu aucune note (sous-requête NOT IN)

SELECT id_etudiant, nom, prenom
FROM etudiants
WHERE id_etudiant NOT IN (SELECT DISTINCT id_etudiant FROM notes);



 5. Taux d'absence par étudiant
 (nb absences non justifiées / nb total de cours)

SELECT
  et.id_etudiant,
  CONCAT(et.prenom, ' ', et.nom) AS etudiant,
  COUNT(a.id_absence) AS nb_absences,
  SUM(CASE WHEN a.justifie = 'Non' THEN 1 ELSE 0 END) AS nb_non_justifiees,
  ROUND(
    100 * SUM(CASE WHEN a.justifie = 'Non' THEN 1 ELSE 0 END)
    / NULLIF((SELECT COUNT(*) FROM cours), 0)
  , 2) AS taux_absence_pct
FROM etudiants et
LEFT JOIN absences a ON et.id_etudiant = a.id_etudiant
GROUP BY et.id_etudiant
ORDER BY nb_absences DESC;



 6. Cours de la semaine en cours (filtrage par semaine)

SELECT
  c.id_cours,
  m.nom_matiere,
  CONCAT(e.prenom, ' ', e.nom) AS enseignant,
  c.date_cours,
  c.heure_debut,
  c.salle,
  c.statut
FROM cours c
JOIN matieres m ON c.id_matiere = m.id_matiere
JOIN enseignants e ON c.Matricule = e.Matricule
WHERE YEARWEEK(c.date_cours, 1) = YEARWEEK(CURDATE(), 1)
ORDER BY c.date_cours, c.heure_debut;



 7. Charge horaire par enseignant (nb de cours + durée totale)

SELECT
  e.Matricule,
  CONCAT(e.prenom, ' ', e.nom) AS enseignant,
  COUNT(c.id_cours) AS nb_cours,
  SUM(c.duree) AS duree_totale_minutes
FROM enseignants e
LEFT JOIN cours c ON e.Matricule = c.Matricule
GROUP BY e.Matricule
ORDER BY duree_totale_minutes DESC;



 8. Taux de remplissage / progression moyenne des cours
 par matière

SELECT
  m.nom_matiere,
  COUNT(c.id_cours) AS nb_cours,
  ROUND(AVG(c.progression), 1) AS progression_moyenne
FROM matieres m
LEFT JOIN cours c ON m.id_matiere = c.id_matiere
GROUP BY m.id_matiere
ORDER BY progression_moyenne DESC;



 9. Évaluations à venir avec le nombre de notes déjà saisies
 (sous-requête corrélée)

SELECT
  ev.id_evaluation,
  ev.libelle,
  m.nom_matiere,
  ev.date_eval,
  ev.statut,
  (SELECT COUNT(*) FROM notes n WHERE n.id_evaluation = ev.id_evaluation) AS nb_notes_saisies
FROM evaluations ev
JOIN matieres m ON ev.id_matiere = m.id_matiere
ORDER BY ev.date_eval;



 10. Meilleur et moins bon étudiant par matière (fenêtrage)

SELECT *
FROM (
  SELECT
    m.nom_matiere,
    CONCAT(et.prenom, ' ', et.nom) AS etudiant,
    n.note,
    RANK() OVER (PARTITION BY m.id_matiere ORDER BY n.note DESC) AS rang_meilleur,
    RANK() OVER (PARTITION BY m.id_matiere ORDER BY n.note ASC)  AS rang_moins_bon
  FROM notes n
  JOIN etudiants et ON n.id_etudiant = et.id_etudiant
  JOIN matieres m   ON n.id_matiere = m.id_matiere
) classement
WHERE rang_meilleur = 1 OR rang_moins_bon = 1;



 11. Enseignants sans compte utilisateur associé
 (utile pour le suivi du multi-utilisateurs, une fois la
 colonne matricule_enseignant ajoutée sur utilisateurs -
 voir migrations.sql #3)

 SELECT e.Matricule, e.nom, e.prenom
 FROM enseignants e
 LEFT JOIN utilisateurs u ON u.matricule_enseignant = e.Matricule
 WHERE u.id_utilisateur IS NULL;
