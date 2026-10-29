<?php
require_once __DIR__ . '/../../config/app.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - <?= APP_ORG ?></title>
    
    <!-- Favicon Oficial CMCO -->
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    
    <!-- Bootstrap 5 y Bootstrap Icons Locales -->
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap-icons.min.css">
    
    <!-- Estilos Personalizados CMCO -->
    <link rel="stylesheet" href="assets/css/custom.css?v=<?= filemtime(__DIR__ . '/../../assets/css/custom.css') ?>">
</head>
<body>
