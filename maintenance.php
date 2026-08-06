<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance - QuestBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .maintenance-card { max-width: 550px; border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .icon-circle { width: 90px; height: 90px; border-radius: 50%; background: #e3f2fd; color: #0d6efd; display: inline-flex; align-items: center; justify-content: center; font-size: 40px; }
    </style>
</head>
<body>
<div class="container text-center py-5">
    <div class="card maintenance-card mx-auto p-4 p-md-5">
        <div class="card-body">
            <div class="icon-circle mb-4">
                <i class="fas fa-tools"></i>
            </div>
            <h2 class="font-weight-bold mb-3">System Under Maintenance</h2>
            <p class="text-muted leading-relaxed mb-4">
                QuestBank is currently undergoing scheduled maintenance and system upgrades to improve our academic examination and analytics platform.
            </p>
            <div class="alert alert-info border-0 rounded-3 text-start small mb-4">
                <i class="fas fa-info-circle me-2"></i><strong>Notice:</strong> Student and teacher portals will return online shortly. Administrators may log in below.
            </div>
            <div class="d-flex justify-content-center gap-2">
                <a href="/login.php" class="btn btn-outline-primary"><i class="fas fa-user-shield me-1"></i>Admin Login</a>
                <button onclick="location.reload()" class="btn btn-primary"><i class="fas fa-sync me-1"></i>Refresh Status</button>
            </div>
        </div>
    </div>
</div>
</body>
</html>
