// esto no seriviria de mucho
// En este caso habria que verificar si tiene acceso a la vista o controlador

if ($_SESSION["nivel"] == 2) {
    $archivoActual = basename($_SERVER['PHP_SELF']);
    $coincidencia = false;
    $archivo = false;
    foreach ($_SESSION["permisos"] as $key => $value) {
        $url_acceso = $value;
        $archivo = $value;

        if ($url_acceso == $archivoActual) {
            $coincidencia = true;
            $archivo = false;
        }
    }
    if ($coincidencia == false) {
        header("Location: ../../login/salir.php");
        exit;
    }
}
