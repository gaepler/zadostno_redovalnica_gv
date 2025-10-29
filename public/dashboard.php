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
            $sql = "SELECT t.id, t.naziv, t.opis FROM tecaji t JOIN vpisi v ON t.id = v.id_tecaja WHERE v.id_studenta = ? ORDER BY t.naziv";
            $stmt_courses = $pdo->prepare($sql);
            $stmt_courses->execute([$_SESSION['user_id']]);
            $my_courses = $stmt_courses->fetchAll();
        } elseif ($user['vloga'] === 'teacher') {
            $sql = "SELECT t.id, t.naziv, t.opis FROM tecaji t JOIN ucitelji_tecajev ut ON t.id = ut.id_tecaja WHERE ut.id_ucitelja = ? ORDER BY t.naziv";
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
                Dobrodošli nazaj v sistem Zadostno.
            </p>
        </div>
    </div>

    <!-- VSEBINA GLEDE NA VLOGO -->
    <?php if ($user['vloga'] === 'admin'): ?>
        <div class="row">
            <div class="col-md-6">
                <div class="card text-center h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <h5 class="card-title">Upravljanje uporabnikov</h5>
                        <p class="card-text">Dodajanje, urejanje in brisanje študentov ter učiteljev.</p>
                        <a href="admin_users.php" class="btn btn-primary mt-auto">Pojdi na upravljanje</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card text-center h-100">
                    <div class="card-body d-flex flex-column justify-content-center">
                        <h5 class="card-title">Upravljanje tečajev</h5>
                        <p class="card-text">Ustvarjanje tečajev in dodeljevanje uporabnikov.</p>
                        <a href="admin_courses.php" class="btn btn-primary mt-auto">Pojdi na upravljanje</a>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($user['vloga'] === 'teacher'): ?>
        <h3>Moji tečaji</h3>
        <?php if (!empty($my_courses)): ?>
            <div class="row row-cols-1 row-cols-md-3 g-4">
                <?php foreach ($my_courses as $course): ?>
                    <div class="col">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($course['naziv']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars(substr($course['opis'], 0, 100)) . '...'; ?></p>
                            </div>
                            <div class="card-footer">
                                <a href="course.php?id=<?php echo $course['id']; ?>" class="btn btn-primary w-100">Odpri tečaj</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>Niste dodeljeni nobenemu tečaju.</p>
        <?php endif; ?>

    <?php elseif ($user['vloga'] === 'student'): ?>
        <div class="row">
            <div class="col-md-8">
                <h3>Moji tečaji</h3>
                <?php if (!empty($my_courses)): ?>
                    <div class="list-group">
                        <?php foreach ($my_courses as $course): ?>
                            <a href="course.php?id=<?php echo $course['id']; ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <?php echo htmlspecialchars($course['naziv']); ?>
                                <span class="badge bg-primary rounded-pill">Odpri</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>Niste vpisani v noben tečaj.</p>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <h3>Hitre povezave</h3>
                <div class="d-grid gap-2">
                    <a href="my_grades.php" class="btn btn-outline-primary">Prikaži moje ocene</a>
                    <a href="courses.php" class="btn btn-outline-secondary">Prikaži katalog tečajev</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>