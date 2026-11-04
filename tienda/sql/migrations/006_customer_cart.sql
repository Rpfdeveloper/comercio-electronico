-- Aditiva e idempotente: ejecutar antes de desplegar el carrito guardado en la cuenta.
-- No modifica clientes, productos ni pedidos existentes. No guarda precios: se releen al mostrar.
CREATE TABLE IF NOT EXISTS cliente_carrito (
    id_cliente INT NOT NULL,
    clave VARCHAR(16) NOT NULL,
    id_producto INT NOT NULL,
    id_variante INT DEFAULT NULL,
    cantidad SMALLINT UNSIGNED NOT NULL,
    actualizado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id_cliente, clave),
    CONSTRAINT chk_carrito_cantidad CHECK (cantidad BETWEEN 1 AND 999),
    CONSTRAINT fk_carrito_cliente FOREIGN KEY (id_cliente)
        REFERENCES cliente_registrado(id_cliente) ON DELETE CASCADE,
    CONSTRAINT fk_carrito_producto FOREIGN KEY (id_producto)
        REFERENCES producto(id) ON DELETE CASCADE,
    CONSTRAINT fk_carrito_variante FOREIGN KEY (id_variante)
        REFERENCES producto_variante(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
