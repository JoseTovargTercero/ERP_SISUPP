<?php
require_once '../sistema_global/config.php';
session_start();
session_destroy();
header('Location: ' . constant('URL'));