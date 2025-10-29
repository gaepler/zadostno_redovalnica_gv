<?php
session_start();

// --- 1. KONTROLA DOSTOPA (samo za študente) ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_vloga']) || $_SESSION['user_vloga'] !== 'student') {
    $_SESSION['error_message'] = 'Ta stran je namenjena samo študentom.';
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/../src/Database.php';

$student_id = $_SESSION['user_id'];
$courses_with_grades = [];

try {
    $pdo = Database::getInstance()->getConnection();

    // Pridobi vse tečaje, v katere je študent vpisan
    $sql_courses = "SELECT t.id, t.naziv 
                    FROM tecaji t 
                    JOIN vpisi v ON t.id = v.id_tecaja 
                    WHERE v.id_studenta = ? 
                    ORDER BY t.naziv";
    $stmt_courses = $pdo->prepare($sql_courses);
    $stmt_courses->execute([$student_id]);
    $courses = $stmt_courses->fetchAll();

    // Za vsak tečaj pridobi naloge in ocene
    foreach ($courses as $course) {
        $sql_grades = "
            SELECT 
                n.naslov as assignment_title,
                o.oddano_ob,
                oc.ocena,
                oc.povratna_informacija,
                oc.ocenjeno_ob
            FROM naloge n
            LEFT JOIN oddaje o ON n.id = o.id_naloge AND o.id_studenta = ?
            LEFT JOIN ocene oc ON o.id = oc.id_oddaje
            WHERE n.id_tecaja = ?
            ORDER BY n.ustvarjeno_ob DESC
        ";
        $stmt_grades = $pdo->prepare($sql_grades);
        $stmt_grades->execute([$student_id, $course['id']]);
        $grades = $stmt_grades->fetchAll();

        // Shrani podatke v strukturirano polje
        $courses_with_grades[] = [
            'course_id' => $course['id'],
            'course_name' => $course['naziv'],
            'grades' => $grades
        ];
    }

} catch (PDOException $e) {
    die("Napaka v bazi podatkov: " . $e->getMessage());
}

require_once __DIR__ . '/../templates/layouts/header.php';
?>

<div class="container">
    <h1 class="mb-4">Moje ocene</h1>

    <?php if (empty($courses_with_grades)): ?>
        <div class="alert alert-info">Niste vpisani v noben tečaj ali pa še ni bilo ustvarjenih nalog.</div>
    <?php else: ?>
        <div class="accordion" id="gradesAccordion">
            <?php foreach ($courses_with_grades as $course_data): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-course-<?php echo $course_data['course_id']; ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-course-<?php echo $course_data['course_id']; ?>" aria-expanded="false">
                            <strong><?php echo htmlspecialchars($course_data['course_name']); ?></strong>
                        </button>
                    </h2>
                    <div id="collapse-course-<?php echo $course_data['course_id']; ?>" class="accordion-collapse collapse" data-bs-parent="#gradesAccordion">
                        <div class="accordion-body">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Naloga</th>
                                        <th>Status</th>
                                        <th>Ocena</th>
                                        <th>Povratna informacija</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($course_data['grades'] as $grade_info): ?>
                                    <tr>
                                        <td class="align-middle"><?php echo htmlspecialchars($grade_info['assignment_title']); ?></td>
                                        <td class="align-middle">
                                            <?php if ($grade_info['oddano_ob']): ?>
                                                <span class="badge bg-success">Oddano</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Ni oddano</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="align-middle">
                                            <strong><?php echo htmlspecialchars($grade_info['ocena'] ?? '---'); ?></strong>
                                        </td>
                                        <td>
                                            <?php if ($grade_info['povratna_informacija']): ?>
                                                <small><?php echo nl2br(htmlspecialchars($grade_info['povratna_informacija'])); ?></small>
                                            <?php else: ?>
                                                <small class="text-muted">---</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../templates/layouts/footer.php'; ?>