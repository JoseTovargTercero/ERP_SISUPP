-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-10-2025 a las 04:03:18
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `erp_g`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alertas`
--

CREATE TABLE `alertas` (
  `alerta_id` char(36) NOT NULL,
  `tipo_alerta` enum('REVISION_20_21','PROX_PARTO_117') NOT NULL,
  `periodo_id` char(36) DEFAULT NULL,
  `animal_id` char(36) DEFAULT NULL,
  `fecha_objetivo` date NOT NULL,
  `estado_alerta` enum('PENDIENTE','ENVIADA','ATENDIDA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
  `detalle` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `animales`
--

CREATE TABLE `animales` (
  `animal_id` char(36) NOT NULL,
  `identificador` varchar(100) NOT NULL,
  `sexo` enum('MACHO','HEMBRA') NOT NULL,
  `especie` enum('BOVINO','OVINO','CAPRINO','PORCINO','OTRO') NOT NULL,
  `raza` varchar(100) DEFAULT NULL,
  `color` varchar(80) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO','MUERTO','VENDIDO') NOT NULL DEFAULT 'ACTIVO',
  `etapa_productiva` enum('TERNERO','LEVANTE','CEBA','REPRODUCTOR','LACTANTE','SECA','GESTANTE','OTRO') DEFAULT NULL,
  `categoria` enum('CRIA','MADRE','PADRE','ENGORDE','REEMPLAZO','OTRO') DEFAULT NULL,
  `origen` enum('NACIMIENTO','COMPRA','TRASLADO','OTRO') NOT NULL DEFAULT 'OTRO',
  `madre_id` char(36) DEFAULT NULL,
  `padre_id` char(36) DEFAULT NULL,
  `fotografia_url` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` char(36) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `animales`
--

INSERT INTO `animales` (`animal_id`, `identificador`, `sexo`, `especie`, `raza`, `color`, `fecha_nacimiento`, `estado`, `etapa_productiva`, `categoria`, `origen`, `madre_id`, `padre_id`, `fotografia_url`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('9e9394fe-00ac-47ef-a3b7-e97bd3ac0c63', 'TEST-7496RV', 'MACHO', 'BOVINO', 'Criollo', 'Negro y Blanco', '2025-10-05', 'ACTIVO', 'CEBA', 'ENGORDE', 'NACIMIENTO', NULL, NULL, '/uploads/9e9394fe-00ac-47ef-a3b7-e97bd3ac0c63.png', '2025-10-05 12:12:02', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-05 20:04:00', '202b02fa-053d-48d5-a307-b52adb5525f4', NULL, NULL),
('ab636534-891c-43fb-b501-56177e79f621', 'TEST-7496RV2', 'HEMBRA', 'BOVINO', 'Criollo', NULL, '2025-10-07', 'ACTIVO', NULL, NULL, 'NACIMIENTO', NULL, NULL, '/uploads/ab636534-891c-43fb-b501-56177e79f621.png', '2025-10-07 10:14:15', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-07 10:14:15', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `animal_movimientos`
--

CREATE TABLE `animal_movimientos` (
  `animal_movimiento_id` char(36) NOT NULL,
  `animal_id` char(36) NOT NULL,
  `fecha_mov` date NOT NULL,
  `tipo_movimiento` enum('INGRESO','EGRESO','TRASLADO','VENTA','COMPRA','NACIMIENTO','MUERTE','OTRO') NOT NULL,
  `motivo` enum('TRASLADO','INGRESO','EGRESO','AISLAMIENTO','VENTA','OTRO') NOT NULL DEFAULT 'OTRO',
  `estado` enum('REGISTRADO','ANULADO') NOT NULL DEFAULT 'REGISTRADO',
  `finca_origen_id` char(36) DEFAULT NULL,
  `aprisco_origen_id` char(36) DEFAULT NULL,
  `area_origen_id` char(36) DEFAULT NULL,
  `recinto_id_origen` char(36) DEFAULT NULL,
  `finca_destino_id` char(36) DEFAULT NULL,
  `aprisco_destino_id` char(36) DEFAULT NULL,
  `area_destino_id` char(36) DEFAULT NULL,
  `recinto_id_destino` char(36) DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT NULL,
  `documento_ref` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` char(36) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `animal_pesos`
--

CREATE TABLE `animal_pesos` (
  `animal_peso_id` char(36) NOT NULL,
  `animal_id` char(36) NOT NULL,
  `fecha_peso` date NOT NULL,
  `peso_kg` decimal(10,3) NOT NULL,
  `metodo` varchar(50) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` char(36) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `animal_pesos`
--

INSERT INTO `animal_pesos` (`animal_peso_id`, `animal_id`, `fecha_peso`, `peso_kg`, `metodo`, `observaciones`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('e785f5ab-3f55-4b0e-aaaa-b7b7f3115bdc', '9e9394fe-00ac-47ef-a3b7-e97bd3ac0c63', '2025-10-05', 255.300, 'BALANZA', 'Ajuste de peso (recalibración)', '2025-10-05 12:12:03', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-05 12:12:03', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL),
('ff91ed4a-4d10-4992-8a77-b6628439f171', '9e9394fe-00ac-47ef-a3b7-e97bd3ac0c63', '2025-10-06', 10.000, 'balanza', '', '2025-10-05 19:50:47', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `animal_salud`
--

CREATE TABLE `animal_salud` (
  `animal_salud_id` char(36) NOT NULL,
  `animal_id` char(36) NOT NULL,
  `fecha_evento` date NOT NULL,
  `tipo_evento` enum('ENFERMEDAD','VACUNACION','DESPARASITACION','REVISION','TRATAMIENTO','OTRO') NOT NULL DEFAULT 'OTRO',
  `diagnostico` varchar(255) DEFAULT NULL,
  `severidad` enum('LEVE','MODERADA','GRAVE','NO_APLICA') DEFAULT NULL,
  `tratamiento` text DEFAULT NULL,
  `medicamento` varchar(255) DEFAULT NULL,
  `dosis` varchar(50) DEFAULT NULL,
  `via_administracion` varchar(50) DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT NULL,
  `estado` enum('ABIERTO','SEGUIMIENTO','CERRADO') NOT NULL DEFAULT 'ABIERTO',
  `proxima_revision` date DEFAULT NULL,
  `responsable` varchar(100) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` char(36) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `animal_salud`
--

INSERT INTO `animal_salud` (`animal_salud_id`, `animal_id`, `fecha_evento`, `tipo_evento`, `diagnostico`, `severidad`, `tratamiento`, `medicamento`, `dosis`, `via_administracion`, `costo`, `estado`, `proxima_revision`, `responsable`, `observaciones`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('142a7d3f-f439-4178-8f05-8f26ba3de22e', '9e9394fe-00ac-47ef-a3b7-e97bd3ac0c63', '2025-10-05', 'ENFERMEDAD', 'fsafa', 'NO_APLICA', 'fsafa', 'fsafa', 'fsa', 'fsa', 25.00, 'ABIERTO', '2025-10-05', 'fsa', 'fsa', '2025-10-05 19:51:23', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL, NULL, NULL),
('c041f6e1-0509-4b29-b587-f387f3dddea2', '9e9394fe-00ac-47ef-a3b7-e97bd3ac0c63', '2025-10-05', 'REVISION', 'Revisión general', 'LEVE', 'N/A', NULL, NULL, NULL, NULL, 'CERRADO', NULL, 'Encargado 1', 'Caso cerrado por estabilidad', '2025-10-05 12:12:03', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-05 12:12:03', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `animal_ubicaciones`
--

CREATE TABLE `animal_ubicaciones` (
  `animal_ubicacion_id` char(36) NOT NULL,
  `animal_id` char(36) NOT NULL,
  `finca_id` char(36) DEFAULT NULL,
  `aprisco_id` char(36) DEFAULT NULL,
  `area_id` char(36) DEFAULT NULL,
  `recinto_id` char(36) DEFAULT NULL,
  `fecha_desde` date NOT NULL,
  `fecha_hasta` date DEFAULT NULL,
  `motivo` enum('TRASLADO','INGRESO','EGRESO','AISLAMIENTO','VENTA','OTRO') NOT NULL DEFAULT 'OTRO',
  `estado` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  `observaciones` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `created_by` char(36) NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `animal_ubicaciones`
--

INSERT INTO `animal_ubicaciones` (`animal_ubicacion_id`, `animal_id`, `finca_id`, `aprisco_id`, `area_id`, `recinto_id`, `fecha_desde`, `fecha_hasta`, `motivo`, `estado`, `observaciones`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('1db3d6d8-b8eb-4895-9c20-47919881fa56', '9e9394fe-00ac-47ef-a3b7-e97bd3ac0c63', '06fcbfc8-ffc7-4956-b99d-77d879d772b7', '78059699-0f15-419e-89a8-fcc2697c4c97', '9927c9e7-d35a-4b1c-93b0-c078894cc9ef', NULL, '2025-10-06', '2025-10-31', 'TRASLADO', 'INACTIVA', '', '2025-10-05 20:19:04', '202b02fa-053d-48d5-a307-b52adb5525f4', NULL, NULL, NULL, NULL),
('41b65188-9e88-442e-baca-be16d85b712b', '9e9394fe-00ac-47ef-a3b7-e97bd3ac0c63', '06fcbfc8-ffc7-4956-b99d-77d879d772b7', '78059699-0f15-419e-89a8-fcc2697c4c97', '9927c9e7-d35a-4b1c-93b0-c078894cc9ef', NULL, '2025-10-06', '2025-10-31', 'INGRESO', 'ACTIVA', 'fsafa', '2025-10-05 20:17:45', '202b02fa-053d-48d5-a307-b52adb5525f4', NULL, NULL, NULL, NULL),
('5692f375-158f-4048-b465-6d3259a7a6d1', '9e9394fe-00ac-47ef-a3b7-e97bd3ac0c63', '06fcbfc8-ffc7-4956-b99d-77d879d772b7', '78059699-0f15-419e-89a8-fcc2697c4c97', '9927c9e7-d35a-4b1c-93b0-c078894cc9ef', NULL, '2025-10-06', NULL, 'TRASLADO', 'ACTIVA', 'fsafa', '2025-10-05 20:21:20', '202b02fa-053d-48d5-a307-b52adb5525f4', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `apriscos`
--

CREATE TABLE `apriscos` (
  `aprisco_id` char(36) NOT NULL,
  `finca_id` char(36) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `apriscos`
--

INSERT INTO `apriscos` (`aprisco_id`, `finca_id`, `nombre`, `estado`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('78059699-0f15-419e-89a8-fcc2697c4c97', '06fcbfc8-ffc7-4956-b99d-77d879d772b7', 'Aprisco Central Editado rd20er', 'ACTIVO', '2025-10-02 10:52:16', '78059699-0f15-419e-89a8-fcc2697c4c97', '2025-10-02 10:52:16', '78059699-0f15-419e-89a8-fcc2697c4c97', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `areas`
--

CREATE TABLE `areas` (
  `area_id` char(36) NOT NULL,
  `aprisco_id` char(36) NOT NULL,
  `nombre_personalizado` varchar(120) DEFAULT NULL,
  `tipo_area` enum('LEVANTE_CEBA','GESTACION','MATERNIDAD','REPRODUCCION','CHIQUERO') NOT NULL,
  `numeracion` varchar(50) DEFAULT NULL,
  `estado` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `areas`
--

INSERT INTO `areas` (`area_id`, `aprisco_id`, `nombre_personalizado`, `tipo_area`, `numeracion`, `estado`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('9927c9e7-d35a-4b1c-93b0-c078894cc9ef', '78059699-0f15-419e-89a8-fcc2697c4c97', 'Gestación-Edit-rd20er', 'GESTACION', '2', 'ACTIVA', '2025-10-02 10:52:16', '9927c9e7-d35a-4b1c-93b0-c078894cc9ef', '2025-10-02 10:52:17', '9927c9e7-d35a-4b1c-93b0-c078894cc9ef', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fincas`
--

CREATE TABLE `fincas` (
  `finca_id` char(36) NOT NULL,
  `nombre` varchar(120) NOT NULL,
  `ubicacion` varchar(255) DEFAULT NULL,
  `estado` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `fincas`
--

INSERT INTO `fincas` (`finca_id`, `nombre`, `ubicacion`, `estado`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('06fcbfc8-ffc7-4956-b99d-77d879d772b7', 'Finca Demo Editada rd20er', 'Coordenadas XYZ, Municipio AB', 'ACTIVA', '2025-10-02 10:52:16', '06fcbfc8-ffc7-4956-b99d-77d879d772b7', '2025-10-04 09:43:03', '06fcbfc8-ffc7-4956-b99d-77d879d772b7', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `menu`
--

CREATE TABLE `menu` (
  `menu_id` char(36) NOT NULL,
  `categoria` enum('area','finca','aprisco','reporte_dano','montas','partos','animales','alertas','usuarios','respaldos') DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  `icono` varchar(255) DEFAULT NULL,
  `user_level` int(11) NOT NULL DEFAULT 0,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `menu`
--

INSERT INTO `menu` (`menu_id`, `categoria`, `nombre`, `url`, `icono`, `user_level`, `orden`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('1be82974-a797-4bea-aae1-0d7112727ec4', 'animales', 'Gestión de Rebaño', 'animales', 'mdi mdi-sheep', 1, 4, '2025-10-05 16:54:24', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-05 17:17:58', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL),
('25d17a58-3186-48ed-81cc-8d396074b62d', 'usuarios', 'Modulos', 'modulos', 'mdi mdi-view-module', 0, 2, '2025-10-04 11:28:50', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-05 17:19:35', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL),
('35f8606a-a133-11f0-a92b-74d02b268d93', 'usuarios', 'Usuarios', 'users', 'mdi mdi-account-group', 0, 1, NULL, NULL, '2025-10-05 17:19:29', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL),
('6d583c24-a39e-11f0-8f58-2a144d1110c0', 'animales', 'Registro de montas', 'montas', 'mdi mdi-reproduction', 0, 0, NULL, NULL, NULL, NULL, NULL, NULL),
('920a038d-e341-4c61-9915-d35fb41d1a6b', 'area', 'Fincas', 'fincas', 'mdi mdi-office-building-marker', 1, 3, '2025-10-04 10:41:51', '920a038d-e341-4c61-9915-d35fb41d1a6b', '2025-10-12 21:31:16', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL),
('95765136-0404-4810-8dc4-5b38751c8522', 'partos', 'asdasd', 'https://github.com/jesuszapataDev/digital-signature-form.git', '0', 1, 0, '2025-10-04 10:05:19', '95765136-0404-4810-8dc4-5b38751c8522', '2025-10-04 10:12:35', '95765136-0404-4810-8dc4-5b38751c8522', '2025-10-04 10:12:40', '95765136-0404-4810-8dc4-5b38751c8522'),
('ba3b2b7e-a7d8-11f0-872b-00e04cf70151', 'usuarios', 'Gestor de Sesiones', 'sesiones', 'mdi mdi-account', 0, 3, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `montas`
--

CREATE TABLE `montas` (
  `monta_id` char(36) NOT NULL,
  `periodo_id` char(36) NOT NULL,
  `numero_monta` tinyint(3) UNSIGNED NOT NULL,
  `fecha_monta` datetime NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `montas`
--

INSERT INTO `montas` (`monta_id`, `periodo_id`, `numero_monta`, `fecha_monta`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('4165a60c-712a-49ca-8d51-2f58d1189179', '2efa8a7b-4fce-40c9-b71a-0e6af178df9f', 6, '2025-10-07 00:00:00', '2025-10-07 10:48:36', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-07 10:48:36', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-07 10:48:36', 'd7518474-2d2f-4634-823f-71936565c110');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `partos`
--

CREATE TABLE `partos` (
  `parto_id` char(36) NOT NULL,
  `periodo_id` char(36) NOT NULL,
  `fecha_parto` date NOT NULL,
  `crias_machos` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `crias_hembras` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `peso_promedio_kg` decimal(5,2) DEFAULT NULL,
  `estado_parto` enum('NORMAL','DISTOCIA','MUERTE_PERINATAL','OTRO') NOT NULL DEFAULT 'NORMAL',
  `observaciones` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `partos`
--

INSERT INTO `partos` (`parto_id`, `periodo_id`, `fecha_parto`, `crias_machos`, `crias_hembras`, `peso_promedio_kg`, `estado_parto`, `observaciones`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('2adba12c-538a-4452-b907-ffe9d2c72f8e', '2efa8a7b-4fce-40c9-b71a-0e6af178df9f', '2025-10-07', 1, 1, 3.60, 'DISTOCIA', 'Parto actualizado (test)', '2025-10-07 10:48:36', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-07 10:48:36', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-07 10:48:36', 'd7518474-2d2f-4634-823f-71936565c110');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `periodos_servicio`
--

CREATE TABLE `periodos_servicio` (
  `periodo_id` char(36) NOT NULL,
  `hembra_id` char(36) NOT NULL,
  `verraco_id` char(36) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `estado_periodo` enum('ABIERTO','CERRADO') NOT NULL DEFAULT 'ABIERTO',
  `created_at` datetime DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `periodos_servicio`
--

INSERT INTO `periodos_servicio` (`periodo_id`, `hembra_id`, `verraco_id`, `fecha_inicio`, `observaciones`, `estado_periodo`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('2efa8a7b-4fce-40c9-b71a-0e6af178df9f', 'ab636534-891c-43fb-b501-56177e79f621', '9e9394fe-00ac-47ef-a3b7-e97bd3ac0c63', '2025-10-07', 'Observación actualizada (test)', 'CERRADO', '2025-10-07 10:48:36', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-07 10:48:36', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-07 10:48:36', 'd7518474-2d2f-4634-823f-71936565c110'),
('334e4ae9-b35d-4b8b-bc35-9f4faa5c66cd', '9e9394fe-00ac-47ef-a3b7-e97bd3ac0c63', 'ab636534-891c-43fb-b501-56177e79f621', '2025-10-04', 'Registro', 'ABIERTO', '2025-10-08 03:45:38', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recintos`
--

CREATE TABLE `recintos` (
  `recinto_id` char(36) NOT NULL,
  `area_id` char(36) NOT NULL,
  `codigo_recinto` varchar(50) NOT NULL,
  `capacidad` smallint(5) UNSIGNED DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `observaciones` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reportes_dano`
--

CREATE TABLE `reportes_dano` (
  `reporte_id` char(36) NOT NULL,
  `finca_id` char(36) DEFAULT NULL,
  `aprisco_id` char(36) DEFAULT NULL,
  `area_id` char(36) DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text NOT NULL,
  `criticidad` enum('BAJA','MEDIA','ALTA') NOT NULL DEFAULT 'BAJA',
  `estado_reporte` enum('ABIERTO','EN_PROCESO','CERRADO') NOT NULL DEFAULT 'ABIERTO',
  `fecha_reporte` datetime NOT NULL DEFAULT current_timestamp(),
  `reportado_por` char(36) DEFAULT NULL,
  `solucionado_por` char(36) DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `reportes_dano`
--

INSERT INTO `reportes_dano` (`reporte_id`, `finca_id`, `aprisco_id`, `area_id`, `titulo`, `descripcion`, `criticidad`, `estado_reporte`, `fecha_reporte`, `reportado_por`, `solucionado_por`, `fecha_cierre`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('def09c3d-e0e5-48a3-b53a-8953f724a6d9', '06fcbfc8-ffc7-4956-b99d-77d879d772b7', '78059699-0f15-419e-89a8-fcc2697c4c97', '9927c9e7-d35a-4b1c-93b0-c078894cc9ef', 'Daño en bebedero rd20er', 'Fuga intermitente; requiere cambio de manguera.', 'ALTA', 'EN_PROCESO', '2025-10-02 10:52:17', NULL, NULL, '2025-10-02 10:52:17', '2025-10-02 10:52:17', 'def09c3d-e0e5-48a3-b53a-8953f724a6d9', '2025-10-05 19:52:21', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `revisiones_servicio`
--

CREATE TABLE `revisiones_servicio` (
  `revision_id` char(36) NOT NULL,
  `periodo_id` char(36) NOT NULL,
  `ciclo_control` tinyint(3) UNSIGNED NOT NULL DEFAULT 1,
  `fecha_programada` date NOT NULL,
  `fecha_realizada` date DEFAULT NULL,
  `resultado` enum('ENTRO_EN_CELO','SOSPECHA_PREÑEZ','CONFIRMADA_PREÑEZ') DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `session_config`
--

CREATE TABLE `session_config` (
  `config_id` int(11) NOT NULL,
  `timeout_minutes` int(11) NOT NULL DEFAULT 30,
  `allow_ip_change` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `session_config`
--

INSERT INTO `session_config` (`config_id`, `timeout_minutes`, `allow_ip_change`, `updated_at`, `updated_by`) VALUES
(1, 15, 0, '2025-07-16 09:13:28', '3');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `session_management`
--

CREATE TABLE `session_management` (
  `session_id` char(36) NOT NULL,
  `user_id` char(36) DEFAULT NULL,
  `user_name` varchar(100) DEFAULT NULL,
  `user_type` enum('Administrator','Usuario') NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `login_time` datetime NOT NULL,
  `logout_time` datetime DEFAULT NULL,
  `inactivity_duration` varchar(255) DEFAULT NULL,
  `login_success` tinyint(1) NOT NULL DEFAULT 1,
  `failure_reason` varchar(255) DEFAULT NULL,
  `session_status` enum('active','closed','expired','failed','kicked') NOT NULL DEFAULT 'active',
  `ip_address` varchar(45) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `region` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `zipcode` varchar(20) DEFAULT NULL,
  `coordinates` varchar(50) DEFAULT NULL,
  `hostname` varchar(100) DEFAULT NULL,
  `os` varchar(100) DEFAULT NULL,
  `browser` varchar(100) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `device_id` varchar(100) DEFAULT NULL,
  `device_type` tinyint(1) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `session_management`
--

INSERT INTO `session_management` (`session_id`, `user_id`, `user_name`, `user_type`, `full_name`, `login_time`, `logout_time`, `inactivity_duration`, `login_success`, `failure_reason`, `session_status`, `ip_address`, `city`, `region`, `country`, `zipcode`, `coordinates`, `hostname`, `os`, `browser`, `user_agent`, `device_id`, `device_type`, `created_at`) VALUES
('313aa437-66f1-40d1-a6f7-82baf418e3f4', '202b02fa-053d-48d5-a307-b52adb5525f4', 'moisescelis21@gmail.com', 'Administrator', 'Moises', '2025-10-12 21:52:25', NULL, NULL, 0, 'Contraseña incorrecta', 'failed', '::1', 'Unknown', 'Unknown', 'Unknown', 'Unknown', '0.0,0.0', 'DESKTOP-92VMM39', 'Windows 10', 'Google Chrome', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, 0, '2025-10-12 21:52:25'),
('763ab6d5-7e6d-48da-bf67-9e365f373a6d', '202b02fa-053d-48d5-a307-b52adb5525f4', 'moisescelis21@gmail.com', 'Administrator', 'Moises', '2025-10-12 21:45:09', NULL, NULL, 1, NULL, 'active', '::1', 'Unknown', 'Unknown', 'Unknown', 'Unknown', '0.0,0.0', 'DESKTOP-92VMM39', 'Windows 10', 'Google Chrome', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, 0, '2025-10-12 21:45:09'),
('94f63bd1-a7a7-4d31-9765-a253a11aa160', '202b02fa-053d-48d5-a307-b52adb5525f4', 'moisescelis21@gmail.com', 'Administrator', 'Moises', '2025-10-12 21:52:27', NULL, NULL, 1, NULL, 'active', '::1', 'Unknown', 'Unknown', 'Unknown', 'Unknown', '0.0,0.0', 'DESKTOP-92VMM39', 'Windows 10', 'Google Chrome', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, 0, '2025-10-12 21:52:27'),
('bbc5a74b-4907-441d-9c48-5b20b48cdfae', '202b02fa-053d-48d5-a307-b52adb5525f4', 'moisescelis21@gmail.com', 'Administrator', 'Moises', '2025-10-12 21:56:48', NULL, NULL, 1, NULL, 'active', '::1', 'Unknown', 'Unknown', 'Unknown', 'Unknown', '0.0,0.0', 'DESKTOP-92VMM39', 'Windows 10', 'Google Chrome', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', NULL, 0, '2025-10-12 21:56:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `system_users`
--

CREATE TABLE `system_users` (
  `user_id` char(36) NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `contrasena` varchar(255) DEFAULT NULL,
  `nivel` int(11) DEFAULT NULL,
  `estado` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `system_users`
--

INSERT INTO `system_users` (`user_id`, `nombre`, `email`, `contrasena`, `nivel`, `estado`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('07d9bff2-7493-4695-92e0-1ca74a48db06', 'Luis Aguirrin', 'aguirrin@gmail.com', '$2y$10$GiHdtU89HFwKXHVYwWFmGeZzLIWjFjpwIYm0OyHGazFJyQmh145ky', 1, 1, '2025-10-05 16:08:11', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL, NULL, NULL),
('1', 'Fatima Gomez', 'fatimagomezpd@gmail.com', '$2y$10$EyP1MOY39kuw4uREdk7ao.UUzQ10YNIZ95IZLM70MUPo5J6YzEBVG', 1, 1, NULL, NULL, NULL, NULL, '2025-10-05 16:14:00', 'd7518474-2d2f-4634-823f-71936565c110'),
('202b02fa-053d-48d5-a307-b52adb5525f4', 'Moises', 'moisescelis21@gmail.com', '$2y$10$Ob9iRVKPw.DiqPASkyUibOERwWkE7PMQaSsmqkDFYc5iLvXdJqXle', 0, 1, '2025-10-05 19:52:49', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL, NULL, NULL),
('35', 'Ricardo', 'jose.2710.ricardo@gmail.com', '$2y$10$xTVUq4JFTu6S7z7pCZtWTuC8ukfj0r3jsFk7Nw62TtU1WtnZ87MDm', 1, 1, NULL, NULL, '2025-10-04 10:49:45', '35', NULL, NULL),
('40', 'Hilson Martinez', 'martinezhilson8@gmail.com', '$2y$10$3mEuUd1/uIn0nNx3.qBoYeGeDc7WAXsEUvldqHX1WNaWusgVwnu9e', 2, 1, NULL, NULL, NULL, NULL, '2025-10-05 16:14:03', 'd7518474-2d2f-4634-823f-71936565c110'),
('42', 'ASDRUBAL MARTINEZs', 'asdrubalmartinez486@gmail.com', '$2y$10$yUnVJhDWX6xkB4BEch2HPeAbEGNA311qcjs1DXVIsTmaah6jzHwzW', 2, 1, NULL, NULL, '2025-10-04 09:49:28', '42', '2025-10-05 16:13:58', 'd7518474-2d2f-4634-823f-71936565c110'),
('43', 'user ejecucion', 'magomagel1983@gmail.com', '$2y$10$EyP1MOY39kuw4uREdk7ao.UUzQ10YNIZ95IZLM70MUPo5J6YzEBVG', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
('45', 'user proyectos', 'proyecto@correo.com', '$2y$10$EyP1MOY39kuw4uREdk7ao.UUzQ10YNIZ95IZLM70MUPo5J6YzEBVG', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
('d7518474-2d2f-4634-823f-71936565c110', 'Jesus Zapatin', 'zapatin@gmail.com', '$2y$10$H.Y1gpOJFRMCObm0rNPZ4uHfis56lTpKacsf1hrvWvwefwDJHNujq', 0, 1, '2025-10-04 10:50:51', 'd7518474-2d2f-4634-823f-71936565c110', '2025-10-05 15:46:18', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users_permisos`
--

CREATE TABLE `users_permisos` (
  `users_permisos_id` char(36) NOT NULL,
  `user_id` char(36) NOT NULL,
  `menu_id` char(36) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users_permisos`
--

INSERT INTO `users_permisos` (`users_permisos_id`, `user_id`, `menu_id`, `created_at`, `created_by`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
('5309a0b7-a133-11f0-a92b-74d02b268d93', 'd7518474-2d2f-4634-823f-71936565c110', '35f8606a-a133-11f0-a92b-74d02b268d93', NULL, NULL, NULL, NULL, NULL, NULL),
('702e6d19-c932-409f-b20a-f25c14774164', '07d9bff2-7493-4695-92e0-1ca74a48db06', '920a038d-e341-4c61-9915-d35fb41d1a6b', '2025-10-05 16:08:48', 'd7518474-2d2f-4634-823f-71936565c110', NULL, NULL, NULL, NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alertas`
--
ALTER TABLE `alertas`
  ADD PRIMARY KEY (`alerta_id`),
  ADD KEY `fk_alerta_periodo` (`periodo_id`),
  ADD KEY `fk_alerta_animal` (`animal_id`),
  ADD KEY `idx_alertas_objetivo` (`tipo_alerta`,`estado_alerta`,`fecha_objetivo`);

--
-- Indices de la tabla `animales`
--
ALTER TABLE `animales`
  ADD PRIMARY KEY (`animal_id`),
  ADD UNIQUE KEY `uq_animales_identificador` (`identificador`),
  ADD KEY `fk_animal_madre` (`madre_id`),
  ADD KEY `fk_animal_padre` (`padre_id`),
  ADD KEY `idx_animales_especie` (`especie`),
  ADD KEY `idx_animales_sexo` (`sexo`),
  ADD KEY `idx_animales_estado` (`estado`),
  ADD KEY `idx_animales_nac` (`fecha_nacimiento`);

--
-- Indices de la tabla `animal_movimientos`
--
ALTER TABLE `animal_movimientos`
  ADD PRIMARY KEY (`animal_movimiento_id`),
  ADD KEY `idx_am_animal` (`animal_id`),
  ADD KEY `idx_am_fecha` (`fecha_mov`),
  ADD KEY `idx_am_tipo` (`tipo_movimiento`),
  ADD KEY `idx_am_estado` (`estado`),
  ADD KEY `idx_am_origen_finca` (`finca_origen_id`),
  ADD KEY `idx_am_origen_aprisco` (`aprisco_origen_id`),
  ADD KEY `idx_am_origen_area` (`area_origen_id`),
  ADD KEY `idx_am_dest_finca` (`finca_destino_id`),
  ADD KEY `idx_am_dest_aprisco` (`aprisco_destino_id`),
  ADD KEY `idx_am_dest_area` (`area_destino_id`),
  ADD KEY `idx_am_recinto_origen` (`recinto_id_origen`),
  ADD KEY `idx_am_recinto_dest` (`recinto_id_destino`);

--
-- Indices de la tabla `animal_pesos`
--
ALTER TABLE `animal_pesos`
  ADD PRIMARY KEY (`animal_peso_id`),
  ADD UNIQUE KEY `uq_animal_peso_fecha` (`animal_id`,`fecha_peso`),
  ADD KEY `idx_animal_pesos_animal` (`animal_id`),
  ADD KEY `idx_animal_pesos_fecha` (`fecha_peso`);

--
-- Indices de la tabla `animal_salud`
--
ALTER TABLE `animal_salud`
  ADD PRIMARY KEY (`animal_salud_id`),
  ADD KEY `idx_salud_animal` (`animal_id`),
  ADD KEY `idx_salud_fecha` (`fecha_evento`),
  ADD KEY `idx_salud_estado` (`estado`),
  ADD KEY `idx_salud_tipo` (`tipo_evento`);

--
-- Indices de la tabla `animal_ubicaciones`
--
ALTER TABLE `animal_ubicaciones`
  ADD PRIMARY KEY (`animal_ubicacion_id`),
  ADD KEY `idx_au_animal` (`animal_id`),
  ADD KEY `idx_au_finca` (`finca_id`),
  ADD KEY `idx_au_aprisc` (`aprisco_id`),
  ADD KEY `idx_au_area` (`area_id`),
  ADD KEY `idx_au_desde` (`fecha_desde`),
  ADD KEY `idx_au_hasta` (`fecha_hasta`),
  ADD KEY `idx_au_recinto` (`recinto_id`);

--
-- Indices de la tabla `apriscos`
--
ALTER TABLE `apriscos`
  ADD PRIMARY KEY (`aprisco_id`),
  ADD KEY `idx_aprisco_finca` (`finca_id`);

--
-- Indices de la tabla `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`area_id`),
  ADD UNIQUE KEY `uq_area_tipo_num` (`aprisco_id`,`tipo_area`,`numeracion`),
  ADD UNIQUE KEY `uq_area_nombre_personalizado` (`aprisco_id`,`nombre_personalizado`),
  ADD KEY `idx_area_aprisco` (`aprisco_id`);

--
-- Indices de la tabla `fincas`
--
ALTER TABLE `fincas`
  ADD PRIMARY KEY (`finca_id`);

--
-- Indices de la tabla `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`menu_id`);

--
-- Indices de la tabla `montas`
--
ALTER TABLE `montas`
  ADD PRIMARY KEY (`monta_id`),
  ADD UNIQUE KEY `uq_monta_periodo_num` (`periodo_id`,`numero_monta`);

--
-- Indices de la tabla `partos`
--
ALTER TABLE `partos`
  ADD PRIMARY KEY (`parto_id`),
  ADD KEY `fk_parto_periodo` (`periodo_id`);

--
-- Indices de la tabla `periodos_servicio`
--
ALTER TABLE `periodos_servicio`
  ADD PRIMARY KEY (`periodo_id`),
  ADD KEY `fk_ps_hembra` (`hembra_id`),
  ADD KEY `fk_ps_verraco` (`verraco_id`);

--
-- Indices de la tabla `recintos`
--
ALTER TABLE `recintos`
  ADD PRIMARY KEY (`recinto_id`),
  ADD UNIQUE KEY `uq_area_codigo_recinto` (`area_id`,`codigo_recinto`);

--
-- Indices de la tabla `reportes_dano`
--
ALTER TABLE `reportes_dano`
  ADD PRIMARY KEY (`reporte_id`),
  ADD KEY `fk_rep_aprisco` (`aprisco_id`),
  ADD KEY `fk_rep_area` (`area_id`),
  ADD KEY `idx_rep_refs` (`finca_id`,`aprisco_id`,`area_id`),
  ADD KEY `idx_rep_estado_fecha` (`estado_reporte`,`fecha_reporte`),
  ADD KEY `idx_rep_criticidad` (`criticidad`);

--
-- Indices de la tabla `revisiones_servicio`
--
ALTER TABLE `revisiones_servicio`
  ADD PRIMARY KEY (`revision_id`),
  ADD KEY `fk_rev_periodo` (`periodo_id`),
  ADD KEY `idx_revisiones_programada` (`fecha_programada`,`resultado`);

--
-- Indices de la tabla `session_config`
--
ALTER TABLE `session_config`
  ADD PRIMARY KEY (`config_id`);

--
-- Indices de la tabla `session_management`
--
ALTER TABLE `session_management`
  ADD PRIMARY KEY (`session_id`);

--
-- Indices de la tabla `system_users`
--
ALTER TABLE `system_users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `usuario` (`email`);

--
-- Indices de la tabla `users_permisos`
--
ALTER TABLE `users_permisos`
  ADD PRIMARY KEY (`users_permisos_id`),
  ADD UNIQUE KEY `uq_user_menu` (`user_id`,`menu_id`),
  ADD KEY `fk_up_menu` (`menu_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `session_config`
--
ALTER TABLE `session_config`
  MODIFY `config_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `alertas`
--
ALTER TABLE `alertas`
  ADD CONSTRAINT `fk_alerta_animal` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`animal_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_alerta_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos_servicio` (`periodo_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `animales`
--
ALTER TABLE `animales`
  ADD CONSTRAINT `fk_animal_madre` FOREIGN KEY (`madre_id`) REFERENCES `animales` (`animal_id`),
  ADD CONSTRAINT `fk_animal_padre` FOREIGN KEY (`padre_id`) REFERENCES `animales` (`animal_id`);

--
-- Filtros para la tabla `animal_movimientos`
--
ALTER TABLE `animal_movimientos`
  ADD CONSTRAINT `fk_am_adest` FOREIGN KEY (`aprisco_destino_id`) REFERENCES `apriscos` (`aprisco_id`),
  ADD CONSTRAINT `fk_am_animal` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`animal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_am_aorig` FOREIGN KEY (`aprisco_origen_id`) REFERENCES `apriscos` (`aprisco_id`),
  ADD CONSTRAINT `fk_am_ardest` FOREIGN KEY (`area_destino_id`) REFERENCES `areas` (`area_id`),
  ADD CONSTRAINT `fk_am_arorig` FOREIGN KEY (`area_origen_id`) REFERENCES `areas` (`area_id`),
  ADD CONSTRAINT `fk_am_fdest` FOREIGN KEY (`finca_destino_id`) REFERENCES `fincas` (`finca_id`),
  ADD CONSTRAINT `fk_am_forig` FOREIGN KEY (`finca_origen_id`) REFERENCES `fincas` (`finca_id`),
  ADD CONSTRAINT `fk_am_recinto_destino` FOREIGN KEY (`recinto_id_destino`) REFERENCES `recintos` (`recinto_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_am_recinto_origen` FOREIGN KEY (`recinto_id_origen`) REFERENCES `recintos` (`recinto_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `animal_pesos`
--
ALTER TABLE `animal_pesos`
  ADD CONSTRAINT `fk_animal_pesos_animal` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`animal_id`);

--
-- Filtros para la tabla `animal_salud`
--
ALTER TABLE `animal_salud`
  ADD CONSTRAINT `fk_animal_salud_animal` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`animal_id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `animal_ubicaciones`
--
ALTER TABLE `animal_ubicaciones`
  ADD CONSTRAINT `fk_au_animal` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`animal_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_au_aprisco` FOREIGN KEY (`aprisco_id`) REFERENCES `apriscos` (`aprisco_id`),
  ADD CONSTRAINT `fk_au_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`),
  ADD CONSTRAINT `fk_au_finca` FOREIGN KEY (`finca_id`) REFERENCES `fincas` (`finca_id`),
  ADD CONSTRAINT `fk_au_recinto` FOREIGN KEY (`recinto_id`) REFERENCES `recintos` (`recinto_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `apriscos`
--
ALTER TABLE `apriscos`
  ADD CONSTRAINT `fk_aprisco_finca` FOREIGN KEY (`finca_id`) REFERENCES `fincas` (`finca_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `areas`
--
ALTER TABLE `areas`
  ADD CONSTRAINT `fk_area_aprisco` FOREIGN KEY (`aprisco_id`) REFERENCES `apriscos` (`aprisco_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `montas`
--
ALTER TABLE `montas`
  ADD CONSTRAINT `fk_monta_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos_servicio` (`periodo_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `partos`
--
ALTER TABLE `partos`
  ADD CONSTRAINT `fk_parto_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos_servicio` (`periodo_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `periodos_servicio`
--
ALTER TABLE `periodos_servicio`
  ADD CONSTRAINT `fk_ps_hembra` FOREIGN KEY (`hembra_id`) REFERENCES `animales` (`animal_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ps_verraco` FOREIGN KEY (`verraco_id`) REFERENCES `animales` (`animal_id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `recintos`
--
ALTER TABLE `recintos`
  ADD CONSTRAINT `fk_recinto_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `reportes_dano`
--
ALTER TABLE `reportes_dano`
  ADD CONSTRAINT `fk_rep_aprisco` FOREIGN KEY (`aprisco_id`) REFERENCES `apriscos` (`aprisco_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rep_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rep_finca` FOREIGN KEY (`finca_id`) REFERENCES `fincas` (`finca_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `revisiones_servicio`
--
ALTER TABLE `revisiones_servicio`
  ADD CONSTRAINT `fk_rev_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos_servicio` (`periodo_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `users_permisos`
--
ALTER TABLE `users_permisos`
  ADD CONSTRAINT `fk_up_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`menu_id`),
  ADD CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `system_users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
