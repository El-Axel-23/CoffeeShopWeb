<?php

$host = "localhost";
$user = "u906011863_madison";
$pass = "t>hv@Sm6";
$db   = "u906011863_madison";

mysqli_report(MYSQLI_REPORT_OFF);

$conexion = mysqli_connect($host, $user, $pass, $db);

if (!$conexion) {
    error_log("Error de conexión: " . mysqli_connect_error());
    http_response_code(500);
    exit("No fue posible conectar con la base de datos.");
}

mysqli_set_charset($conexion, "utf8mb4");

?>