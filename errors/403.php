<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Access Denied - QuestBank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .error-card { max-width: 500px; border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .icon-circle { width: 80px; height: 80px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: inline-flex; align-items: center; justify-content: center; font-size: 36px; }
    </style>
</head>
<body>
<div class="container text-center py-5">
    <div class="card error-card mx-auto p-4 p-md-5">
        <div class="card-body">
            <div class="icon-circle mb-4">
                <i class="fas fa-user-slash"></i>
            </div>
            <h2 class="font-weight-bold text-danger mb-2">403 Forbidden</h2>
            <h5 class="text-secondary mb-3">Access Denied</h5>
            <p class="text-muted leading-relaxed mb-4 small">
                You do not have permission to access this resource. Please verify your account privileges or log in with an authorized account.
            </p>
            <div class="d-flex justify-content-center gap-2">
                <a href="/index.php" class="btn btn-primary"><i class="fas fa-home me-1"></i>Return to Portal</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
