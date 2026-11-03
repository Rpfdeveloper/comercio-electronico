# Estados de pedido

El detalle de cada pedido en **Administración → Pedidos** permite avanzar su estado, cancelarlo antes del envío y consultar el historial de cambios. Mantiene la cuenta administrativa única, sesión independiente, CSRF, caducidad, limitador de intentos y respuestas privadas sin caché, indexación ni Referer.

## Flujo

| Estado actual | Cambios permitidos |
| --- | --- |
| Pendiente (`pending`) | Pago registrado, Cancelado |
| Pago registrado (`paid`) | En preparación, Cancelado |
| En preparación (`processing`) | Enviado, Cancelado |
| Enviado (`shipped`) | Ninguno: pedido cerrado |
| Cancelado (`canceled`) | Ninguno: pedido cerrado |
| Estado personalizado | Ninguno: se revisa en la base de datos |

El servidor aplica la tabla; un formulario manipulado no puede saltar pasos, reabrir un pedido cerrado ni cancelar un envío. **Registrar el pago** solo anota que se comprobó fuera de la tienda: el prototipo sigue sin cobrar, verificar ni reembolsar pagos.

## Cancelación y existencias

Cancelar requiere marcar una confirmación. En la misma transacción que el cambio de estado, las unidades de todas las líneas vuelven al formato o producto sin variantes del que salieron; varias líneas del mismo formato se reponen juntas. Se reponen también referencias inactivas, porque las unidades siguen físicamente en el almacén. Si una referencia ya no existe, no se cancela nada.

## Concurrencia y errores

- El pedido se bloquea (`FOR UPDATE`) y se compara con el estado que mostraba el formulario. Un formulario antiguo, un reenvío o una segunda cancelación simultánea devuelven **409** sin cambios ni reposiciones repetidas.
- Un interbloqueo con una compra simultánea se revierte por completo y se reintenta hasta tres veces.
- Datos inválidos devuelven **422** y conservan la nota para corregirla; un fallo de base de datos devuelve **503** sin modificar el pedido.
- Tras guardar, se redirige (303) al detalle conservando los filtros del listado.

## Historial

Cada cambio registra estado anterior, nuevo, unidades repuestas, fecha y una nota interna opcional de hasta 500 caracteres. Las notas se muestran escapadas. No se guardan IP, sesiones ni credenciales; no conviene escribir en ellas datos de pago.

La cuenta del cliente muestra ahora los estados en español («Pendiente», «Pago registrado»…) en lugar de los códigos internos; los estados personalizados aparecen como «En revisión».

## Actualización

1. Aplicar `tienda/sql/migrations/005_order_status_history.sql` (aditiva e idempotente) **antes** de desplegar el código. No reimportar el esquema completo sobre pedidos reales.
2. Desplegar conjuntamente los PHP y `tienda/admin/assets/admin.css` (la hoja pasa a `?v=4`).
3. Sin la migración, el detalle sigue mostrándose con un aviso y los cambios de estado responden 503 sin modificar nada.

## Validación

`tests/admin_order_status.php` añade comprobaciones HTTP con pedidos, productos y formatos ficticios: acceso y CSRF, tabla de transiciones, entradas no escalares, SQL y XSS, notas en el límite (500 caracteres multibyte) y por encima, confirmación de cancelación, formularios antiguos, reenvíos, reposición agrupada y exacta, pedidos cerrados y estados personalizados, identificadores inválidos y métodos no previstos. Incluye una carrera real: dos administradores con sesiones independientes esperan el mismo bloqueo y solo una cancelación se aplica.

Resultado: **54 comprobaciones nuevas** y **684 por entorno** con todas las regresiones, en PHP 8.3, 8.4 y 8.5 con MariaDB 11.4 ([ejecución en GitHub Actions](https://github.com/Rpfdeveloper/comercio-electronico/actions/runs/41709712175)); sintaxis, revisión de patrones sensibles y log PHP sin avisos. La primera ejecución falló en PHP 8.5 porque el servidor de pruebas asignó ambas peticiones de la carrera al mismo proceso; la prueba lanza ahora la segunda cuando la primera ya espera el bloqueo. No se ha hecho revisión visual en dispositivos reales.

## Límites

Sin correos al cliente, seguimiento de transportista, devoluciones, reembolsos ni pagos reales. Un pedido enviado no se puede cancelar desde el panel; las devoluciones requerirán su propio flujo. MFA y roles siguen pendientes.
