# Política comercial: IVA y envío

**Administración → Ajustes de venta** define el IVA y los gastos de envío. Se aplican al carrito, al resumen y a los pedidos nuevos; los pedidos registrados conservan sus importes.

## Valores

| Ajuste | Rango | Por defecto |
| --- | --- | --- |
| IVA | 0–30 %, hasta dos decimales | 21 % |
| Gastos de envío por pedido (sin IVA) | 0–1.000 € | 0 € (gratis) |
| Envío gratis desde (importe de productos) | vacío o 0,01–100.000 € | vacío |

Los valores por defecto reproducen la regla anterior, así que tras la migración nada cambia hasta que se edite la política.

## Cálculo

- Productos: suma de los precios del catálogo, que son sin IVA.
- Envío: el importe configurado, salvo carrito vacío o productos iguales o superiores al umbral de envío gratis.
- IVA: el tipo configurado sobre productos y envío, porque el envío tributa al mismo tipo que lo que se vende.
- Total: productos + envío + IVA, redondeado a céntimos.

El carrito indica cuánto falta para el envío gratis. El resumen y el pedido muestran el tipo de IVA aplicado.

## Consistencia

El resumen guarda los importes mostrados. Al confirmar, la política se lee con un bloqueo compartido; si cambió desde el resumen, el pedido no se registra y se pide revisarlo (**409**). Cada edición compara una huella de los valores mostrados (409 ante formularios antiguos) y anota el cambio en un historial con los diez últimos. Datos inválidos, incluidos caracteres de control, devuelven 422.

## Actualización

Aplicar `tienda/sql/migrations/007_commerce_policy.sql` (aditiva e idempotente). Sin ella, la tienda sigue usando 21 % y envío gratis, y la página de ajustes indica que falta la migración.

## Validación

`tests/policy.php`: 30 comprobaciones HTTP (valores por defecto, validación, CSRF, conflictos, historial, carrito con envío y umbral inclusivo, cambio de política durante la compra y pedido con los importes mostrados). **857 comprobaciones por entorno** en PHP 8.3, 8.4 y 8.5.

## Límites

Un solo tipo de IVA para todo el catálogo y un único coste de envío, sin zonas ni transportistas. No sustituye el asesoramiento fiscal: antes de vender, revisar tipos, territorios con regímenes especiales (Canarias, Ceuta y Melilla) y facturación.
