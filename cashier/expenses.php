<?php
require_once 'layout.php';
render_header('Кунлик харажатлар');

$success = '';
$error = '';

if (isset($_POST['add_expense'])) {
    $category = $conn->real_escape_string($_POST['category']);
    $amount = (float)$_POST['amount'];
    $description = $conn->real_escape_string($_POST['description']);
    $user_id = $_SESSION['user_id'];

    if ($amount > 0 && !empty($category)) {
        $sql = "INSERT INTO expenses (user_id, category, amount, description) VALUES ($user_id, '$category', $amount, '$description')";
        if ($conn->query($sql)) {
            $success = "Харажат муваффақиятли қўшилди!";
        } else {
            $error = "Хатолик: " . $conn->error;
        }
    } else {
        $error = "Илтимос, барча майдонларни тўлдиринг!";
    }
}
?>

<div class="row">
    <div class="col-md-4">
        <div class="card pos-card p-4 h-100">
            <h5 class="fw-bold mb-4 text-primary"><i class="bi bi-plus-circle me-2"></i> Янги харажат киритиш</h5>
            
            <?php if($success): ?>
                <div class="alert alert-success alert-dismissible fade show"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Харажат сабаби (Нима учун?)</label>
                    <input type="text" name="category" class="form-control form-control-lg" placeholder="Масалан: Тушлик, Такси, Хўжалик моллари..." required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Суммаси (сўмда)</label>
                    <input type="number" name="amount" class="form-control form-control-lg" placeholder="0" required>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-semibold">Қўшимча изоҳ (ихтиёрий)</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Батафсилроқ маълумот..."></textarea>
                </div>
                <button type="submit" name="add_expense" class="btn btn-primary btn-lg w-100 py-3 fw-bold">
                    <i class="bi bi-save me-2"></i> САҚЛАШ
                </button>
            </form>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card pos-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold m-0"><i class="bi bi-list-task me-2"></i> Бугунги харажатларингиз</h5>
                <span class="badge bg-light text-dark border p-2"><?php echo date('d.m.Y'); ?></span>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Вақт</th>
                            <th>Сабаби</th>
                            <th>Суммаси</th>
                            <th>Изоҳ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $user_id = $_SESSION['user_id'];
                        $today = date('Y-m-d');
                        $sql = "SELECT * FROM expenses WHERE user_id = $user_id AND DATE(date) = '$today' ORDER BY date DESC";
                        $res = $conn->query($sql);
                        $total_today = 0;
                        
                        if ($res->num_rows > 0):
                            while($row = $res->fetch_assoc()):
                                $total_today += $row['amount'];
                        ?>
                        <tr>
                            <td class="small text-muted"><?php echo date('H:i', strtotime($row['date'])); ?></td>
                            <td><span class="fw-bold"><?php echo $row['category']; ?></span></td>
                            <td class="text-danger fw-bold"><?php echo formatMoney($row['amount']); ?></td>
                            <td class="small text-muted italic"><?php echo $row['description'] ?: '—'; ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">Бугун ҳали харажат киритилмаган.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <?php if($total_today > 0): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="2" class="text-end">Бугунги жами харажат:</td>
                            <td class="text-danger fs-5"><?php echo formatMoney($total_today); ?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
