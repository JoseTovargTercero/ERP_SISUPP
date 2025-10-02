-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-10-2025 a las 17:16:32
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
-- Base de datos: `iseller`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users_permisos`
--

CREATE TABLE `users_permisos` (
  `id` int(11) NOT NULL,
  `id_user` int(11) NOT NULL,
  `id_item_menu` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `users_permisos`
--

INSERT INTO `users_permisos` (`id`, `id_user`, `id_item_menu`) VALUES
(1, 16, 10),
(4, 20, 15),
(5, 20, 11),
(6, 22, 15),
(7, 22, 11),
(8, 23, 15),
(9, 23, 11),
(10, 24, 1),
(11, 24, 5),
(12, 24, 9),
(13, 24, 8),
(14, 24, 10),
(15, 24, 11),
(16, 24, 15),
(17, 24, 20),
(18, 24, 21),
(19, 24, 22),
(20, 19, 15),
(21, 26, 15),
(23, 26, 11),
(24, 28, 15),
(25, 29, 15);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `users_permisos`
--
ALTER TABLE `users_permisos`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `users_permisos`
--
ALTER TABLE `users_permisos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
