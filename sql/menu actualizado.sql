-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 13-10-2025 a las 03:50:07
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
('95765136-0404-4810-8dc4-5b38751c8522', 'partos', 'asdasd', 'https://github.com/jesuszapataDev/digital-signature-form.git', '0', 1, 0, '2025-10-04 10:05:19', '95765136-0404-4810-8dc4-5b38751c8522', '2025-10-04 10:12:35', '95765136-0404-4810-8dc4-5b38751c8522', '2025-10-04 10:12:40', '95765136-0404-4810-8dc4-5b38751c8522');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`menu_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
