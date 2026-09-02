# Módulo de caja

## Reglas operativas

- Existe un único turno abierto por tienda.
- Solo administradores y cajeros pueden abrir caja.
- El cajero responsable o un administrador pueden registrar movimientos y cerrar el turno.
- Toda venta requiere una caja abierta y queda asociada a ella.
- Los movimientos son inmutables y solo se aceptan durante un turno abierto.
- El cierre exige efectivo contado. Una diferencia requiere justificación.
- Una venta asociada a una caja cerrada no puede anularse.
- Consultas, detalles y exportaciones están aislados por tienda.

## Cálculo de conciliación

```text
efectivo esperado = monto inicial
                  + pagos en efectivo
                  + ingresos adicionales
                  - vuelto
                  - egresos

diferencia = efectivo contado - efectivo esperado
```

La diferencia se clasifica como `exacto`, `sobrante` o `faltante`.

## Arquitectura

- `CashRegisterManager`: apertura y cierre transaccional.
- `CashMovementManager`: creación transaccional de ingresos y egresos.
- `CashRegisterSummary`: agregados financieros en tres consultas constantes.
- `CashRegisterHistoryQuery`: filtros reutilizados por historial y CSV.
- `CajaPolicy`: permisos y aislamiento operativo.
- `AuditLogger`: trazabilidad de aperturas, movimientos y cierres.

## Pruebas

- `CajaSecurityTest`: autenticación, roles, aislamiento y auditoría.
- `CashMovementTest`: movimientos, validaciones y actualización de acumulados.
- `CashReconciliationTest`: conteo, diferencias y pagos.
- `CashHistoryDetailTest`: filtros, detalle, paginación y exportación.
- `CashRegisterPerformanceTest`: presupuestos de consultas y volumen.
- `CashRegisterAssetTest`: CSS, JavaScript, accesibilidad y adaptabilidad.

Ejecutar todas las pruebas:

```bash
php artisan test
```
