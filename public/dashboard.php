<?php
// Začetek seje za dostop do spremenljivk seje.
session_start();

// --- 1. ZAŠČITA STRANI ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Vključi povezavo z bazo podatkov.
require_once __DIR__ . '/../src/Database.php';

$my_courses = []; // Inicializacija polja za tečaje

// --- 2. PRIDOBIVANJE PODATKOV O UPORABNIKU ---
try {
    $pdo = Database::getInstance()->getConnection();
    $stmt = $pdo->prepare('SELECT ime, priimek, vloga FROM uporabniki WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        // Glede na vlogo uporabnika pridobi relevantne tečaje
        if ($user['vloga'] === 'student') {
            $sql = "SELECT t.id, t.naziv FROM tecaji t JOIN vpisi v ON t.id = v.id_tecaja WHERE v.id_studenta = ? ORDER BY t.naziv";
            $stmt_courses = $pdo->prepare($sql);
            $stmt_courses->execute([$_SESSION['user_id']]);
            $my_courses = $stmt_courses->fetchAll();
        } elseif ($user['vloga'] === 'teacher') {
            $sql = "SELECT t.id, t.naziv FROM tecaji t JOIN ucitelji_tecajev ut ON t.id = ut.id_tecaja WHERE ut.id_ucitelja = ? ORDER BY t.naziv";
            $stmt_courses = $pdo->prepare($sql);
            $stmt_courses->execute([$_SESSION['user_id']]);
            $my_courses = $stmt_courses->fetchAll();
        }
    } else {
        // Če uporabnik ne obstaja, ga odjavi
        header('Location: logout.php');
        exit();
    }

} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
    header('Location: logout.php');
    exit();
}

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>

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
            
            <?php // --- VSEBINA GLEDE NA VLOGO --- ?>
            <?php if ($user['vloga'] === 'admin'): ?>
                <p>Izberite eno od možnosti v meniju za upravljanje.</p>
            
            <?php elseif ($user['vloga'] === 'teacher'): ?>
                <h4>Moji tečaji</h4>
                <?php if (!empty($my_courses)): ?>
                    <div class="list-group">
                        <?php foreach ($my_courses as $course): ?>
                            <a href="course.php?id=<?php echo $course['id']; ?>" class="list-group-item list-group-item-action">
                                <?php echo htmlspecialchars($course['naziv']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Niste dodeljeni nobenemu tečaju.</p>
                <?php endif; ?>

            <?php elseif ($user['vloga'] === 'student'): ?>
                <h4>Moji tečaji</h4>
                <?php if (!empty($my_courses)): ?>
                    <div class="list-group mb-4">
                        <?php foreach ($my_courses as $course): ?>
                            <a href="course.php?id=<?php echo $course['id']; ?>" class="list-group-item list-group-item-action">
                                <?php echo htmlspecialchars($course['naziv']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Niste vpisani v noben tečaj.</p>
                <?php endif; ?>
                <a href="courses.php" class="btn btn-primary">Prikaži vse tečaje</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>