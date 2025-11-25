<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user is logged in and has super admin role
if (!isLoggedIn() || !hasRole('super_admin')) {
    redirectWith('../login.php', 'Unauthorized access', 'danger');
}

// Get all activities grouped by date
$conn = getConnection();
$stmt = $conn->prepare("SELECT
    a.*, u.username, u.role,
    DATE(a.created_at) as activity_date
    FROM activity_log a
    JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC");
$stmt->execute();
$all_activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group activities by date
$activities_by_date = [];
foreach ($all_activities as $activity) {
    $date = $activity['activity_date'];
    if (!isset($activities_by_date[$date])) {
        $activities_by_date[$date] = [];
    }
    $activities_by_date[$date][] = $activity;
}

// Pagination logic
$dates_per_page = 3;
$total_dates = count($activities_by_date);
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$total_pages = ceil($total_dates / $dates_per_page);

// Get the dates for current page
$date_keys = array_keys($activities_by_date);
$start_index = ($current_page - 1) * $dates_per_page;
$end_index = min($start_index + $dates_per_page, $total_dates);
$current_dates = array_slice($date_keys, $start_index, $dates_per_page, true);

// Filter activities for current page
$current_activities_by_date = [];
foreach ($current_dates as $date) {
    $current_activities_by_date[$date] = $activities_by_date[$date];
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Activity Log</h1>
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <?php if (empty($activities_by_date)): ?>
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-history fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No activities found</h5>
                        <p class="text-muted">Activity log is empty.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($current_activities_by_date as $date => $activities): ?>
                    <div class="activity-date-group mb-4">
                        <h5 class="text-white mb-3 p-2 rounded date-header">
                            <i class="fas fa-calendar-day"></i>
                            <?= date('l, F j, Y', strtotime($date)) ?>
                            <span class="badge badge-light ml-2 small"><?= count($activities) ?> activities</span>
                        </h5>
                        <div class="activity-list">
                            <?php foreach ($activities as $activity): ?>
                                <div class="activity-item mb-2 p-3 bg-light rounded activity-hover">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <span class="font-weight-bold text-primary mr-2">
                                                    <?= htmlspecialchars($activity['username']) ?>
                                                </span>
                                                <span class="badge badge-info">
                                                    <?= htmlspecialchars($activity['role']) ?>
                                                </span>
                                            </div>
                                            <div class="mb-2">
                                                <strong class="text-dark"><?= htmlspecialchars($activity['activity_type']) ?>:</strong>
                                                <span class="text-muted ml-1"><?= htmlspecialchars($activity['description']) ?></span>
                                            </div>
                                            <?php if (!empty($activity['ip_address'])): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-globe"></i> IP: <?= htmlspecialchars($activity['ip_address']) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-right">
                                            <small class="text-muted font-weight-bold">
                                                <?= date('h:i:s A', strtotime($activity['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center mt-4">
                        <nav aria-label="Activity log pagination">
                            <ul class="pagination">
                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="activity_log.php?page=<?= $current_page - 1 ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo; Previous</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                        <a class="page-link" href="activity_log.php?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="activity_log.php?page=<?= $current_page + 1 ?>" aria-label="Next">
                                            <span aria-hidden="true">Next &raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.activity-hover:hover {
    background-color: #e9ecef !important;
    transition: background-color 0.2s ease;
}

.date-header {
    background-color: #007bff;
}
</style>

<?php require_once '../includes/footer.php'; ?>
