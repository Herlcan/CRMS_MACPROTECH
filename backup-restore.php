<?php
include 'src/db/connection.php';
include 'auth_check.php';
require_once __DIR__ . '/src/handlers/activity_log_helper.php';
require_once __DIR__ . '/src/handlers/database_backup_helpers.php';

if (($_SESSION['role'] ?? '') !== 'Administrator') {
    audit_authorization_failure($conn, 'backup and restore page');
    $_SESSION['dialog_flash'] = [
        'type' => 'error',
        'title' => 'Backup Restricted',
        'message' => 'Only administrators can access database backup and restore.'
    ];
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['backup_action'] ?? '';

    if (!verify_csrf_token_or_audit($conn, 'database backup and restore')) {
        $_SESSION['dialog_flash'] = [
            'type' => 'error',
            'title' => 'Security Check Failed',
            'message' => 'Your form session expired. Please try again.'
        ];
        header('Location: backup-restore.php');
        exit();
    }

    if ($action === 'download') {
        log_activity($conn, 'Downloaded database backup');
        database_backup_send($conn);
    }

    if ($action === 'restore') {
        try {
            log_security_event($conn, 'Started database restore from uploaded backup');
            $executed = database_restore_from_uploaded_file($conn, $_FILES['backup_file'] ?? []);
            log_security_event($conn, "Completed database restore ({$executed} SQL statements)");
            $_SESSION['dialog_flash'] = [
                'type' => 'success',
                'title' => 'Database Restored',
                'message' => "Backup restored successfully. {$executed} SQL statements were executed."
            ];
        } catch (Exception $e) {
            log_security_event($conn, 'Database restore failed: ' . $e->getMessage());
            $_SESSION['dialog_flash'] = [
                'type' => 'error',
                'title' => 'Restore Failed',
                'message' => $e->getMessage()
            ];
        }

        header('Location: backup-restore.php');
        exit();
    }

    $_SESSION['dialog_flash'] = [
        'type' => 'error',
        'title' => 'Invalid Backup Action',
        'message' => 'Please choose a valid backup or restore action.'
    ];
    header('Location: backup-restore.php');
    exit();
}

include 'header.php';
include 'sidebar.php';
?>

<div class="mobile-menu-overlay"></div>

<div class="main-container">
    <div class="pd-ltr-20 xs-pd-20-10">
        <div class="min-height-200px">
            <div class="page-header settings-page-header">
                <div class="row">
                    <div class="col-md-6 col-sm-12">
                        <div class="title">
                            <h4>Backup And Restore</h4>
                        </div>
                        <nav aria-label="breadcrumb" role="navigation">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Backup And Restore</li>
                            </ol>
                        </nav>
                    </div>
                    <div class="col-md-6 col-sm-12 settings-header-actions">
                        <a href="settings.php" class="btn btn-secondary btn-sm">Settings</a>
                        <a href="reports.php" class="btn btn-secondary btn-sm">Reports</a>
                    </div>
                </div>
            </div>

            <div class="settings-grid">
                <section class="card-box settings-panel">
                    <div class="settings-panel-heading">
                        <div>
                            <span class="settings-kicker">Disaster Recovery</span>
                            <h5>Export Database Backup</h5>
                        </div>
                        <span class="settings-status-pill">Administrator</span>
                    </div>
                    <p class="text-muted">
                        Download a full SQL backup of the current MACPROTECH database, including table structure and data.
                    </p>
                    <form method="POST">
                        <?= csrf_input() ?>
                        <input type="hidden" name="backup_action" value="download">
                        <button type="submit" class="btn btn-primary">Download SQL Backup</button>
                    </form>
                </section>

                <section class="card-box settings-panel">
                    <div class="settings-panel-heading">
                        <div>
                            <span class="settings-kicker">Business Continuity</span>
                            <h5>Restore Database Backup</h5>
                        </div>
                        <span class="settings-status-pill">High Impact</span>
                    </div>
                    <div class="alert alert-danger settings-compact-alert">
                        Restoring a backup replaces the current database contents. Download a fresh backup before restoring.
                    </div>
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_input() ?>
                        <input type="hidden" name="backup_action" value="restore">
                        <input type="hidden" name="MAX_FILE_SIZE" value="52428800">
                        <div class="form-group">
                            <label class="form-label" for="backupFile">SQL Backup File</label>
                            <input id="backupFile" class="form-control" type="file" name="backup_file" accept=".sql" required>
                        </div>
                        <button type="submit" class="btn btn-danger">Restore SQL Backup</button>
                    </form>
                </section>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
