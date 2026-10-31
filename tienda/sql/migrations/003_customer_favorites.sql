-- Aditiva e idempotente: ejecutar antes de desplegar favoritos vinculados a cuentas.
-- No modifica clientes, productos ni pedidos existentes.
CREATE TABLE IF NOT EXISTS cliente_favorito (
    id_cliente INT NOT NULL,
    id_producto INT NOT NULL,
    posicion TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (id_cliente, id_producto),
    UNIQUE KEY idx_favorito_orden (id_cliente, posicion),
    CONSTRAINT chk_favorito_limite CHECK (posicion < 24),
    CONSTRAINT fk_favorito_cliente FOREIGN KEY (id_cliente)
        REFERENCES cliente_registrado(id_cliente) ON DELETE CASCADE,
    CONSTRAINT fk_favorito_producto FOREIGN KEY (id_producto)
        REFERENCES producto(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
