<?php
/**
 * Diagnostic tarification (matinal / normal) — à supprimer après usage.
 * Accès (connecté) : https://csk-foot.com/gestion/terrains/tarif-diagnostic.php
 * Read-only : n'écrit rien.
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Terrain.php';

Auth::requireLogin();

header('Content-Type: text/plain; charset=utf-8');

echo "=== DIAGNOSTIC TARIFICATION ===\n\n";

// 1) Paramètre de coupure + week-end
$coupure = getParam('heure_pointe_debut', '(non défini → défaut 16:00)');
$weekend = getParam('jours_weekend', '(non défini → défaut 6,7)');
echo "[Paramètres en ligne]\n";
echo "  heure_pointe_debut (coupure) : $coupure\n";
echo "  jours_weekend                : $weekend\n";
echo "  >> Avant cette heure = matinal ; à partir = normal.\n\n";

// 2) Preuve que le nouveau code est déployé
$src = @file_get_contents(APP_PATH . 'models/Terrain.php');
$deployed = ($src !== false && strpos($src, 'prixMatinal') !== false && strpos($src, 'heureCoupure') !== false);
echo "[Déploiement]\n";
echo "  Terrain.php nouveau modèle (prixMatinal/heureCoupure) : " . ($deployed ? "OUI ✅" : "NON ❌ (ancien fichier en ligne / OPcache)") . "\n\n";

// 3) Trouver un jour de semaine (hors week-end) pour tester
$joursWeekend = array_filter(array_map('intval', explode(',', getParam('jours_weekend', '6,7'))));
$d = new DateTime('today');
for ($i = 0; $i < 8; $i++) {
    if (!in_array((int)$d->format('N'), $joursWeekend, true)) break;
    $d->modify('+1 day');
}
$testDate = $d->format('Y-m-d');
echo "[Test de calcul — jour de semaine $testDate]\n";
printf("  %-22s %12s %12s | %12s %12s\n", "Terrain", "Matinal(hr)", "Normal(pte)", "Prix 10:00", "Prix 18:00");
echo "  " . str_repeat('-', 78) . "\n";

$terrains = Terrain::getAll('actif');
foreach ($terrains as $t) {
    $matinal = (float)$t['prix_heure'];
    $normal  = $t['prix_heure_pointe'] !== null && $t['prix_heure_pointe'] !== '' ? (float)$t['prix_heure_pointe'] : null;
    $prix10 = Terrain::calculatePrice((int)$t['id'], $testDate, '10:00', '11:00'); // 1h le matin
    $prix18 = Terrain::calculatePrice((int)$t['id'], $testDate, '18:00', '19:00'); // 1h l'après-midi
    printf("  %-22s %12s %12s | %12s %12s\n",
        mb_substr($t['nom'], 0, 22),
        number_format($matinal, 0, ',', ' '),
        $normal === null ? "(VIDE ⚠)" : number_format($normal, 0, ',', ' '),
        number_format($prix10, 0, ',', ' '),
        number_format($prix18, 0, ',', ' ')
    );
}

echo "\n[Interprétation]\n";
echo "  - Prix 10:00 doit = Matinal, Prix 18:00 doit = Normal (si coupure=16:00).\n";
echo "  - Si 'Normal(pte)' = VIDE ⚠ pour un terrain : matinal s'applique toute la journée\n";
echo "    (10:00 et 18:00 identiques) -> renseigner le 'Tarif normal' du terrain.\n";
echo "  - Si Déploiement = NON : les fichiers ne sont pas à jour en ligne (ré-uploader + vider OPcache).\n";
