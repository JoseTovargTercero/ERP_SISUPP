-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 03-10-2025 a las 16:54:03
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
  `codigo_identificacion` varchar(80) NOT NULL,
  `fotografia_url` varchar(255) DEFAULT NULL,
  `raza` varchar(100) DEFAULT NULL,
  `sexo` enum('MACHO','HEMBRA') NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `peso_nacer_kg` decimal(5,2) DEFAULT NULL,
  `origen` enum('CRIA_INTERNA','COMPRA') NOT NULL,
  `estado_animal` enum('ACTIVO','VENDIDO','MUERTO','DESCARTADO') NOT NULL DEFAULT 'ACTIVO',
  `padre_id` char(36) DEFAULT NULL,
  `madre_id` char(36) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `animal_movimientos`
--

CREATE TABLE `animal_movimientos` (
  `movimiento_id` char(36) NOT NULL,
  `animal_id` char(36) NOT NULL,
  `tipo_movimiento` enum('ALTA','VENTA','MUERTE','BAJA','DESCARTE','CAMBIO_UBICACION','ASIGNACION_CODIGO','DESTETE') NOT NULL,
  `fecha_evento` datetime NOT NULL,
  `detalle` varchar(255) DEFAULT NULL,
  `finca_id` char(36) DEFAULT NULL,
  `aprisco_id` char(36) DEFAULT NULL,
  `area_id` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `animal_pesos`
--

CREATE TABLE `animal_pesos` (
  `peso_id` char(36) NOT NULL,
  `animal_id` char(36) NOT NULL,
  `fecha` date NOT NULL,
  `peso_kg` decimal(6,2) NOT NULL,
  `observaciones` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `animal_salud`
--

CREATE TABLE `animal_salud` (
  `salud_id` char(36) NOT NULL,
  `animal_id` char(36) NOT NULL,
  `fecha_diagnostico` date NOT NULL,
  `diagnostico` varchar(200) NOT NULL,
  `tratamiento` varchar(255) DEFAULT NULL,
  `estado_caso` enum('ABIERTO','SEGUIMIENTO','CERRADO') NOT NULL DEFAULT 'ABIERTO',
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `animal_ubicaciones`
--

CREATE TABLE `animal_ubicaciones` (
  `ubicacion_id` char(36) NOT NULL,
  `animal_id` char(36) NOT NULL,
  `finca_id` char(36) DEFAULT NULL,
  `aprisco_id` char(36) DEFAULT NULL,
  `area_id` char(36) DEFAULT NULL,
  `fecha_asignacion` datetime NOT NULL DEFAULT current_timestamp(),
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
('06fcbfc8-ffc7-4956-b99d-77d879d772b7', 'Finca Demo Editada rd20er', 'Coordenadas XYZ, Municipio ABC', 'ACTIVA', '2025-10-02 10:52:16', '06fcbfc8-ffc7-4956-b99d-77d879d772b7', '2025-10-02 10:52:16', '06fcbfc8-ffc7-4956-b99d-77d879d772b7', NULL, NULL);

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
  `created_at` datetime DEFAULT NULL,
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `montas`
--

CREATE TABLE `montas` (
  `monta_id` char(36) NOT NULL,
  `periodo_id` char(36) NOT NULL,
  `numero_monta` tinyint(3) UNSIGNED NOT NULL,
  `fecha_monta` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `observaciones` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `estado_periodo` enum('ABIERTO','CERRADO') NOT NULL DEFAULT 'ABIERTO'
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
('def09c3d-e0e5-48a3-b53a-8953f724a6d9', '06fcbfc8-ffc7-4956-b99d-77d879d772b7', '78059699-0f15-419e-89a8-fcc2697c4c97', '9927c9e7-d35a-4b1c-93b0-c078894cc9ef', 'Daño en bebedero rd20er', 'Fuga intermitente; requiere cambio de manguera.', 'ALTA', 'CERRADO', '2025-10-02 10:52:17', NULL, NULL, '2025-10-02 10:52:17', '2025-10-02 10:52:17', 'def09c3d-e0e5-48a3-b53a-8953f724a6d9', '2025-10-02 10:52:17', 'def09c3d-e0e5-48a3-b53a-8953f724a6d9', NULL, NULL);

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
('1', 'Fatima Gomez', 'fatimagomezpd@gmail.com', '$2y$10$EyP1MOY39kuw4uREdk7ao.UUzQ10YNIZ95IZLM70MUPo5J6YzEBVG', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
('35', 'Ricardo', 'jose.2710.ricardo@gmail.com', '$2y$10$azF/dOpnDs9sCTYiLEF7kO8612REFdjpk8Te.bih4BaNDSfhAw9MO', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
('40', 'Hilson Martinez', 'martinezhilson8@gmail.com', '$2y$10$3mEuUd1/uIn0nNx3.qBoYeGeDc7WAXsEUvldqHX1WNaWusgVwnu9e', 2, 1, NULL, NULL, NULL, NULL, NULL, NULL),
('42', 'ASDRUBAL MARTINEZ', 'asdrubalmartinez486@gmail.com', '$2y$10$yUnVJhDWX6xkB4BEch2HPeAbEGNA311qcjs1DXVIsTmaah6jzHwzW', 2, 1, NULL, NULL, NULL, NULL, NULL, NULL),
('43', 'user ejecucion', 'magomagel1983@gmail.com', '$2y$10$EyP1MOY39kuw4uREdk7ao.UUzQ10YNIZ95IZLM70MUPo5J6YzEBVG', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL),
('45', 'user proyectos', 'proyecto@correo.com', '$2y$10$EyP1MOY39kuw4uREdk7ao.UUzQ10YNIZ95IZLM70MUPo5J6YzEBVG', 1, 1, NULL, NULL, NULL, NULL, NULL, NULL);

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
  ADD UNIQUE KEY `codigo_identificacion` (`codigo_identificacion`),
  ADD KEY `fk_animal_padre` (`padre_id`),
  ADD KEY `fk_animal_madre` (`madre_id`),
  ADD KEY `idx_animales_estado` (`estado_animal`,`sexo`);

--
-- Indices de la tabla `animal_movimientos`
--
ALTER TABLE `animal_movimientos`
  ADD PRIMARY KEY (`movimiento_id`),
  ADD KEY `fk_mov_animal` (`animal_id`),
  ADD KEY `fk_mov_finca` (`finca_id`),
  ADD KEY `fk_mov_aprisco` (`aprisco_id`),
  ADD KEY `fk_mov_area` (`area_id`),
  ADD KEY `idx_mov_tipo_fecha` (`tipo_movimiento`,`fecha_evento`);

--
-- Indices de la tabla `animal_pesos`
--
ALTER TABLE `animal_pesos`
  ADD PRIMARY KEY (`peso_id`),
  ADD UNIQUE KEY `uq_peso_animal_fecha` (`animal_id`,`fecha`),
  ADD KEY `idx_pesos_animal_fecha` (`animal_id`,`fecha`);

--
-- Indices de la tabla `animal_salud`
--
ALTER TABLE `animal_salud`
  ADD PRIMARY KEY (`salud_id`),
  ADD KEY `fk_salud_animal` (`animal_id`);

--
-- Indices de la tabla `animal_ubicaciones`
--
ALTER TABLE `animal_ubicaciones`
  ADD PRIMARY KEY (`ubicacion_id`),
  ADD KEY `fk_ub_animal` (`animal_id`),
  ADD KEY `fk_ub_finca` (`finca_id`),
  ADD KEY `fk_ub_aprisco` (`aprisco_id`),
  ADD KEY `fk_ub_area` (`area_id`);

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
  ADD CONSTRAINT `fk_animal_madre` FOREIGN KEY (`madre_id`) REFERENCES `animales` (`animal_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_animal_padre` FOREIGN KEY (`padre_id`) REFERENCES `animales` (`animal_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `animal_movimientos`
--
ALTER TABLE `animal_movimientos`
  ADD CONSTRAINT `fk_mov_animal` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`animal_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mov_aprisco` FOREIGN KEY (`aprisco_id`) REFERENCES `apriscos` (`aprisco_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mov_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mov_finca` FOREIGN KEY (`finca_id`) REFERENCES `fincas` (`finca_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `animal_pesos`
--
ALTER TABLE `animal_pesos`
  ADD CONSTRAINT `fk_peso_animal` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`animal_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `animal_salud`
--
ALTER TABLE `animal_salud`
  ADD CONSTRAINT `fk_salud_animal` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`animal_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `animal_ubicaciones`
--
ALTER TABLE `animal_ubicaciones`
  ADD CONSTRAINT `fk_ub_animal` FOREIGN KEY (`animal_id`) REFERENCES `animales` (`animal_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ub_aprisco` FOREIGN KEY (`aprisco_id`) REFERENCES `apriscos` (`aprisco_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ub_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`area_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ub_finca` FOREIGN KEY (`finca_id`) REFERENCES `fincas` (`finca_id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
