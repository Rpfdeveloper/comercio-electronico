# Favoritos vinculados a la cuenta

## Uso

Sin iniciar sesión, la colección sigue guardándose en ese navegador durante 180 días desde el último cambio. Con una cuenta abierta, los botones del catálogo, la portada, la guía y las fichas guardan en la colección de esa cuenta. «Mis favoritos» indica dónde se guardan; «Mi cuenta» muestra un acceso y el contador.

La colección de cuenta se recupera al iniciar sesión desde otro navegador o dispositivo. Los cambios se ven al recargar o volver a entrar en la página; no hay actualización en tiempo real. Cada lista admite 24 perfumes, con los nuevos al principio. Repetir el guardado no duplica ni reordena un perfume existente.

Si el navegador ya tenía favoritos, se ofrece **«Unir favoritos a mi cuenta»**. El acceso no los importa automáticamente. La unión añade solo productos que siguen activos en una categoría activa, conserva el orden relativo de los ya guardados y evita duplicados. Después de confirmar la operación en la base de datos, vacía la lista del navegador. Si excede el límite, mantiene ambas listas para que se pueda liberar espacio y reintentar.

Cerrar sesión conserva la colección en la cuenta y no la copia a una cookie. Los favoritos del navegador que no se hayan unido siguen siendo una lista independiente. Los de la cuenta permanecen hasta retirarlos o vaciar la colección; la eliminación del registro de cuenta también elimina sus referencias en la base de datos.

Se releen precios y disponibilidad del catálogo. Los agotados siguen visibles, los productos o categorías retirados se ocultan con un aviso y no se reserva stock. La cuenta continúa creándose al completar una compra registrada: el alta independiente, la verificación del correo y la recuperación de contraseña siguen pendientes.

## Actualización de una instalación

1. Conservar una copia privada de la base de datos y comprobar su restauración antes de desplegar. Las ramas de copia de seguridad de GitHub guardan código, no los datos de la instalación.
2. Aplicar `tienda/sql/migrations/003_customer_favorites.sql` con una cuenta administrativa, después de las migraciones anteriores y **antes de subir el nuevo código**. Añade únicamente `cliente_favorito`; repetirla no borra datos. La cuenta de aplicación mantiene permisos de lectura/escritura, sin necesitar DDL.
3. Desplegar el código y probar con dos cuentas ficticias: guardado, recuperación en otra sesión, unión, salida y aislamiento. La preparación automática de pruebas aplica todas las migraciones sobre su base desechable.
4. Para volver al código anterior, conservar la nueva tabla y sus datos: la versión anterior simplemente no la usa. No reimportar el esquema completo ni eliminar tablas para retroceder.

En instalaciones nuevas, importar el esquema y aplicar todas las migraciones por orden. El esquema completo es una herramienta de instalación y reinicio de pruebas: elimina tablas y no debe aplicarse a una base que se quiera conservar.

## Aislamiento y consistencia

La identidad procede exclusivamente de la sesión validada; los parámetros de cliente enviados en URLs o formularios no cambian el propietario. Todas las consultas se limitan a ese cliente. No se publica un enlace compartible ni se recupera una colección por coincidencia de correo.

Las escrituras requieren POST y CSRF. El formulario indica si procede de la lista del navegador o de la cuenta: una sesión perdida o cambiada rechaza el envío antiguo en lugar de aplicarlo a otra lista. Se conservan noindex, ausencia de caché compartida y escape HTML.

Una transacción bloquea el registro de cuenta con `SELECT ... FOR UPDATE`, vuelve a comprobar la credencial validada y lee la colección actual antes de modificarla. De este modo, dos sesiones de la misma cuenta no pierden cambios ni superan conjuntamente el límite. Los interbloqueos y esperas agotadas tienen hasta tres intentos acotados; otros errores revierten la transacción. Si no puede confirmarse una operación, se pide recargar antes de reintentar.

La clave principal impide duplicados y la combinación de posiciones únicas y restricción de rango limita la tabla a 24 filas por cliente. Las claves foráneas eliminan referencias cuando se elimina un producto o el registro de cuenta. No hay nuevas credenciales, proveedores externos, mensajes ni pagos.

## Validación y límites

45 comprobaciones nuevas y 393 regresiones: **438 por entorno**. Las nuevas pruebas usan dos clientes y sesiones independientes; cubren lectura y escritura ajenas, CSRF, formularios antiguos, unión explícita, duplicados, límite, precios actuales, retirada del catálogo, cierre/cambio de sesión, migración repetida y eliminación de referencias.

Cuatro escenarios observan dos transacciones HTTP realmente bloqueadas a la vez en InnoDB: competir por el último hueco, añadir dos productos, añadir el mismo producto y revocar la credencial mientras esperan. Una prueba provoca un fallo SQL durante la unión, comprueba la reversión y la conservación de la cookie y después verifica el reintento completo. Solo opera en la base local desechable de pruebas.

La matriz de CI utiliza PHP 8.3, 8.4 y 8.5 con MariaDB 11.4. La validación local usa PHP 8.3.6/MariaDB 10.11.14. No se ha hecho una prueba de carga, auditoría completa de accesibilidad ni verificación en dispositivos físicos. La sincronización se ha comprobado con sesiones independientes, no con teléfonos reales. La revisión de producción sigue pendiente.

## Referencias técnicas

- [MariaDB: FOR UPDATE dentro de transacciones](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update).
- [OWASP: prevención de acceso a objetos ajenos](https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html).

Consultadas el 31 de octubre de 2026. Las pruebas de este repositorio verifican el comportamiento descrito; estas referencias no constituyen una auditoría del proyecto.
