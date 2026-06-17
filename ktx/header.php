<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($pageTitle) ? $pageTitle : 'Management System' ?> — VNU Campus</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<header class="site-header">
    <div style="display:flex; align-items:center; gap: 12px;">
        <svg viewBox="0 0 100 100" style="height: 42px; width: 42px; filter: drop-shadow(0 2px 4px rgba(216,112,147,0.2));" xmlns="http://www.w3.org/2000/svg">
            <circle cx="50" cy="50" r="46" fill="#FFF0F5" stroke="#D87093" stroke-width="4"/>
            <path d="M28 35 C 40 30, 45 42, 50 45 C 55 42, 60 30, 72 35 L 72 65 C 60 62, 55 75, 50 72 C 45 75, 40 62, 28 65 Z" fill="#D87093"/>
            <text x="50" y="85" font-family="Arial, sans-serif" font-size="14" font-weight="900" fill="#B8506E" text-anchor="middle">VNU</text>
        </svg>
        <div>
            <div class="logo">VNU Campus</div>
            <div class="subtitle">Management System</div>
        </div>
    </div>
    
    <?php include 'sidebar.php'; ?>
</header>