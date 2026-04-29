<?php
session_start();
if (isset($_SESSION['operarios_adicionales'])) {
    unset($_SESSION['operarios_adicionales']);
}
echo json_encode(['success' => true]);