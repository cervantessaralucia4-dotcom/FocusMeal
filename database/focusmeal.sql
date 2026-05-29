-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3306
-- Tiempo de generación: 27-12-2025 a las 19:59:50
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `focusmeal`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alimentos`
--

CREATE TABLE `alimentos` (
  `id_alimneto` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `calorias` int(11) DEFAULT NULL,
  `proteinas` decimal(5,2) DEFAULT NULL,
  `carbohidratos` decimal(5,2) DEFAULT NULL,
  `grasas` decimal(5,2) DEFAULT NULL,
  `fibra` decimal(5,2) DEFAULT NULL,
  `Azucares` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id_alimneto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `contraseña` varchar(255) NOT NULL,
  `edad` int(11) DEFAULT NULL,
  `genero` enum('Masculino','Femenino','Otro') DEFAULT NULL,
  `peso_actual` decimal(5,2) DEFAULT NULL,
  `altura` decimal(5,2) DEFAULT NULL,
  `objetivo` enum('Bajar de peso','Mantener peso','Aumentar masa') DEFAULT NULL,
  `tipo_dieta` enum('General','Vegetariana','Keto','Baja en carbohidratos','Alta en proteínas') DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `es_premium` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `correo` (`correo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `correo`, `contraseña`, `edad`, `genero`, `peso_actual`, `altura`, `objetivo`, `tipo_dieta`, `fecha_registro`, `es_premium`) VALUES
(1, 'Sara Pérez', 'sara@example.com', '$2y$10$EjxLiYzpVnEDjP/ppQLJG.QdbgrOd3RjV4pvbw50WpxOCyPe3PIHK', 25, 'Femenino', 60.50, 1.65, 'Mantener peso', 'General', '2025-11-03 11:52:37', 0),
(4, 'sara cervantes', 'cervantessara366@gmail.com', '$2y$10$EjxLiYzpVnEDjP/ppQLJG.QdbgrOd3RjV4pvbw50WpxOCyPe3PIHK', 17, 'Femenino', 56.00, 151.00, 'Aumentar masa', 'Baja en carbohidratos', '2025-11-23 19:01:31', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planes`
--

CREATE TABLE `planes` (
  `id_plan` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `nombre_plan` varchar(100) NOT NULL,
  `calorias_diarias` int(11) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('Activo','Finalizado') DEFAULT 'Activo',
  PRIMARY KEY (`id_plan`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comidas`
--

CREATE TABLE `comidas` (
  `id_comidas` int(11) NOT NULL AUTO_INCREMENT,
  `id_plan` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `tipo_comida` enum('Desayuno','Almuerzo','Cena','Snack') DEFAULT NULL,
  `nombre_comida` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `calorias` int(11) DEFAULT NULL,
  `proteinas` decimal(5,2) DEFAULT NULL,
  `carbohidratos` decimal(5,2) DEFAULT NULL,
  `grasas` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id_comidas`),
  KEY `id_plan` (`id_plan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_progreso`
--

CREATE TABLE `historial_progreso` (
  `id_hsitoial` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `peso` decimal(5,2) DEFAULT NULL,
  `calorias_consumidas` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id_hsitoial`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recomendaciones`
--

CREATE TABLE `recomendaciones` (
  `id_recomendacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `titulo` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `calarias` int(11) DEFAULT NULL,
  `tipo_dieta` enum('General','Vegetariana','Keto','Baja en carbohidratos','Alta en proteinas') DEFAULT NULL,
  `fecha_generada` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id_recomendacion`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planes_disponibles`
--

CREATE TABLE `planes_disponibles` (
  `id_plan` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_plan` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `calorias_diarias` int(11) NOT NULL,
  `tipo_dieta` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_plan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `planes_disponibles`
--

INSERT INTO `planes_disponibles` (`nombre_plan`, `descripcion`, `calorias_diarias`, `tipo_dieta`) VALUES
('Plan Keto Básico', 'Dieta baja en carbohidratos y alta en grasas saludables para inducir la cetosis.', 1800, 'Keto'),
('Plan Vegetariano Balanceado', 'Dieta equilibrada a base de plantas con fuentes de proteína vegetal.', 2000, 'Vegetariana'),
('Plan Déficit Calórico General', 'Ideal para perder grasa manteniendo masa muscular magra.', 1600, 'General'),
('Plan Volumen Muscular', 'Plan calórico alto con macronutrientes balanceados para el crecimiento muscular.', 2800, 'Alta en proteínas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `planes_premium`
--

CREATE TABLE `planes_premium` (
  `id_plan_premium` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `precio_mensual` decimal(10,2) NOT NULL,
  `precio_anual` decimal(10,2) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_plan_premium`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `planes_premium`
--

INSERT INTO `planes_premium` (`nombre`, `precio_mensual`, `precio_anual`, `activo`) VALUES
('Focus Premium', 19900.00, 179900.00, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `suscripciones`
--

CREATE TABLE `suscripciones` (
  `id_suscripcion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `id_plan_premium` int(11) NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'activa',
  `fecha_inicio` date NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `referencia_payu` varchar(100) DEFAULT NULL,
  `fecha_pago` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_suscripcion`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_plan_premium` (`id_plan_premium`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pqrs`
--

CREATE TABLE `pqrs` (
  `id_pqrs` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `correo` varchar(100) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `asunto` varchar(150) NOT NULL,
  `mensaje` text NOT NULL,
  `respuesta` text DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'Pendiente',
  `fecha` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pqrs`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `chats`
--

CREATE TABLE `chats` (
  `id_chat` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `enviado_por` varchar(20) NOT NULL,
  `fecha_envio` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_chat`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `plan_generado`
--

CREATE TABLE `plan_generado` (
  `id_plan_gen` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `contenido` text NOT NULL,
  `calorias_obj` int(11) NOT NULL,
  `proteinas_obj` decimal(5,2) NOT NULL,
  `carbos_obj` decimal(5,2) NOT NULL,
  `grasas_obj` decimal(5,2) NOT NULL,
  `fecha_gen` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_plan_gen`),
  KEY `id_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Filtros para las tablas
--

ALTER TABLE `comidas`
  ADD CONSTRAINT `comidas_ibfk_1` FOREIGN KEY (`id_plan`) REFERENCES `planes` (`id_plan`) ON DELETE CASCADE;

ALTER TABLE `historial_progreso`
  ADD CONSTRAINT `historial_progreso_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

ALTER TABLE `planes`
  ADD CONSTRAINT `planes_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

ALTER TABLE `recomendaciones`
  ADD CONSTRAINT `recomendaciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

ALTER TABLE `suscripciones`
  ADD CONSTRAINT `suscripciones_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE,
  ADD CONSTRAINT `suscripciones_ibfk_2` FOREIGN KEY (`id_plan_premium`) REFERENCES `planes_premium` (`id_plan_premium`) ON DELETE CASCADE;

ALTER TABLE `chats`
  ADD CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

ALTER TABLE `plan_generado`
  ADD CONSTRAINT `plan_generado_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON DELETE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
