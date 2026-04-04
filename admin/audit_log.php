<?php
require_once 'layout.php';

function getBrowser($user_agent) {
    if (strpos($user_agent, 'Opera') || strpos($user_agent, 'OPR/')) return 'Opera';
    elseif (strpos($user_agent, 'Edge') || strpos($user_agent, 'Edg/')) return 'Edge';
    elseif (strpos($user_agent, 'Chrome')) return 'Chrome';
    elseif (strpos($user_agent, 'Safari')) return 'Safari';
    elseif (strpos($user_agent, 'Firefox')) return 'Firefox';
    elseif (strpos($user_agent, 'MSIE') || strpos($user_agent, 'Trident/7')) return 'Internet Explorer';
    return 'Unknown';
}

function getOS($user_agent) {
    $os_array = [
        '/windows nt 10/i'      =>  'Windows 10/11',
        '/windows nt 6.3/i'     =>  'Windows 8.1',
        '/windows nt 6.2/i'     =>  'Windows 8',
        '/windows nt 6.1/i'     =>  'Windows 7',
        '/macintosh|mac os x/i' =>  'Mac OS X',
        '/android/i'            =>  'Android',
        '/iphone/i'             =>  'iPhone',
        '/ipad/i'               =>  'iPad',
        '/linux/i'              =>  'Linux',
    ];
    foreach ($os_array as $regex => $value) {
        if (preg_match($regex, $user_agent)) return $value;
    }
    return 'Unknown OS';
}

render_header('Кириш тарихи');
?>

<div class="card p-4 border-0 shadow-sm" style="border-radius: 20px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="fw-bold mb-0"><i class="bi bi-shield-check text-primary me-2"></i> Тизимга кириш тарихи</h5>
        <span class="badge bg-light text-muted p-2 fw-normal">Охирги 50 та уриниш</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead class="table-light">
                <tr>
                    <th>Фойдаланувчи</th>
                    <th>Роль</th>
                    <th>Вақт</th>
                    <th>IP Манзил</th>
                    <th>Қурилма</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $logs = $conn->query("SELECT a.*, u.name, u.role FROM audit_log a JOIN users u ON a.user_id = u.id ORDER BY a.login_at DESC LIMIT 50");
                while($log = $logs->fetch_assoc()):
                    $os = getOS($log['user_agent']);
                    $browser = getBrowser($log['user_agent']);
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; font-weight: 600;">
                                <?php echo mb_substr($log['name'], 0, 1); ?>
                            </div>
                            <div>
                                <div class="fw-bold"><?php echo $log['name']; ?></div>
                                <div class="small text-muted">ID: <?php echo $log['user_id']; ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge <?php echo $log['role'] === 'admin' ? 'bg-danger' : 'bg-info'; ?> bg-opacity-10 <?php echo $log['role'] === 'admin' ? 'text-danger' : 'text-info'; ?> px-3">
                            <?php echo $log['role'] === 'admin' ? 'Админ' : 'Кассир'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="fw-bold"><?php echo date('H:i', strtotime($log['login_at'])); ?></div>
                        <div class="small text-muted"><?php echo date('d.m.Y', strtotime($log['login_at'])); ?></div>
                    </td>
                    <td>
                        <code class="text-dark bg-light px-2 py-1 rounded"><?php echo $log['ip_address']; ?></code>
                    </td>
                    <td>
                        <div class="small fw-bold text-dark"><i class="bi bi-laptop me-1"></i> <?php echo $os; ?></div>
                        <div class="small text-muted"><i class="bi bi-browser-chrome me-1"></i> <?php echo $browser; ?></div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php render_footer(); ?>
