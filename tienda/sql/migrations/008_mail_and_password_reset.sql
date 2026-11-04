-- Aditiva e idempotente. Bandeja de correo saliente y enlaces de recuperación de contraseña.
-- Los enlaces se guardan solo como hash SHA-256; el token en claro nunca se almacena fuera del correo.
CREATE TABLE IF NOT EXISTS correo_saliente (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo VARCHAR(32) NOT NULL,
    referencia VARCHAR(64) DEFAULT NULL,
    destinatario VARCHAR(160) NOT NULL,
    asunto VARCHAR(200) NOT NULL,
    cuerpo TEXT NOT NULL,
    estado VARCHAR(16) NOT NULL DEFAULT 'registrado',
    creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_correo_referencia (tipo, referencia),
    KEY idx_correo_creado (creado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS cliente_recuperacion (
    token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    id_cliente INT NOT NULL,
    expira DATETIME NOT NULL,
    usado DATETIME DEFAULT NULL,
    creado TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (token_hash),
    KEY idx_recuperacion_cliente (id_cliente),
    CONSTRAINT fk_recuperacion_cliente FOREIGN KEY (id_cliente)
        REFERENCES cliente_registrado(id_cliente) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
