<?php
// Začetek seje za dostop do spremenljivk seje.
session_start();

// --- 1. ZAŠČITA STRANI ---
// Preveri, če je uporabnik prijavljen. Če ni, ga preusmeri na stran za prijavo.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vključi povezavo z bazo podatkov.
require_once __DIR__ . '/../src/Database.php';
$my_courses = [];

// --- 2. PRIDOBIVANJE PODATKOV O UPORABNIKU ---
try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT ime, priimek, vloga FROM uporabniki WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    // Če je uporabnik študent, pridobi njegove vpisane tečaje
    if ($user && $user['vloga'] === 'student') {
        $sql = "SELECT t.id, t.naziv FROM tecaji t JOIN vpisi v ON t.id = v.id_tecaja WHERE v.id_studenta = ?";
        $stmt_courses = $pdo->prepare($sql);
        $stmt_courses->execute([$_SESSION['user_id']]);
        $my_courses = $stmt_courses->fetchAll();
    }

} catch (PDOException $e) {
    // V primeru napake v bazi je najbolje prekiniti sejo in preusmeriti.
    error_log('Dashboard error: ' . $e->getMessage());
    header('Location: logout.php');
    exit();
}

// Če uporabnik ni bil najden v bazi (npr. izbrisan), ga odjavi.
if (!$user) {
    header('Location: logout.php');
    exit();
}

// --- 3. PRIKAZ STRANI ---
require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <div class="p-5 mb-4 bg-light rounded-3">
        <div class="container-fluid py-5">
            <h1 class="display-5 fw-bold">Pozdravljeni, <?php echo htmlspecialchars($user['ime']); ?>!</h1>
            <p class="col-md-8 fs-4">
                Uspešno ste prijavljeni v sistem Zadostno. Vaša vloga je: <strong><?php echo htmlspecialchars($user['vloga']); ?></strong>.
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <h2>Nadzorna plošča</h2>
            
            <?php // --- 4. VSEBINA GLEDE NA VLOGO --- ?>
            <?php if ($user['vloga'] === 'admin'): ?>
                <p>Tukaj boste lahko upravljali uporabnike in tečaje.</p>
                <!-- Povezave za admina so v glavi -->
            <?php elseif ($user['vloga'] === 'teacher'): ?>
                <p>Tukaj boste lahko upravljali svoje tečaje, gradiva in naloge.</p>
                <!-- Povezave za učitelja bodo dodane kasneje -->
            <?php elseif ($user['vloga'] === 'student'): ?>
                <h4>Moji tečaji</h4>
                <?php if (!empty($my_courses)): ?>
                    <ul class="list-group mb-4">
                        <?php foreach ($my_courses as $course): ?>
                            <li class="list-group-item"><?php echo htmlspecialchars($course['naziv']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p>Niste vpisani v noben tečaj.</p>
                <?php endif; ?>
                <a href="courses.php" class="btn btn-primary">Prikaži vse tečaje</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>