-- phpMyAdmin SQL Dump
-- version 5.0.2
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 02-10-2025 a las 17:16:38
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
-- Estructura de tabla para la tabla `menu`
--

CREATE TABLE `menu` (
  `id` int(11) NOT NULL,
  `categoria` varchar(255) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `dir` varchar(255) DEFAULT NULL,
  `icono` varchar(255) DEFAULT NULL,
  `admin` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Volcado de datos para la tabla `menu`
--

INSERT INTO `menu` (`id`, `categoria`, `nombre`, `dir`, `icono`, `admin`) VALUES
(1, NULL, 'inicio', 'index.php', 'home-outline', 0),
(2, NULL, 'Ingreso diarios', 'registroCierre.php', 'document-text-outline', 0),
(3, NULL, 'Sucursales', 'sucursales.php', 'storefront-outline', 1),
(4, NULL, 'Gestión de Gastos', 'gastos.php', 'briefcase-outline', 0),
(5, 'ventas', 'Ventas del dia', 'listaVentas.php', 'wallet-outline', 0),
(6, 'ventas', 'Ventas de la semana', 'listaVentas_semana.php', 'calendar-outline', 0),
(7, 'ventas', 'Ventas del mes', 'listaVentas_mes.php', 'calendar-number-outline', 0),
(8, 'stock', 'Listado de productos', 'productos.php', 'list-outline', 0),
(9, 'stock', 'Nuevo producto', 'nuevoProducto.php', 'add-circle-outline', 0),
(10, 'stock', 'Nueva compra', 'nuevaCompra.php', 'cart-outline', 0),
(11, 'ventas', 'Creditos', 'creditos.php', 'card-outline', 0),
(12, 'stock', 'Descontado', 'descontado.php', 'remove-circle-outline', 0),
(15, NULL, 'Vender', 'ventas.php', 'cash-outline', 0),
(16, NULL, 'Consultas', 'consultaHistorica.php', 'search-outline', 0),
(17, 'stock', 'Nuevo producto por sucursal', 'nuevo_producto_sucursal.php', 'cube-outline', 0),
(18, 'stock', 'Modificar stock', 'modificar_stock.php', 'create-outline', 0),
(19, 'stock', 'Stock critico', 'stock_critico.php', 'alert-circle-outline', 0),
(20, 'stock', 'Nuevo Producto al mayor', 'productos_al_mayor.php', 'bag-add-outline', 0),
(21, 'stock', 'Transferencia de stock', 'transferir.php', 'swap-horizontal-outline', 0),
(22, 'Ajustes', 'Tasas de cambio', 'cambiar_tasas.php', 'settings-outline', 0),
(23, 'Ajustes', 'Configuración', 'configuracion.php', 'settings-outline', 0);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `menu`
--
ALTER TABLE `menu`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `menu`
--
ALTER TABLE `menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
