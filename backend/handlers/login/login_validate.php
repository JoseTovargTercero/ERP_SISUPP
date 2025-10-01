<?php
ob_start();
include '../../config/db.php';

// Configurar seguridad de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
if (!empty($_SERVER['HTTPS'])) {
	ini_set('session.cookie_secure', 1);
}

$values = json_decode(file_get_contents('php://input'), true);
$email = $values['email'] ?? '';
$contrasena = $values['password'] ?? '';

if (empty($email) || empty($contrasena)) {
	echo json_encode(['success' => false, 'msg' => 'Email o contraseña vacíos']);
	exit;
}

if ($conexion->connect_error) {
	die("Error conexión BD: " . $conexion->connect_error);
}

// Solo seleccionar campos necesarios
$stmt = $conexion->prepare("
    SELECT id, nombre, nivel, contrasena, email
    FROM system_users 
    WHERE email = ? AND contrasena != '' AND estado = '1' 
    LIMIT 1
");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
	if (password_verify($contrasena, $row['contrasena'])) {

		session_start();
		session_regenerate_id(true);

		$_SESSION['id'] = $row['id'];
		$_SESSION['nombre'] = $row['nombre'];
		$_SESSION['nivel'] = $row['nivel'];
		$_SESSION['email'] = $row['email'];


		// Permisos si no es administrador
		if ($row['nivel'] != 1) {
			$permisos = [];
			$stmt_2 = $conexion->prepare("
                SELECT sup.id_item_menu, menu.dir 
                FROM system_users_permisos AS sup 
                LEFT JOIN menu ON menu.id = sup.id_item_menu
                WHERE id_user = ?
            ");
			$stmt_2->bind_param('i', $row['id']);
			$stmt_2->execute();
			$result2 = $stmt_2->get_result();
			while ($row_p = $result2->fetch_assoc()) {
				$permisos[$row_p['id_item_menu']] = $row_p['dir'];
			}
			$_SESSION['permisos'] = $permisos;
			$stmt_2->close();
		}


		echo json_encode([
			'success' => true,
			'msg' => 'Login exitoso'
		]);
	} else {
		echo json_encode(['success' => false, 'msg' => 'Verifique sus credenciales']);
	}
} else {
	echo json_encode(['success' => false, 'msg' => 'Verifique sus credenciales']);
}

$stmt->close();
ob_end_flush();
