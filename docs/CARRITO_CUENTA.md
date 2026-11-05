# Carrito guardado en la cuenta

Con sesión de cliente, el carrito se guarda en la base de datos y se recupera en cualquier dispositivo. Sin sesión, el carrito sigue funcionando como antes, solo en ese navegador.

## Funcionamiento

- **Al acceder**, el carrito del navegador se une con el guardado. Si un perfume está en ambos, se conserva la cantidad mayor: el mismo carrito en dos sitios no suma unidades dos veces.
- **Mientras hay sesión**, la cuenta es la referencia. Añadir, cambiar cantidades o eliminar actualiza la cuenta, y lo eliminado en un dispositivo no reaparece en otro.
- **El checkout** recupera primero el carrito de la cuenta, incluso si el navegador todavía lo tiene vacío. **Al confirmar el pedido**, el carrito guardado se vacía en la misma transacción que registra el pedido y descuenta existencias.
- **Al cerrar sesión**, el carrito se conserva en el navegador (comportamiento anterior, cubierto por las pruebas de cuenta). Al volver a acceder no se duplica.
- Solo se guardan identificadores de perfume/formato y cantidades (1–999), hasta 100 líneas. Precios, stock y disponibilidad se releen siempre; un perfume retirado se muestra como no disponible y puede eliminarse.
- Varios dispositivos a la vez: cada escritura compara el carrito que leyó con el guardado después de bloquear la cuenta. Si ha cambiado, responde **409** y muestra el contenido actual sin sobrescribir el cambio ajeno. El formulario de cantidades también incluye una huella para detectar páginas antiguas. Las altas directas usan el carrito actual y conservan la comprobación transaccional.
- Confirmar vuelve a leer el carrito bajo el mismo bloqueo y lo compara con el resumen. Dos dispositivos que confirman el mismo contenido solo pueden generar un pedido. Se comprueba de nuevo que la credencial sigue vigente.

## Fallos

Si falla la tabla del carrito guardado, el navegador conserva una copia y la página avisa de que no se ha guardado en la cuenta. Al recuperarse, se vuelven a unir ambos carritos. **La compra autenticada se detiene mientras no pueda comprobarse el carrito de la cuenta**. Una caída completa de la conexión también impide consultar productos y acceder.

Si falla el vaciado del carrito al confirmar, se revierten el pedido y el descuento de stock. Si faltan los ajustes de impuestos/envío, se conserva el carrito y se detiene la compra sin inventar un total.

## Actualización

1. Aplicar `tienda/sql/migrations/006_customer_cart.sql` (aditiva e idempotente) **antes** de desplegar el código.
2. Desplegar juntos `carrito.php`, `checkout.php`, `acceso.php`, `src/lib/cart_account.php` y `src/lib/customer.php`.

## Validación

`tests/cart_account.php`: 33 comprobaciones HTTP de unión, aislamiento, cierre de sesión, recuperación y compra. `tests/cart_consistency.php`: 11 comprobaciones adicionales de entrada directa al checkout, formularios antiguos, cambios durante el resumen, fallo SQL con reversión, escrituras solapadas y confirmaciones desde dos sesiones. Las carreras observan dos transacciones esperando el mismo bloqueo; requieren varios procesos HTTP, MariaDB y permisos PROCESS/TRIGGER solo para la cuenta de pruebas.

Validación local completa: **922 comprobaciones** con PHP 8.3.6 y MariaDB 10.11.14. [Pruebas y CI](../tests/README.md).

## Límites

No reserva stock ni guarda precios. No envía actualizaciones en tiempo real: los cambios de otro dispositivo se detectan al recargar, enviar un formulario o confirmar.
