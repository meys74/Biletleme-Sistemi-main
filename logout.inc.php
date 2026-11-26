<?php
    require_once 'config.inc.php';

    session_unset();
    session_destroy();

    header("location: /Biletleme-Sistemi/signin.php");
    exit();
