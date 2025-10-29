<?php
session_start();

// --- 1. ZAŠČITA STRANI IN KONTROLA DOSTOPA ---
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once __DIR__ . '/../src/Database.php';

$course_id = $_GET['id'] ?? null;
if (!$course_id) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_vloga = $_SESSION['user_vloga'];
$has_access = false;
$is_teacher_of_course = false;

$errors = [];
$success_message = '';
$edit_assignment = null;

try {
    $pdo = Database::getInstance()->getConnection();

    // Preveri, ali ima uporabnik dostop do tečaja
    if ($user_vloga === 'admin') {
        $has_access = true;
    } elseif ($user_vloga === 'teacher') {
        $stmt = $pdo->prepare("SELECT 1 FROM ucitelji_tecajev WHERE id_ucitelja = ? AND id_tecaja = ?");
        $stmt->execute([$user_id, $course_id]);
        if ($stmt->fetch()) {
            $has_access = true;
            $is_teacher_of_course = true;
        }
    } elseif ($user_vloga === 'student') {
        $stmt = $pdo->prepare("SELECT 1 FROM vpisi WHERE id_studenta = ? AND id_tecaja = ?");
        $stmt->execute([$user_id, $course_id]);
        if ($stmt->fetch()) $has_access = true;
    }

    if (!$has_access) {
        $_SESSION['error_message'] = 'Do tega tečaja nimate dostopa.';
        header('Location: dashboard.php');
        exit();
    }

    // --- 2. OBDELAVA AKCIJ ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // AKCIJE ZA UČITELJA
        if ($is_teacher_of_course) {
            // Dodajanje povezave
            if (isset($_POST['add_link'])) {
                $naslov = trim($_POST['naslov'] ?? '');
                $url = trim($_POST['url'] ?? '');
                if (!empty($naslov) && !empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                    $sql = "INSERT INTO gradiva (id_tecaja, naslov, tip, vsebina) VALUES (?, ?, 'link', ?)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$course_id, $naslov, $url]);
                    $success_message = 'Povezava uspešno dodana.';
                } else { $errors[] = 'Vnesite veljaven naslov in URL.'; }
            }
            // Nalaganje datoteke
            if (isset($_POST['upload_file'])) {
                $naslov = trim($_POST['naslov_datoteke'] ?? '');
                if (!empty($naslov) && isset($_FILES['datoteka']) && $_FILES['datoteka']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['datoteka'];
                    if ($file['size'] > 15 * 1024 * 1024) {
                        $errors[] = 'Datoteka je prevelika. Največja dovoljena velikost je 15MB.';
                    } else {
                        $upload_dir = __DIR__ . '/../uploads/';
                        $filename = uniqid() . '-' . basename($file['name']);
                        $destination = $upload_dir . $filename;
                        if (move_uploaded_file($file['tmp_name'], $destination)) {
                            $sql = "INSERT INTO gradiva (id_tecaja, naslov, tip, vsebina) VALUES (?, ?, 'file', ?)";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$course_id, $naslov, $filename]);
                            $success_message = 'Datoteka uspešno naložena.';
                        } else { $errors[] = 'Napaka pri premikanju naložene datoteke.'; }
                    }
                } else { $errors[] = 'Izberite datoteko in vnesite naslov.'; }
            }
            // Dodajanje ali urejanje naloge
            if (isset($_POST['save_assignment'])) {
                $naslov = trim($_POST['naslov_naloge'] ?? '');
                $opis = trim($_POST['opis_naloge'] ?? '');
                $rok_oddaje = !empty($_POST['rok_oddaje']) ? $_POST['rok_oddaje'] : null;
                $assignment_id = $_POST['assignment_id'] ?? null;
                if (empty($naslov)) { $errors[] = 'Naslov naloge je obvezen.'; }
                if (empty($errors)) {
                    if ($assignment_id) { // Urejanje
                        $sql = "UPDATE naloge SET naslov = ?, opis = ?, rok_oddaje = ? WHERE id = ? AND id_tecaja = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$naslov, $opis, $rok_oddaje, $assignment_id, $course_id]);
                        $success_message = 'Naloga uspešno posodobljena.';
                    } else { // Ustvarjanje
                        $sql = "INSERT INTO naloge (id_tecaja, naslov, opis, rok_oddaje) VALUES (?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$course_id, $naslov, $opis, $rok_oddaje]);
                        $success_message = 'Naloga uspešno ustvarjena.';
                    }
                }
            }
        }

        // AKCIJE ZA ŠTUDENTA
        if ($user_vloga === 'student' && isset($_POST['submit_assignment'])) {
            $assignment_id = $_POST['assignment_id'] ?? null;
            if ($assignment_id && isset($_FILES['datoteka_oddaje']) && $_FILES['datoteka_oddaje']['error'] === UPLOAD_ERR_OK) {
                
                $stmt_user = $pdo->prepare("SELECT ime, priimek FROM uporabniki WHERE id = ?");
                $stmt_user->execute([$user_id]);
                $user_details = $stmt_user->fetch();

                $stmt_ass = $pdo->prepare("SELECT naslov FROM naloge WHERE id = ?");
                $stmt_ass->execute([$assignment_id]);
                $assignment_details = $stmt_ass->fetch();
                
                $file = $_FILES['datoteka_oddaje'];

                $lastName = preg_replace('/[^A-Za-z0-9_]/', '', $user_details['priimek']);
                $firstName = preg_replace('/[^A-Za-z0-9_]/', '', $user_details['ime']);
                $assignmentTitle = preg_replace('/[^A-Za-z0-9_]/', '', $assignment_details['naslov']);
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                
                $new_filename = "{$lastName}_{$firstName}_{$assignmentTitle}.{$extension}";
                $destination = __DIR__ . '/../uploads/submissions/' . $new_filename;
                
                $stmt_check = $pdo->prepare("SELECT id FROM oddaje WHERE id_studenta = ? AND id_naloge = ?");
                $stmt_check->execute([$user_id, $assignment_id]);
                $existing_submission = $stmt_check->fetch();

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    if ($existing_submission) {
                        $sql = "UPDATE oddaje SET pot_do_datoteke = ?, oddano_ob = NOW() WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$new_filename, $existing_submission['id']]);
                        $success_message = 'Oddaja uspešno posodobljena.';
                    } else {
                        $sql = "INSERT INTO oddaje (id_naloge, id_studenta, pot_do_datoteke) VALUES (?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$assignment_id, $user_id, $new_filename]);
                        $success_message = 'Naloga uspešno oddana.';
                    }
                } else { $errors[] = 'Napaka pri nalaganju datoteke.'; }
            } else { $errors[] = 'Prosimo, izberite datoteko za oddajo.'; }
        }
    }
    
    // AKCIJE ZA UČITELJA (GET)
    if ($is_teacher_of_course && isset($_GET['action'])) {
        if ($_GET['action'] === 'delete_material' && isset($_GET['material_id'])) {
            $material_id = $_GET['material_id'];
            $stmt_mat = $pdo->prepare("SELECT * FROM gradiva WHERE id = ? AND id_tecaja = ?");
            $stmt_mat->execute([$material_id, $course_id]);
            if ($material_to_delete = $stmt_mat->fetch()) {
                if ($material_to_delete['tip'] === 'file') {
                    $file_path = __DIR__ . '/../uploads/' . $material_to_delete['vsebina'];
                    if (file_exists($file_path)) unlink($file_path);
                }
                $stmt_del = $pdo->prepare("DELETE FROM gradiva WHERE id = ?");
                $stmt_del->execute([$material_id]);
                $success_message = 'Gradivo uspešno izbrisano.';
            }
        }
        if ($_GET['action'] === 'delete_assignment' && isset($_GET['assignment_id'])) {
            $assignment_id = $_GET['assignment_id'];
            $stmt = $pdo->prepare("DELETE FROM naloge WHERE id = ? AND id_tecaja = ?");
            $stmt->execute([$assignment_id, $course_id]);
            $success_message = 'Naloga uspešno izbrisana.';
        }
        if ($_GET['action'] === 'edit_assignment' && isset($_GET['assignment_id'])) {
            $assignment_id = $_GET['assignment_id'];
            $stmt = $pdo->prepare("SELECT * FROM naloge WHERE id = ? AND id_tecaja = ?");
            $stmt->execute([$assignment_id, $course_id]);
            $edit_assignment = $stmt->fetch();
        }
    }

    // --- 3. PRIDOBIVANJE PODATKOV ZA PRIKAZ ---
    $stmt_course = $pdo->prepare("SELECT * FROM tecaji WHERE id = ?");
    $stmt_course->execute([$course_id]);
    $course = $stmt_course->fetch();
    if (!$course) { header('Location: dashboard.php'); exit(); }

    $stmt_materials = $pdo->prepare("SELECT * FROM gradiva WHERE id_tecaja = ? ORDER BY nalozeno_ob DESC");
    $stmt_materials->execute([$course_id]);
    $materials = $stmt_materials->fetchAll();

    $stmt_assignments = $pdo->prepare("SELECT * FROM naloge WHERE id_tecaja = ? ORDER BY rok_oddaje DESC");
    $stmt_assignments->execute([$course_id]);
    $assignments = $stmt_assignments->fetchAll();

    $student_submissions = [];
    if ($user_vloga === 'student') {
        $sql_submissions = "SELECT id, id_naloge, pot_do_datoteke, oddano_ob FROM oddaje WHERE id_studenta = ? AND id_naloge IN (SELECT id FROM naloge WHERE id_tecaja = ?)";
        $stmt_submissions = $pdo->prepare($sql_submissions);
        $stmt_submissions->execute([$user_id, $course_id]);
        foreach ($stmt_submissions->fetchAll() as $sub) {
            $student_submissions[$sub['id_naloge']] = $sub;
        }
    }

} catch (PDOException $e) { die("Napaka v bazi podatkov: " . $e->getMessage()); }

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0"><?php echo htmlspecialchars($course['naziv']); ?></h1>
            <p class="text-muted"><?php echo htmlspecialchars($course['opis']); ?></p>
        </div>
        <a href="dashboard.php" class="btn btn-secondary">Nazaj na nadzorno ploščo</a>
    </div>

    <?php if ($success_message): ?><div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert alert-danger"><?php foreach($errors as $error) echo "<p class='mb-0'>" . htmlspecialchars($error) . "</p>"; ?></div><?php endif; ?>

    <?php if ($is_teacher_of_course): ?>
    <div class="row mb-4">
        <div class="col-md-6 mb-3 mb-md-0"><div class="card h-100"><div class="card-header"><h4>Naloži datoteko</h4></div><div class="card-body"><form action="course.php?id=<?php echo $course_id; ?>" method="POST" enctype="multipart/form-data"><div class="mb-3"><label for="naslov_datoteke" class="form-label">Naslov gradiva</label><input type="text" name="naslov_datoteke" id="naslov_datoteke" class="form-control" required></div><div class="mb-3"><label for="datoteka" class="form-label">Izberi datoteko (max 15MB)</label><input type="file" name="datoteka" id="datoteka" class="form-control" required></div><button type="submit" name="upload_file" class="btn btn-primary">Naloži</button></form></div></div></div>
        <div class="col-md-6"><div class="card h-100"><div class="card-header"><h4>Dodaj povezavo</h4></div><div class="card-body"><form action="course.php?id=<?php echo $course_id; ?>" method="POST"><div class="mb-3"><label for="naslov" class="form-label">Naslov povezave</label><input type="text" name="naslov" id="naslov" class="form-control" required></div><div class="mb-3"><label for="url" class="form-label">URL povezave</label><input type="url" name="url" id="url" class="form-control" placeholder="https://www.primer.com" required></div><button type="submit" name="add_link" class="btn btn-primary">Dodaj</button></form></div></div></div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6 mb-3 mb-md-0">
            <div class="card"><div class="card-header"><h4>Gradiva</h4></div><div class="card-body"><?php if (empty($materials)): ?><p>Za ta tečaj še ni bilo dodanega nobenega gradiva.</p><?php else: ?><ul class="list-group list-group-flush"><?php foreach ($materials as $material): ?><li class="list-group-item d-flex justify-content-between align-items-center"><div><?php if ($material['tip'] === 'link'): ?><a href="<?php echo htmlspecialchars($material['vsebina']); ?>" target="_blank"><?php echo htmlspecialchars($material['naslov']); ?></a><span class="badge bg-secondary ms-2">Povezava</span><?php else: ?><a href="download.php?id=<?php echo $material['id']; ?>"><?php echo htmlspecialchars($material['naslov']); ?></a><span class="badge bg-info ms-2">Datoteka</span><?php endif; ?></div><?php if ($is_teacher_of_course): ?><a href="course.php?id=<?php echo $course_id; ?>&action=delete_material&material_id=<?php echo $material['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Ste prepričani?');">Izbriši</a><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?></div></div>
        </div>
        <div class="col-md-6">
            <div class="card"><div class="card-header"><h4>Naloge</h4></div><div class="card-body"><?php if (empty($assignments)): ?><p>Za ta tečaj še ni bilo ustvarjene nobene naloge.</p><?php else: ?><div class="accordion" id="assignmentsAccordion"><?php foreach ($assignments as $assignment): ?><div class="accordion-item"><h2 class="accordion-header" id="heading<?php echo $assignment['id']; ?>"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $assignment['id']; ?>" aria-expanded="false"><?php echo htmlspecialchars($assignment['naslov']); ?> <small class="ms-auto text-muted pe-3">Rok: <?php echo $assignment['rok_oddaje'] ? date('d. m. Y H:i', strtotime($assignment['rok_oddaje'])) : 'Ni določen'; ?></small></button></h2><div id="collapse<?php echo $assignment['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#assignmentsAccordion"><div class="accordion-body"><p><?php echo nl2br(htmlspecialchars($assignment['opis'])); ?></p><?php if ($is_teacher_of_course): ?><hr><a href="assignment_submissions.php?id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-info">Preglej oddaje</a><a href="course.php?id=<?php echo $course_id; ?>&action=edit_assignment&assignment_id=<?php echo $assignment['id']; ?>#assignment_form" class="btn btn-sm btn-secondary">Uredi</a><a href="course.php?id=<?php echo $course_id; ?>&action=delete_assignment&assignment_id=<?php echo $assignment['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Ste prepričani?');">Izbriši</a><?php endif; ?><?php if ($user_vloga === 'student'): ?><hr><h5>Oddaja naloge</h5><?php if (isset($student_submissions[$assignment['id']])): $submission = $student_submissions[$assignment['id']]; ?><p class="text-success mb-1"><strong>Status:</strong> Oddano dne <?php echo date('d. m. Y H:i', strtotime($submission['oddano_ob'])); ?></p><a href="download_submission.php?id=<?php echo $submission['id']; ?>">Prenesi mojo oddajo</a><?php else: ?><p class="text-danger"><strong>Status:</strong> Naloge še niste oddali.</p><?php endif; ?><form action="course.php?id=<?php echo $course_id; ?>" method="POST" enctype="multipart/form-data" class="mt-2"><input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>"><div class="input-group"><input type="file" class="form-control" name="datoteka_oddaje" required><button type="submit" name="submit_assignment" class="btn btn-primary" <?php if (isset($student_submissions[$assignment['id']])) echo "onclick=\"return confirm('Ste prepričani, da želite zamenjati prejšnjo oddajo? To dejanje ni reverzibilno.');\""; ?>><?php echo isset($student_submissions[$assignment['id']]) ? 'Zamenjaj oddajo' : 'Oddaj nalogo'; ?></button></div></form><?php endif; ?></div></div></div><?php endforeach; ?></div><?php endif; ?></div></div>
        </div>
    </div>
    
    <?php if ($is_teacher_of_course): ?>
    <div class="card mt-4" id="assignment_form"><div class="card-header"><h3><?php echo $edit_assignment ? 'Uredi nalogo' : 'Dodaj novo nalogo'; ?></h3></div><div class="card-body"><form action="course.php?id=<?php echo $course_id; ?>" method="POST"><input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($edit_assignment['id'] ?? ''); ?>"><div class="mb-3"><label for="naslov_naloge" class="form-label">Naslov</label><input type="text" name="naslov_naloge" id="naslov_naloge" class="form-control" value="<?php echo htmlspecialchars($edit_assignment['naslov'] ?? ''); ?>" required></div><div class="mb-3"><label for="opis_naloge" class="form-label">Opis</label><textarea name="opis_naloge" id="opis_naloge" class="form-control" rows="4"><?php echo htmlspecialchars($edit_assignment['opis'] ?? ''); ?></textarea></div><div class="mb-3"><label for="rok_oddaje" class="form-label">Rok oddaje</label><input type="datetime-local" name="rok_oddaje" id="rok_oddaje" class="form-control" value="<?php echo !empty($edit_assignment['rok_oddaje']) ? date('Y-m-d\TH:i', strtotime($edit_assignment['rok_oddaje'])) : ''; ?>"></div><button type="submit" name="save_assignment" class="btn btn-primary"><?php echo $edit_assignment ? 'Shrani spremembe' : 'Dodaj nalogo'; ?></button><?php if ($edit_assignment): ?><a href="course.php?id=<?php echo $course_id; ?>" class="btn btn-secondary">Prekliči</a><?php endif; ?></form></div></div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>