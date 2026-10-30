-- Aplicar sobre una copia de seguridad de la base de datos existente.
-- No vuelve a crear tablas ni elimina pedidos. Puede ejecutarse más de una vez.
START TRANSACTION;
UPDATE producto_imagen
SET url = 'img/products/Hombre/ocean-graphite-edt.jpg',
    alt = 'Ocean Graphite EDT'
WHERE url = 'img/products/Hombre/ocean-graphite-edp.jpg';
COMMIT;
