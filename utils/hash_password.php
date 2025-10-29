<?php
// Ta skripta ni del glavne aplikacije in je namenjena samo za razvijalce.
// Dostop do nje naj bo omejen.

$password_to_hash = '';
$hashed_password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['password'])) {
    $password_to_hash = $_POST['password'];
    // Uporabimo isto metodo šifriranja kot v aplikaciji
    $hashed_password = password_hash($password_to_hash, PASSWORD_BCRYPT);
}

?>
<!DOCTYPE html>
<html lang="sl">
<head>
    <meta charset="UTF-8">
    <title>Generator gesel (Hash)</title>
    <!-- Uporabimo Bootstrap za lepši izgled -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3>Generator gesel (Hash)</h3>
                    </div>
                    <div class="card-body">
                        <p>Vnesite geslo, da dobite njegovo šifrirano (hash) vrednost za vnos v bazo podatkov.</p>
                        
                        <form action="hash_password.php" method="POST">
                            <div class="mb-3">
                                <label for="password" class="form-label">Geslo v čistem tekstu</label>
                                <input type="text" class="form-control" id="password" name="password" value="<?php echo htmlspecialchars($password_to_hash); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Generiraj Hash</button>
                        </form>

                        <?php if ($hashed_password): ?>
                        <div class="mt-4">
                            <label for="hashed_output" class="form-label"><strong>Generiran Hash (za `geslo_hash` stolpec):</strong></label>
                            <textarea id="hashed_output" class="form-control" rows="3" readonly onclick="this.select();"><?php echo htmlspecialchars($hashed_password); ?></textarea>
                            <small class="form-text text-muted">Kliknite na polje, da ga označite, nato ga kopirajte (Ctrl+C).</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-center mt-3">
                    <a href="../public/dashboard.php">Nazaj na aplikacijo</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>