# Carrito guardado en la cuenta

Con sesión de cliente, el carrito se guarda en la base de datos y se recupera en cualquier dispositivo. Sin sesión, el carrito sigue funcionando como antes, solo en ese navegador.

## Funcionamiento

- **Al acceder**, el carrito del navegador se une con el guardado. Si un perfume está en ambos, se conserva la cantidad mayor: el mismo carrito en dos sitios no suma unidades dos veces.
- **Mientras hay sesión**, la cuenta es la referencia. Añadir, cambiar cantidades o eliminar actualiza la cuenta, y lo eliminado en un dispositivo no reaparece en otro.
- **El checkout** parte del carrito de la cuenta. **Al confirmar el pedido**, el carrito guardado se vacía.
- **Al cerrar sesión**, el carrito se conserva en el navegador (comportamiento anterior, cubierto por las pruebas de cuenta). Al volver a acceder no se duplica.
- Solo se guardan identificadores de perfume/formato y cantidades (1–999), hasta 100 líneas. Precios, stock y disponibilidad se releen siempre; un perfume retirado se muestra como no disponible y puede eliminarse.
- Varios dispositivos a la vez: gana el último cambio guardado. Los cambios de una misma cuenta se serializan con un bloqueo y se comprueba que la credencial sigue vigente.

## Fallos

Si la base de datos no responde, el carrito del navegador sigue funcionando y la página avisa de que no se ha guardado en la cuenta. Al recuperarse, se vuelven a unir ambos carritos, sin perder lo añadido mientras tanto. Un fallo al guardar nunca impide acceder ni comprar.

## Actualización

1. Aplicar `tienda/sql/migrations/006_customer_cart.sql` (aditiva e idempotente) **antes** de desplegar el código.
2. Desplegar juntos `carrito.php`, `checkout.php`, `acceso.php`, `src/lib/cart_account.php` y `src/lib/customer.php`.

## Validación

`tests/cart_account.php`: 33 comprobaciones HTTP con dos dispositivos y dos cuentas: unión sin duplicados, eliminaciones y cantidades compartidas, aislamiento entre cuentas, CSRF y entradas inválidas, cierre y nuevo acceso, perfumes retirados, caída simulada de la tabla y recuperación, credencial cambiada, y compra que vacía el carrito en todos los dispositivos y descuenta stock una vez. **717 comprobaciones por entorno** en PHP 8.3, 8.4 y 8.5 con MariaDB 11.4.

## Límites

No reserva stock ni guarda precios. No hay aviso cuando otro dispositivo cambia el carrito mientras se está viendo: se ve al recargar.
