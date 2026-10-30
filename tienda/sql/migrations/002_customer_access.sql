-- Ejecutar sobre la base de la tienda. No altera clientes ni pedidos existentes.
CREATE TABLE IF NOT EXISTS acceso_intento (
    clave CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    ventana DATETIME NOT NULL,
    intentos INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (clave),
    KEY idx_acceso_ventana (ventana)
) ENGINE=InnoDB;
