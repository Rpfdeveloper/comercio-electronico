/* ============================================================
 * CREACIÓN DE LA BASE DE DATOS
 * ============================================================ */

CREATE DATABASE IF NOT EXISTS `tienda_perfumes`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `tienda_perfumes`;

/* ============================================================
 * USUARIO DE APLICACIÓN
 * ============================================================ */

DROP USER IF EXISTS 'tienda_user'@'localhost';

CREATE USER 'tienda_user'@'localhost'
  IDENTIFIED BY 'CAMBIAR_ANTES_DE_IMPORTAR';

GRANT SELECT, INSERT, UPDATE, DELETE
  ON `tienda_perfumes`.*
  TO 'tienda_user'@'localhost';

FLUSH PRIVILEGES;

SET FOREIGN_KEY_CHECKS = 0;

/* ============================================================
 * ELIMINAR TABLAS SI EXISTEN
 * ============================================================ */

DROP TABLE IF EXISTS `linea_pedido`;
DROP TABLE IF EXISTS `producto_imagen`;
DROP TABLE IF EXISTS `producto_variante`;
DROP TABLE IF EXISTS `pedido`;
DROP TABLE IF EXISTS `producto`;
DROP TABLE IF EXISTS `cliente_registrado`;
DROP TABLE IF EXISTS `cliente`;
DROP TABLE IF EXISTS `estado_pedido`;
DROP TABLE IF EXISTS `marca`;
DROP TABLE IF EXISTS `categoria`;

/* ============================================================
 * TABLAS
 * ============================================================ */

-- Tabla: categoria
CREATE TABLE `categoria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `orden` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_categoria_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: marca
CREATE TABLE `marca` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `slug` varchar(140) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `pais` varchar(80) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_marca_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cliente
CREATE TABLE `cliente` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(160) NOT NULL,
  `nombre` varchar(120) DEFAULT NULL,
  `apellidos` varchar(160) DEFAULT NULL,
  `telefono` varchar(32) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_cliente_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: cliente_registrado
CREATE TABLE `cliente_registrado` (
  `id_cliente` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `direccion` varchar(200) NOT NULL,
  `cp` varchar(12) NOT NULL,
  `poblacion` varchar(120) NOT NULL,
  `provincia` varchar(120) DEFAULT NULL,
  `pais` varchar(120) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: estado_pedido
CREATE TABLE `estado_pedido` (
  `id` tinyint(4) NOT NULL,
  `descripcion` varchar(60) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: pedido (COINCIDE CON checkout.php)
CREATE TABLE `pedido` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) DEFAULT NULL,
  `email` varchar(160) NOT NULL,
  `nombre_envio` varchar(120) NOT NULL,
  `direccion_envio` varchar(200) NOT NULL,
  `cp_envio` varchar(12) NOT NULL,
  `poblacion_envio` varchar(120) NOT NULL,
  `provincia_envio` varchar(120) DEFAULT NULL,
  `pais_envio` varchar(120) NOT NULL,
  `importe_productos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `gastos_envio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `impuestos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `estado` tinyint(4) NOT NULL DEFAULT 1,
  `fecha_pedido` timestamp NOT NULL DEFAULT current_timestamp(),
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pedido_estado` (`estado`),
  KEY `idx_pedido_fecha` (`fecha_pedido`),
  KEY `idx_pedido_email` (`email`),
  KEY `fk_pedido_cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: producto
CREATE TABLE `producto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_categoria` int(11) NOT NULL,
  `id_marca` int(11) NOT NULL,
  `nombre` varchar(180) NOT NULL,
  `slug` varchar(200) NOT NULL,
  `descripcion_corta` varchar(255) DEFAULT NULL,
  `descripcion_larga` text DEFAULT NULL,
  `imagen_principal` varchar(255) DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `es_oferta` tinyint(1) NOT NULL DEFAULT 0,
  `precio_oferta` decimal(10,2) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_producto_slug` (`slug`),
  KEY `idx_producto_categoria_activo` (`id_categoria`,`activo`),
  KEY `idx_producto_nombre` (`nombre`),
  KEY `fk_producto_marca` (`id_marca`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: producto_imagen
CREATE TABLE `producto_imagen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `url` varchar(255) NOT NULL,
  `alt` varchar(160) DEFAULT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  `es_principal` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_prodimg_producto` (`id_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: producto_variante
CREATE TABLE `producto_variante` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `capacidad_ml` int(11) NOT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `precio` decimal(10,2) NOT NULL DEFAULT 0.00,
  `es_oferta` tinyint(1) DEFAULT 0,
  `precio_oferta` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_variante_sku` (`sku`),
  KEY `idx_variante_producto_activo` (`id_producto`,`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: linea_pedido
CREATE TABLE `linea_pedido` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_producto` int(11) NOT NULL,
  `id_variante` int(11) DEFAULT NULL,
  `nombre_producto` varchar(180) NOT NULL,
  `capacidad_ml` int(11) DEFAULT NULL,
  `unidades` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `dto` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio_neto` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_lp_pedido` (`id_pedido`),
  KEY `fk_lp_producto` (`id_producto`),
  KEY `fk_lp_variante` (`id_variante`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/* ============================================================
 * CLAVES FORÁNEAS
 * ============================================================ */

ALTER TABLE `cliente_registrado`
  ADD CONSTRAINT `fk_cliente_registrado_cliente`
  FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id`) ON DELETE CASCADE;

ALTER TABLE `pedido`
  ADD CONSTRAINT `fk_pedido_cliente`
    FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id`),
  ADD CONSTRAINT `fk_pedido_estado`
    FOREIGN KEY (`estado`) REFERENCES `estado_pedido` (`id`);

ALTER TABLE `producto`
  ADD CONSTRAINT `fk_producto_categoria`
    FOREIGN KEY (`id_categoria`) REFERENCES `categoria` (`id`),
  ADD CONSTRAINT `fk_producto_marca`
    FOREIGN KEY (`id_marca`) REFERENCES `marca` (`id`);

ALTER TABLE `producto_imagen`
  ADD CONSTRAINT `fk_prodimg_producto`
    FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id`) ON DELETE CASCADE;

ALTER TABLE `producto_variante`
  ADD CONSTRAINT `fk_var_producto`
    FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id`) ON DELETE CASCADE;

ALTER TABLE `linea_pedido`
  ADD CONSTRAINT `fk_lp_pedido`
    FOREIGN KEY (`id_pedido`) REFERENCES `pedido` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lp_producto`
    FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id`),
  ADD CONSTRAINT `fk_lp_variante`
    FOREIGN KEY (`id_variante`) REFERENCES `producto_variante` (`id`);

/* ============================================================
 * DATOS INICIALES
 * ============================================================ */

-- Categorías
INSERT INTO `categoria`
(`id`, `nombre`, `slug`, `descripcion`, `imagen_url`, `activa`, `orden`, `created_at`, `updated_at`)
VALUES
(1, 'Women Perfumes', 'perfumes-women',
 'Fragancias femeninas elegantes y duraderas.',
 'img/categories/women.jpg', 1, 1, NOW(), NOW()),
(2, 'Men Perfumes', 'perfumes-men',
 'Perfumes intensos y sofisticados para hombre.',
 'img/categories/men.jpg', 1, 2, NOW(), NOW()),
(3, 'Unisex', 'unisex',
 'Aromas versátiles para todos los públicos.',
 'img/categories/unisex.jpg', 1, 3, NOW(), NOW());

-- Marcas
INSERT INTO `marca`
(`id`, `nombre`, `slug`, `descripcion`, `pais`, `created_at`, `updated_at`)
VALUES
(1, 'Aurora Scents',  'aurora-scents',  'Colección de fragancias modernas.',      'Italy',  NOW(), NULL),
(2, 'Nordic Woods',   'nordic-woods',   'Fragancias inspiradas en la naturaleza.', 'Norway', NOW(), NULL),
(3, 'Urban Essence',  'urban-essence',  'Aromas urbanos y contemporáneos.',       'France', NOW(), NULL);

-- Estados de pedido
INSERT INTO `estado_pedido` (`id`, `descripcion`) VALUES
(1, 'pending'),
(2, 'paid'),
(3, 'processing'),
(4, 'shipped'),
(5, 'canceled');

-- Productos (10 hombre, 10 mujer)
INSERT INTO `producto`
(`id`, `id_categoria`, `id_marca`, `nombre`, `slug`,
 `descripcion_corta`, `descripcion_larga`,
 `precio`, `es_oferta`, `precio_oferta`,
 `stock`, `activo`, `imagen_principal`,
 `created_at`, `updated_at`)
VALUES
-- Hombre (categoría 2)
(1,  2, 1, 'Alpine Cedar EDP',      'alpine-cedar-edp',
 'Fragancia masculina.', 'Fragancia masculina de la línea Alpine Cedar.',
 59.90, 0, NULL, 20, 1, 'img/products/Hombre/alpine-cedar-edp.jpg', NOW(), NULL),
(2,  2, 2, 'Atlas Noir EDT',        'atlas-noir-edt',
 'Fragancia masculina.', 'Fragancia masculina intensa con matices oscuros.',
 49.90, 0, NULL, 20, 1, 'img/products/Hombre/atlas-noir-edt.jpg', NOW(), NULL),
(3,  2, 3, 'Blue Meridian EDP',     'blue-meridian-edp',
 'Fragancia masculina.', 'Fragancia masculina fresca con fondo marino.',
 59.90, 0, NULL, 20, 1, 'img/products/Hombre/blue-meridian-edp.jpg', NOW(), NULL),
(4,  2, 1, 'Cedar Storm EDT',       'cedar-storm-edt',
 'Fragancia masculina.', 'Perfume masculino especiado con base de cedro.',
 49.90, 0, NULL, 20, 1, 'img/products/Hombre/cedar-storm-edt.jpg', NOW(), NULL),
(5,  2, 2, 'Ember Wood EDP',        'ember-wood-edp',
 'Fragancia masculina.', 'Fragancia cálida de maderas y notas ahumadas.',
 59.90, 0, NULL, 20, 1, 'img/products/Hombre/ember-wood-edp.jpg', NOW(), NULL),
(6,  2, 3, 'Iron Harbor EDT',       'iron-harbor-edt',
 'Fragancia masculina.', 'Perfume masculino con notas metálicas y marinas.',
 49.90, 0, NULL, 20, 1, 'img/products/Hombre/iron-harbor-edt.jpg', NOW(), NULL),
(7,  2, 1, 'Night Compass EDT',     'night-compass-edt',
 'Fragancia masculina.', 'Fragancia nocturna, ideal para salidas y eventos.',
 49.90, 0, NULL, 20, 1, 'img/products/Hombre/night-compass-edt.jpg', NOW(), NULL),
(8,  2, 2, 'Ocean Graphite EDT',    'ocean-graphite-edt',
 'Fragancia masculina.', 'Perfume masculino marino con fondo mineral.',
 59.90, 0, NULL, 20, 1, 'img/products/Hombre/ocean-graphite-edt.jpg', NOW(), NULL),
(9,  2, 3, 'Royal Leather EDP',     'royal-leather-edp',
 'Fragancia masculina.', 'Fragancia de cuero elegante y duradera.',
 59.90, 0, NULL, 20, 1, 'img/products/Hombre/royal-leather-edp.jpg', NOW(), NULL),
(10, 2, 1, 'Urban Vetiver EDP',     'urban-vetiver-edp',
 'Fragancia masculina.', 'Vetiver moderno con toques cítricos y urbanos.',
 59.90, 0, NULL, 20, 1, 'img/products/Hombre/urban-vetiver-edp.jpg', NOW(), NULL),
-- Mujer (categoría 1)
(11, 1, 2, 'Amber Silk EDP',        'amber-silk-edp',
 'Fragancia femenina.', 'Fragancia femenina cálida con fondo ambarado.',
 59.90, 0, NULL, 20, 1, 'img/products/Mujer/amber-silk-edp.jpg', NOW(), NULL),
(12, 1, 3, 'Aurora Blossom EDP',    'aurora-blossom-edp',
 'Fragancia femenina.', 'Perfume floral luminoso, inspirado en amaneceres.',
 59.90, 0, NULL, 20, 1, 'img/products/Mujer/aurora-blossom-edp.jpg', NOW(), NULL),
(13, 1, 1, 'Coral Breeze EDT',      'coral-breeze-edt',
 'Fragancia femenina.', 'Fragancia fresca con toques marinos y cítricos.',
 49.90, 0, NULL, 20, 1, 'img/products/Mujer/coral-breeze-edt.jpg', NOW(), NULL),
(14, 1, 2, 'Ivory Jasmine EDP',     'ivory-jasmine-edp',
 'Fragancia femenina.', 'Perfume floral centrado en el jazmín y notas suaves.',
 59.90, 0, NULL, 20, 1, 'img/products/Mujer/ivory-jasmine-edp.jpg', NOW(), NULL),
(15, 1, 3, 'Luna Nera EDP',         'luna-nera-edp',
 'Fragancia femenina.', 'Fragancia envolvente con matices orientales.',
 59.90, 0, NULL, 20, 1, 'img/products/Mujer/luna-nera-edp.jpg', NOW(), NULL),
(16, 1, 1, 'Lune Dor EDT',          'lune-dor-edt',
 'Fragancia femenina.', 'Perfume suave y dorado con notas afrutadas.',
 49.90, 0, NULL, 20, 1, 'img/products/Mujer/lune-dor-edt.jpg', NOW(), NULL),
(17, 1, 2, 'Midnight Dahlia EDP',   'midnight-dahlia-edp',
 'Fragancia femenina.', 'Fragancia floral oscura, ideal para la noche.',
 59.90, 0, NULL, 20, 1, 'img/products/Mujer/midnight-dahlia-edp.jpg', NOW(), NULL),
(18, 1, 3, 'Petal Muse EDP',        'petal-muse-edp',
 'Fragancia femenina.', 'Perfume de pétalos suaves con fondo almizclado.',
 59.90, 0, NULL, 20, 1, 'img/products/Mujer/petal-muse-edp.jpg', NOW(), NULL),
(19, 1, 1, 'Serene Garden EDP',     'serene-garden-edp',
 'Fragancia femenina.', 'Fragancia serena inspirada en jardines verdes.',
 59.90, 0, NULL, 20, 1, 'img/products/Mujer/serene-garden-edp.jpg', NOW(), NULL),
(20, 1, 2, 'Velvet Rose EDP',       'velvet-rose-edp',
 'Fragancia femenina.', 'Perfume de rosa aterciopelada con fondo cálido.',
 59.90, 0, NULL, 20, 1, 'img/products/Mujer/velvet-rose-edp.jpg', NOW(), NULL);

-- Variantes (50 ml y 100 ml por producto)
INSERT INTO `producto_variante`
(`id`, `id_producto`, `capacidad_ml`, `sku`,
 `precio`, `es_oferta`, `precio_oferta`,
 `stock`, `activo`, `created_at`, `updated_at`)
VALUES
-- Hombre
(1,  1,  50, 'ALPINE-50',     59.90, 0, NULL, 10, 1, NOW(), NULL),
(2,  1, 100, 'ALPINE-100',    79.90, 0, NULL, 10, 1, NOW(), NULL),
(3,  2,  50, 'ATLAS-50',      49.90, 0, NULL, 10, 1, NOW(), NULL),
(4,  2, 100, 'ATLAS-100',     69.90, 0, NULL, 10, 1, NOW(), NULL),
(5,  3,  50, 'MERIDIAN-50',   59.90, 0, NULL, 10, 1, NOW(), NULL),
(6,  3, 100, 'MERIDIAN-100',  79.90, 0, NULL, 10, 1, NOW(), NULL),
(7,  4,  50, 'CEDAR-50',      49.90, 0, NULL, 10, 1, NOW(), NULL),
(8,  4, 100, 'CEDAR-100',     69.90, 0, NULL, 10, 1, NOW(), NULL),
(9,  5,  50, 'EMBER-50',      59.90, 0, NULL, 10, 1, NOW(), NULL),
(10, 5, 100, 'EMBER-100',     79.90, 0, NULL, 10, 1, NOW(), NULL),
(11, 6,  50, 'IRON-50',       49.90, 0, NULL, 10, 1, NOW(), NULL),
(12, 6, 100, 'IRON-100',      69.90, 0, NULL, 10, 1, NOW(), NULL),
(13, 7,  50, 'COMPASS-50',    49.90, 0, NULL, 10, 1, NOW(), NULL),
(14, 7, 100, 'COMPASS-100',   69.90, 0, NULL, 10, 1, NOW(), NULL),
(15, 8,  50, 'GRAPHITE-50',   59.90, 0, NULL, 10, 1, NOW(), NULL),
(16, 8, 100, 'GRAPHITE-100',  79.90, 0, NULL, 10, 1, NOW(), NULL),
(17, 9,  50, 'ROYALLEA-50',   59.90, 0, NULL, 10, 1, NOW(), NULL),
(18, 9, 100, 'ROYALLEA-100',  79.90, 0, NULL, 10, 1, NOW(), NULL),
(19, 10,  50, 'VETIVER-50',   59.90, 0, NULL, 10, 1, NOW(), NULL),
(20, 10, 100, 'VETIVER-100',  79.90, 0, NULL, 10, 1, NOW(), NULL),
-- Mujer
(21, 11,  50, 'AMBER-50',     59.90, 0, NULL, 10, 1, NOW(), NULL),
(22, 11, 100, 'AMBER-100',    79.90, 0, NULL, 10, 1, NOW(), NULL),
(23, 12,  50, 'AURORA-50',    59.90, 0, NULL, 10, 1, NOW(), NULL),
(24, 12, 100, 'AURORA-100',   79.90, 0, NULL, 10, 1, NOW(), NULL),
(25, 13,  50, 'CORAL-50',     49.90, 0, NULL, 10, 1, NOW(), NULL),
(26, 13, 100, 'CORAL-100',    69.90, 0, NULL, 10, 1, NOW(), NULL),
(27, 14,  50, 'IVORY-50',     59.90, 0, NULL, 10, 1, NOW(), NULL),
(28, 14, 100, 'IVORY-100',    79.90, 0, NULL, 10, 1, NOW(), NULL),
(29, 15,  50, 'LUNANERA-50',  59.90, 0, NULL, 10, 1, NOW(), NULL),
(30, 15, 100, 'LUNANERA-100', 79.90, 0, NULL, 10, 1, NOW(), NULL),
(31, 16,  50, 'LUNEDOR-50',   49.90, 0, NULL, 10, 1, NOW(), NULL),
(32, 16, 100, 'LUNEDOR-100',  69.90, 0, NULL, 10, 1, NOW(), NULL),
(33, 17,  50, 'DAHLIA-50',    59.90, 0, NULL, 10, 1, NOW(), NULL),
(34, 17, 100, 'DAHLIA-100',   79.90, 0, NULL, 10, 1, NOW(), NULL),
(35, 18,  50, 'PETAL-50',     59.90, 0, NULL, 10, 1, NOW(), NULL),
(36, 18, 100, 'PETAL-100',    79.90, 0, NULL, 10, 1, NOW(), NULL),
(37, 19,  50, 'SERENE-50',    59.90, 0, NULL, 10, 1, NOW(), NULL),
(38, 19, 100, 'SERENE-100',   79.90, 0, NULL, 10, 1, NOW(), NULL),
(39, 20,  50, 'VELVET-50',    59.90, 0, NULL, 10, 1, NOW(), NULL),
(40, 20, 100, 'VELVET-100',   79.90, 0, NULL, 10, 1, NOW(), NULL);

-- Imágenes por producto
INSERT INTO `producto_imagen`
(`id_producto`, `url`, `alt`, `orden`, `es_principal`)
VALUES
-- Hombre
(1,  'img/products/Hombre/alpine-cedar-edp.jpg',    'Alpine Cedar EDP',      1, 1),
(2,  'img/products/Hombre/atlas-noir-edt.jpg',      'Atlas Noir EDT',        1, 1),
(3,  'img/products/Hombre/blue-meridian-edp.jpg',   'Blue Meridian EDP',     1, 1),
(4,  'img/products/Hombre/cedar-storm-edt.jpg',     'Cedar Storm EDT',       1, 1),
(5,  'img/products/Hombre/ember-wood-edp.jpg',      'Ember Wood EDP',        1, 1),
(6,  'img/products/Hombre/iron-harbor-edt.jpg',     'Iron Harbor EDT',       1, 1),
(7,  'img/products/Hombre/night-compass-edt.jpg',   'Night Compass EDT',     1, 1),
(8,  'img/products/Hombre/ocean-graphite-edp.jpg',  'Ocean Graphite EDP',    1, 1),
(9,  'img/products/Hombre/royal-leather-edp.jpg',   'Royal Leather EDP',     1, 1),
(10, 'img/products/Hombre/urban-vetiver-edp.jpg',   'Urban Vetiver EDP',     1, 1),
-- Mujer
(11, 'img/products/Mujer/amber-silk-edp.jpg',       'Amber Silk EDP',        1, 1),
(12, 'img/products/Mujer/aurora-blossom-edp.jpg',   'Aurora Blossom EDP',    1, 1),
(13, 'img/products/Mujer/coral-breeze-edt.jpg',     'Coral Breeze EDT',      1, 1),
(14, 'img/products/Mujer/ivory-jasmine-edp.jpg',    'Ivory Jasmine EDP',     1, 1),
(15, 'img/products/Mujer/luna-nera-edp.jpg',        'Luna Nera EDP',         1, 1),
(16, 'img/products/Mujer/lune-dor-edt.jpg',         'Lune Dor EDT',          1, 1),
(17, 'img/products/Mujer/midnight-dahlia-edp.jpg',  'Midnight Dahlia EDP',   1, 1),
(18, 'img/products/Mujer/petal-muse-edp.jpg',       'Petal Muse EDP',        1, 1),
(19, 'img/products/Mujer/serene-garden-edp.jpg',    'Serene Garden EDP',     1, 1),
(20, 'img/products/Mujer/velvet-rose-edp.jpg',      'Velvet Rose EDP',       1, 1);

SET FOREIGN_KEY_CHECKS = 1;

