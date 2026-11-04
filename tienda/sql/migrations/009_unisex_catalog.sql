-- Aditiva e idempotente. Ocho perfumes ficticios para la colección Unisex, con formatos de 50 y 100 ml.
-- Las imágenes son ilustraciones originales del proyecto (img/products/Unisex); se pueden sustituir desde el panel.
-- Solo añade lo que no existe: no modifica perfumes, precios ni stock ya presentes.
SET NAMES utf8mb4;
INSERT INTO categoria (nombre, slug, descripcion, imagen_url, activa, orden)
SELECT 'Unisex', 'unisex', 'Fragancias para cualquier persona.', 'img/categories/unisex.jpg', 1, 3
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM categoria WHERE slug = 'unisex');

INSERT INTO producto (id_categoria, id_marca, nombre, slug, descripcion_corta, descripcion_larga, precio, es_oferta, precio_oferta, stock, activo, imagen_principal)
SELECT c.id, COALESCE((SELECT id FROM marca WHERE slug = 'aurora-scents'), (SELECT MIN(id) FROM marca)), 'Bruma de Higo EDP', 'bruma-de-higo-edp', 'Higo verde, sal marina y cedro.', 'Fragancia unisex que abre con la frescura verde de la hoja de higuera y un toque salino. El corazón de higo maduro descansa sobre un fondo seco de cedro.', 62.90, 0, NULL, 0, 1, 'img/products/Unisex/bruma-de-higo-edp.jpg'
FROM categoria c WHERE c.slug = 'unisex' AND NOT EXISTS (SELECT 1 FROM producto WHERE slug = 'bruma-de-higo-edp');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 50, 'UNI-BRUMA-50', 62.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'bruma-de-higo-edp' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-BRUMA-50');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 100, 'UNI-BRUMA-100', 84.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'bruma-de-higo-edp' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-BRUMA-100');

INSERT INTO producto (id_categoria, id_marca, nombre, slug, descripcion_corta, descripcion_larga, precio, es_oferta, precio_oferta, stock, activo, imagen_principal)
SELECT c.id, COALESCE((SELECT id FROM marca WHERE slug = 'nordic-woods'), (SELECT MIN(id) FROM marca)), 'Té Blanco Lumen EDT', 'te-blanco-lumen-edt', 'Té blanco, bergamota y almizcle.', 'Eau de toilette luminosa y ligera. La bergamota da paso a una infusión de té blanco que se asienta en almizcles limpios. Pensada para el día a día.', 48.90, 0, NULL, 0, 1, 'img/products/Unisex/te-blanco-lumen-edt.jpg'
FROM categoria c WHERE c.slug = 'unisex' AND NOT EXISTS (SELECT 1 FROM producto WHERE slug = 'te-blanco-lumen-edt');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 50, 'UNI-LUMEN-50', 48.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'te-blanco-lumen-edt' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-LUMEN-50');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 100, 'UNI-LUMEN-100', 66.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'te-blanco-lumen-edt' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-LUMEN-100');

INSERT INTO producto (id_categoria, id_marca, nombre, slug, descripcion_corta, descripcion_larga, precio, es_oferta, precio_oferta, stock, activo, imagen_principal)
SELECT c.id, COALESCE((SELECT id FROM marca WHERE slug = 'urban-essence'), (SELECT MIN(id) FROM marca)), 'Ámbar Nómada EDP', 'ambar-nomada-edp', 'Ámbar, cardamomo y vainilla seca.', 'Perfume cálido y especiado. El cardamomo verde abre paso a un corazón ambarado que termina en una vainilla seca, poco dulce, con matices de madera.', 69.90, 0, NULL, 0, 1, 'img/products/Unisex/ambar-nomada-edp.jpg'
FROM categoria c WHERE c.slug = 'unisex' AND NOT EXISTS (SELECT 1 FROM producto WHERE slug = 'ambar-nomada-edp');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 50, 'UNI-NOMADA-50', 69.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'ambar-nomada-edp' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-NOMADA-50');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 100, 'UNI-NOMADA-100', 92.90, 1, 82.90, 12, 1
FROM producto p WHERE p.slug = 'ambar-nomada-edp' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-NOMADA-100');

INSERT INTO producto (id_categoria, id_marca, nombre, slug, descripcion_corta, descripcion_larga, precio, es_oferta, precio_oferta, stock, activo, imagen_principal)
SELECT c.id, COALESCE((SELECT id FROM marca WHERE slug = 'urban-essence'), (SELECT MIN(id) FROM marca)), 'Cítrico Cobalto EDT', 'citrico-cobalto-edt', 'Pomelo, neroli y vetiver.', 'Cítrico chispeante con pomelo rosado y neroli. El vetiver aporta un fondo terroso que alarga la estela. Fresco y versátil para cualquier estación.', 46.90, 0, NULL, 0, 1, 'img/products/Unisex/citrico-cobalto-edt.jpg'
FROM categoria c WHERE c.slug = 'unisex' AND NOT EXISTS (SELECT 1 FROM producto WHERE slug = 'citrico-cobalto-edt');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 50, 'UNI-COBALTO-50', 46.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'citrico-cobalto-edt' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-COBALTO-50');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 100, 'UNI-COBALTO-100', 64.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'citrico-cobalto-edt' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-COBALTO-100');

INSERT INTO producto (id_categoria, id_marca, nombre, slug, descripcion_corta, descripcion_larga, precio, es_oferta, precio_oferta, stock, activo, imagen_principal)
SELECT c.id, COALESCE((SELECT id FROM marca WHERE slug = 'aurora-scents'), (SELECT MIN(id) FROM marca)), 'Iris de Arena EDP', 'iris-de-arena-edp', 'Iris, sándalo y almizcle blanco.', 'Fragancia empolvada y serena. El iris, de textura suave, se funde con sándalo cremoso y almizcle blanco en una estela cercana a la piel.', 74.90, 0, NULL, 0, 1, 'img/products/Unisex/iris-de-arena-edp.jpg'
FROM categoria c WHERE c.slug = 'unisex' AND NOT EXISTS (SELECT 1 FROM producto WHERE slug = 'iris-de-arena-edp');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 50, 'UNI-IRIS-50', 74.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'iris-de-arena-edp' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-IRIS-50');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 100, 'UNI-IRIS-100', 99.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'iris-de-arena-edp' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-IRIS-100');

INSERT INTO producto (id_categoria, id_marca, nombre, slug, descripcion_corta, descripcion_larga, precio, es_oferta, precio_oferta, stock, activo, imagen_principal)
SELECT c.id, COALESCE((SELECT id FROM marca WHERE slug = 'nordic-woods'), (SELECT MIN(id) FROM marca)), 'Musgo Polar EDT', 'musgo-polar-edt', 'Musgo, enebro y lavanda.', 'Aromático y fresco, inspirado en bosques del norte. Bayas de enebro y lavanda sobre un fondo de musgo y maderas claras.', 49.90, 0, NULL, 0, 1, 'img/products/Unisex/musgo-polar-edt.jpg'
FROM categoria c WHERE c.slug = 'unisex' AND NOT EXISTS (SELECT 1 FROM producto WHERE slug = 'musgo-polar-edt');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 50, 'UNI-POLAR-50', 49.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'musgo-polar-edt' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-POLAR-50');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 100, 'UNI-POLAR-100', 68.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'musgo-polar-edt' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-POLAR-100');

INSERT INTO producto (id_categoria, id_marca, nombre, slug, descripcion_corta, descripcion_larga, precio, es_oferta, precio_oferta, stock, activo, imagen_principal)
SELECT c.id, COALESCE((SELECT id FROM marca WHERE slug = 'nordic-woods'), (SELECT MIN(id) FROM marca)), 'Lavanda Ahumada EDP', 'lavanda-ahumada-edp', 'Lavanda, cuero suave y haba tonka.', 'Una lavanda distinta: envuelta en notas ahumadas y un cuero suave, se redondea con haba tonka. Intensa y elegante, ideal para la noche.', 66.90, 0, NULL, 0, 1, 'img/products/Unisex/lavanda-ahumada-edp.jpg'
FROM categoria c WHERE c.slug = 'unisex' AND NOT EXISTS (SELECT 1 FROM producto WHERE slug = 'lavanda-ahumada-edp');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 50, 'UNI-AHUMADA-50', 66.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'lavanda-ahumada-edp' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-AHUMADA-50');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 100, 'UNI-AHUMADA-100', 88.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'lavanda-ahumada-edp' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-AHUMADA-100');

INSERT INTO producto (id_categoria, id_marca, nombre, slug, descripcion_corta, descripcion_larga, precio, es_oferta, precio_oferta, stock, activo, imagen_principal)
SELECT c.id, COALESCE((SELECT id FROM marca WHERE slug = 'aurora-scents'), (SELECT MIN(id) FROM marca)), 'Lluvia de Jengibre EDT', 'lluvia-de-jengibre-edt', 'Jengibre, yuzu y cedro.', 'Salida vibrante de jengibre fresco y yuzu, con un corazón ligeramente especiado y fondo de cedro. Energética y fácil de llevar.', 44.90, 0, NULL, 0, 1, 'img/products/Unisex/lluvia-de-jengibre-edt.jpg'
FROM categoria c WHERE c.slug = 'unisex' AND NOT EXISTS (SELECT 1 FROM producto WHERE slug = 'lluvia-de-jengibre-edt');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 50, 'UNI-JENGIBRE-50', 44.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'lluvia-de-jengibre-edt' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-JENGIBRE-50');
INSERT INTO producto_variante (id_producto, capacidad_ml, sku, precio, es_oferta, precio_oferta, stock, activo)
SELECT p.id, 100, 'UNI-JENGIBRE-100', 62.90, 0, NULL, 12, 1
FROM producto p WHERE p.slug = 'lluvia-de-jengibre-edt' AND NOT EXISTS (SELECT 1 FROM producto_variante WHERE sku = 'UNI-JENGIBRE-100');
