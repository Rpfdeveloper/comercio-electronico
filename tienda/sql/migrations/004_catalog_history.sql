-- Aditiva e idempotente. Aplicar antes del panel de edición del catálogo.
-- Conserva productos, stock y pedidos; no registra IP, sesiones ni credenciales.
CREATE TABLE IF NOT EXISTS catalogo_cambio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_producto INT NOT NULL,
    accion VARCHAR(24) NOT NULL,
    cambios LONGTEXT NOT NULL,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_catalogo_historial (id_producto, id),
    CONSTRAINT fk_catalogo_historial_producto FOREIGN KEY (id_producto)
        REFERENCES producto(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
