-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 01-10-2025 a las 04:12:32
-- Versión del servidor: 10.4.13-MariaDB
-- Versión de PHP: 7.4.8

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
-- Estructura de tabla para la tabla `system_users`
--

CREATE TABLE `system_users` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8 COLLATE utf8_spanish2_ci DEFAULT NULL,
  `contrasena` varchar(255) DEFAULT NULL,
  `nivel` int(11) DEFAULT NULL,
  `estado` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Volcado de datos para la tabla `system_users`
--

INSERT INTO `system_users` (`id`, `nombre`, `email`, `contrasena`, `nivel`, `estado`) VALUES
(1, 'Fatima Gomez', 'fatimagomezpd@gmail.com', '$2y$10$EyP1MOY39kuw4uREdk7ao.UUzQ10YNIZ95IZLM70MUPo5J6YzEBVG', 1, 1),
(35, 'Ricardo', 'jose.2710.ricardo@gmail.com', '$2y$10$azF/dOpnDs9sCTYiLEF7kO8612REFdjpk8Te.bih4BaNDSfhAw9MO', 1, 1),
(40, 'Hilson Martinez', 'martinezhilson8@gmail.com', '$2y$10$3mEuUd1/uIn0nNx3.qBoYeGeDc7WAXsEUvldqHX1WNaWusgVwnu9e', 2, 1),
(42, 'ASDRUBAL MARTINEZ', 'asdrubalmartinez486@gmail.com', '$2y$10$yUnVJhDWX6xkB4BEch2HPeAbEGNA311qcjs1DXVIsTmaah6jzHwzW', 2, 1),
(43, 'user ejecucion', 'magomagel1983@gmail.com', '$2y$10$EyP1MOY39kuw4uREdk7ao.UUzQ10YNIZ95IZLM70MUPo5J6YzEBVG', 1, 1),
(45, 'user proyectos', 'proyecto@correo.com', '$2y$10$EyP1MOY39kuw4uREdk7ao.UUzQ10YNIZ95IZLM70MUPo5J6YzEBVG', 1, 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `system_users`
--
ALTER TABLE `system_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario` (`email`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `system_users`
--
ALTER TABLE `system_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
