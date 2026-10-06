<?php
/** @var string $pageTitle @var string $bodyClass @var string $bodyStyle */
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="Diva Junction — Fashion &amp; Beauty deals for every diva this Big Billion Days.">
    <meta name="theme-color" content="#dae1f4">
    <link rel="icon" href="<?= e(asset(setting('logo'))) ?>">
    <link rel="preload" href="<?= e(base_url('assets/fonts/inter-latin.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?= e(base_url('assets/fonts/inter-latin-italic.woff2')) ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>"<?= $bodyStyle ? ' style="' . e($bodyStyle) . '"' : '' ?>>
