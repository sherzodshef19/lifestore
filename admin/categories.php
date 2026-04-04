<?php
require_once 'layout.php';

$success = '';
$error = '';

if (isset($_POST['add_category'])) {
    $name = $conn->real_escape_string($_POST['name']);
    if ($conn->query("INSERT INTO categories (name) VALUES ('$name')")) {
        $success = "Категория муваффақиятли қўшилди!";
    } else {
        $error = "Хатолик юз берди: " . $conn->error;
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($conn->query("DELETE FROM categories WHERE id = $id")) {
        $success = "Категория ўчирилди!";
    } else {
        $error = "Хатолик юз берди!";
    }
}

render_header('Категориялар');
?>

<div class="row">
    <div class="col-md-4">
        <div class="card p-4 mb-4">
            <h5 class="fw-bold mb-4">Янги категория</h5>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Номи</label>
                    <input type="text" name="name" class="form-control" placeholder="М: Озиқ-овқат" required>
                </div>
                <button type="submit" name="add_category" class="btn btn-primary w-100">ҚЎШИШ</button>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <?php if($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card p-4">
            <h5 class="fw-bold mb-4">Барча категориялар</h5>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Номи</th>
                            <th class="text-end">Амаллар</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $categories = $conn->query("SELECT * FROM categories ORDER BY id DESC");
                        while($c = $categories->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?php echo $c['id']; ?></td>
                            <td><?php echo $c['name']; ?></td>
                            <td class="text-end">
                                <a href="?delete=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Ростан ҳам ўчирмоқчимисиз?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php render_footer(); ?>
