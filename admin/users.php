<?php
require_once 'layout.php';

$success = '';
$error = '';

if (isset($_POST['save_user'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = $conn->real_escape_string($_POST['name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $login = $conn->real_escape_string($_POST['login']);
    $password = $_POST['password'];

    if ($id > 0) {
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET name='$name', phone='$phone', login='$login', password='$hashed' WHERE id=$id";
        } else {
            $sql = "UPDATE users SET name='$name', phone='$phone', login='$login' WHERE id=$id";
        }
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (name, phone, login, password, role) VALUES ('$name', '$phone', '$login', '$hashed', 'cashier')";
    }

    if ($conn->query($sql)) {
        $success = "Ходим маълумотлари сақланди!";
    } else {
        if ($conn->errno === 1062) {
            $error = "Бундай логин мавжуд!";
        } else {
            $error = "Хатолик: " . $conn->error;
        }
    }
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($id == $_SESSION['user_id']) {
        $error = "Ўзингизни ўчира олмайсиз!";
    } else {
        $conn->query("DELETE FROM users WHERE id = $id");
        $success = "Ходим ўчирилди!";
    }
}

$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$edit_user = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $edit_user = $conn->query("SELECT * FROM users WHERE id = $id")->fetch_assoc();
}

render_header('Ходимлар (Кассирлар)');
?>

<div class="mb-4">
    <a href="?action=list" class="btn btn-outline-primary <?php echo $action === 'list' ? 'active' : ''; ?>">Рўйхат</a>
    <a href="?action=add" class="btn btn-outline-primary <?php echo $action === 'add' ? 'active' : ''; ?>">Янги қўшиш</a>
</div>

<?php if($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<?php if($action === 'list'): ?>
    <div class="card p-4">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Исми</th>
                        <th>Телефон</th>
                        <th>Логин</th>
                        <th class="text-end">Амаллар</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = $conn->query("SELECT * FROM users WHERE role = 'cashier' ORDER BY id DESC");
                    while($u = $users->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo $u['id']; ?></td>
                        <td><?php echo $u['name']; ?></td>
                        <td><?php echo $u['phone']; ?></td>
                        <td><code><?php echo $u['login']; ?></code></td>
                        <td class="text-end">
                            <a href="?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> Танрирлаш</a>
                            <a href="?delete=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Ўчирмоқчимисиз?')"><i class="bi bi-trash"></i></a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php else: ?>
    <div class="card p-4">
        <h5 class="fw-bold mb-4"><?php echo $action === 'edit' ? 'Ходимни таҳрирлаш' : 'Янги ходим қўшиш'; ?></h5>
        <form method="POST">
            <input type="hidden" name="id" value="<?php echo $edit_user ? $edit_user['id'] : ''; ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Исми</label>
                    <input type="text" name="name" class="form-control" value="<?php echo $edit_user ? $edit_user['name'] : ''; ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Телефон</label>
                    <input type="text" name="phone" class="form-control" value="<?php echo $edit_user ? $edit_user['phone'] : ''; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Логин</label>
                    <input type="text" name="login" class="form-control" value="<?php echo $edit_user ? $edit_user['login'] : ''; ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Пароль <?php echo $action === 'edit' ? '(Бўш қолдирилса ўзгармайди)' : ''; ?></label>
                    <input type="password" name="password" class="form-control" <?php echo $action === 'add' ? 'required' : ''; ?>>
                </div>
            </div>
            <button type="submit" name="save_user" class="btn btn-primary mt-4 px-5">САҚЛАШ</button>
        </form>
    </div>
<?php endif; ?>

<?php render_footer(); ?>
