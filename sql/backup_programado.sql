-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost:8889
-- Tiempo de generación: 13-04-2026 a las 18:21:16
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
-- Estructura de tabla para la tabla `backup_programado`
--

CREATE TABLE `backup_programado` (
  `id` int NOT NULL,
  `frecuencia` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'diario',
  `hora_backup` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '02:00',
  `ultimo_backup` datetime DEFAULT NULL,
  `proxima_programacion` datetime DEFAULT NULL,
  `activo` tinyint DEFAULT '1',
  `usuario_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `backup_programado`
--

INSERT INTO `backup_programado` (`id`, `frecuencia`, `hora_backup`, `ultimo_backup`, `proxima_programacion`, `activo`, `usuario_id`, `created_at`, `updated_at`) VALUES
(1, 'diario', '14:15', '2026-04-13 14:13:40', NULL, 1, 422, '2026-04-13 18:07:11', '2026-04-13 18:14:43');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `backup_programado`
--
ALTER TABLE `backup_programado`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_backup` (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `backup_programado`
--
ALTER TABLE `backup_programado`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `backup_programado`
--
ALTER TABLE `backup_programado`
  ADD CONSTRAINT `backup_programado_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
