-- Aditiva e idempotente. Aplicar antes de la gestión de estados de pedidos.
-- Conserva pedidos y stock; no registra IP, sesiones ni credenciales.
CREATE TABLE IF NOT EXISTS pedido_estado_cambio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pedido INT(11) NOT NULL,
    estado_anterior TINYINT(4) NOT NULL,
    estado_nuevo TINYINT(4) NOT NULL,
    nota VARCHAR(500) DEFAULT NULL,
    unidades_repuestas INT NOT NULL DEFAULT 0,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pedido_estado_historial (id_pedido, id),
    CONSTRAINT fk_pedido_estado_historial_pedido FOREIGN KEY (id_pedido)
        REFERENCES pedido(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
