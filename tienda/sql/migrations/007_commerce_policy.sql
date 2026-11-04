-- Aditiva e idempotente. Valores iniciales idénticos a la regla anterior: IVA 21 % y envío gratuito.
-- Los pedidos guardan sus propios importes; cambiar la política no altera pedidos registrados.
CREATE TABLE IF NOT EXISTS tienda_politica (
    id TINYINT UNSIGNED NOT NULL,
    iva DECIMAL(5,2) NOT NULL DEFAULT 21.00,
    envio DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    envio_gratis_desde DECIMAL(10,2) DEFAULT NULL,
    actualizado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_politica_unica CHECK (id = 1),
    CONSTRAINT chk_politica_iva CHECK (iva BETWEEN 0 AND 30),
    CONSTRAINT chk_politica_envio CHECK (envio BETWEEN 0 AND 1000),
    CONSTRAINT chk_politica_gratis CHECK (envio_gratis_desde IS NULL OR envio_gratis_desde > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO tienda_politica(id) VALUES (1);
CREATE TABLE IF NOT EXISTS tienda_politica_cambio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    antes LONGTEXT NOT NULL,
    despues LONGTEXT NOT NULL,
    fecha TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
