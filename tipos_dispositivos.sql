-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:8889
-- Tiempo de generación: 24-03-2026 a las 17:12:43
-- Versión del servidor: 8.0.44
-- Versión de PHP: 8.3.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `jupiter`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_dispositivos`
--

CREATE TABLE `tipos_dispositivos` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `color` varchar(7) NOT NULL,
  `icono` varchar(50) DEFAULT 'bi bi-router',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Volcado de datos para la tabla `tipos_dispositivos`
--

INSERT INTO `tipos_dispositivos` (`id`, `nombre`, `color`, `icono`, `fecha_creacion`) VALUES
(1, 'Switch', '#007bff', 'bi bi-router', '2026-03-24 16:40:19'),
(2, 'Reloj Control', '#6f42c1', 'bi bi-clock', '2026-03-24 16:40:19'),
(3, 'NVR', '#dc3545', 'bi bi-camera-video', '2026-03-24 16:40:19'),
(4, 'NAS', '#fd7e14', 'bi bi-hdd-rack', '2026-03-24 16:40:19'),
(5, 'Máquina Virtual', '#20c997', 'bi bi-cpu', '2026-03-24 16:40:19'),
(6, 'Access Point', '#0dcaf0', 'bi bi-wifi', '2026-03-24 16:40:19'),
(7, 'Otro', '#6c757d', 'bi bi-device-hdd', '2026-03-24 16:40:19');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `tipos_dispositivos`
--
ALTER TABLE `tipos_dispositivos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `tipos_dispositivos`
--
ALTER TABLE `tipos_dispositivos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
