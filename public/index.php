<?php
session_start();

// Če je uporabnik že prijavljen, ga preusmeri na nadzorno ploščo
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <div class="p-5 mb-4 bg-light rounded-3 text-center">
        <div class="container-fluid py-5">
            <h1 class="display-5 fw-bold">Dobrodošli v sistem Zadostno</h1>
            <p class="fs-4">
                Vaša centralna platforma za učenje na daljavo.
            </p>
            <p class="lead mt-4">
                Sistem omogoča enostavno upravljanje s tečaji, gradivi in nalogami.
            </p>
            <hr class="my-4">
            <p>Za začetek se prijavite ali registrirajte.</p>
            <a class="btn btn-primary btn-lg mx-2" href="login.php" role="button">Prijava</a>
            <a class="btn btn-secondary btn-lg mx-2" href="register.php" role="button">Registracija</a>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../templates/layouts/footer.php';
?>