<?php require __DIR__ . '/header.php'; ?>
<section class="commerce" aria-labelledby="checkout-title">
  <nav aria-label="Pasos de la compra"><ol class="commerce-steps"><li><a href="carrito.php">1. Carrito</a></li><li <?= $step === 'form' ? 'aria-current="step"' : '' ?>><?= $step === 'resumen' ? '<a href="checkout.php">2. Datos</a>' : '2. Datos' ?></li><li <?= $step === 'resumen' ? 'aria-current="step"' : '' ?>>3. Revisar</li></ol></nav>
  <h2 id="checkout-title" <?= $step === 'resumen' ? 'tabindex="-1" data-focus-on-load' : '' ?>><?= $step === 'resumen' ? 'Revisa tu pedido' : 'Datos de envío' ?></h2>
  <p class="commerce-intro"><?= $step === 'resumen' ? 'Comprueba los productos, el total y la dirección antes de confirmar.' : 'Completa tus datos. En el siguiente paso podrás revisar el pedido.' ?></p>
  <?php if ($errors): ?>
    <div class="commerce-error" id="checkout-errors" role="alert" tabindex="-1" data-focus-on-load aria-labelledby="errors-title">
      <h3 id="errors-title">Revisa los datos para continuar</h3><ul>
        <?php foreach ($errors as $key => $message): ?><li><?php if ($key !== 'general'): ?><a href="#checkout-<?= h($key) ?>"><?= h($message) ?></a><?php else: ?><?= h($message) ?><?php endif; ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>
  <div class="commerce-layout">
    <div class="commerce-panel">
      <?php if ($step === 'form'): ?>
        <form method="post" action="checkout.php" class="checkout-form" data-checkout-form data-account-email="<?= h($currentCustomer['email'] ?? '') ?>" novalidate>
          <?= csrf_field() ?><input type="hidden" name="step" value="form">
          <fieldset id="checkout-tipo" tabindex="-1" <?= isset($errors['tipo']) ? 'aria-describedby="error-tipo"' : '' ?>>
            <legend>Cómo quieres comprar</legend>
            <div class="checkout-options">
              <label><input type="radio" name="tipo" value="invitado" <?= $data['tipo'] === 'invitado' ? 'checked' : '' ?>><span><strong>Como invitado</strong><small>Sin crear una cuenta</small></span></label>
              <label><input type="radio" name="tipo" value="registrado" <?= $data['tipo'] === 'registrado' ? 'checked' : '' ?>><span><strong>Con una cuenta</strong><small>Accede o crea tu cuenta al comprar</small></span></label>
            </div>
            <?php if (isset($errors['tipo'])): ?><p id="error-tipo" class="field-error"><?= h($errors['tipo']) ?></p><?php endif; ?>
          </fieldset>
          <?php if ($currentCustomer): ?><p class="commerce-help">Has accedido como <?= h($currentCustomer['email']) ?>. Puedes comprar con esta cuenta sin repetir la contraseña.</p><?php else: ?><p class="commerce-help">¿Ya tienes cuenta? <a href="acceso.php">Accede para usar tus datos guardados</a>.</p><?php endif; ?>
          <p class="commerce-help">Todos los campos son obligatorios salvo la provincia.</p>
          <div class="checkout-fields">
            <?php foreach (checkout_fields() as $key => $field): ?>
              <div class="checkout-field <?= in_array($key, ['email','nombre','direccion'], true) ? 'checkout-wide' : '' ?>">
                <label for="checkout-<?= h($key) ?>"><?= h($field['label']) ?><?= !$field['required'] ? ' (opcional)' : '' ?></label>
                <input type="<?= $field['type'] ?>" id="checkout-<?= h($key) ?>" name="<?= h($key) ?>" value="<?= h($data[$key]) ?>" autocomplete="<?= h($field['autocomplete']) ?>" maxlength="<?= $field['max'] ?>" <?= $field['required'] ? 'required' : '' ?> <?= isset($errors[$key]) ? 'aria-invalid="true" aria-describedby="error-' . h($key) . '"' : '' ?>>
                <?php if (isset($errors[$key])): ?><p id="error-<?= h($key) ?>" class="field-error"><?= h($errors[$key]) ?></p><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div id="password-block" class="checkout-password">
            <label for="checkout-password">Contraseña de la cuenta</label>
            <div class="password-control"><input type="password" id="checkout-password" name="password" value="" autocomplete="current-password" maxlength="4096" aria-describedby="password-help<?= isset($errors['password']) ? ' error-password' : '' ?>" <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>><button type="button" class="commerce-secondary" data-show-password hidden aria-controls="checkout-password" aria-pressed="false">Mostrar</button></div>
            <p id="password-help" class="commerce-help">Solo para comprar con cuenta. Usa tu contraseña actual o crea una de al menos 15 caracteres y como máximo 72 bytes.</p>
            <?php if (isset($errors['password'])): ?><p id="error-password" class="field-error"><?= h($errors['password']) ?></p><?php endif; ?>
          </div>
          <button type="submit" class="commerce-primary">Continuar al resumen</button>
          <p class="commerce-help">Todavía no se registrará el pedido.</p>
        </form>
      <?php else: ?>
        <div class="commerce-heading"><h3>Datos de envío</h3><a href="checkout.php">Modificar datos</a></div>
        <address class="checkout-address"><?= h($data['nombre']) ?><br><?= h($data['direccion']) ?><br><?= h($data['cp']) . ' ' . h($data['poblacion']) ?><br><?php if ($data['provincia']): ?><?= h($data['provincia']) ?><br><?php endif; ?><?= h($data['pais']) ?></address>
        <p class="checkout-email"><strong>Correo de contacto</strong><br><?= h($data['email']) ?></p>
        <p class="commerce-help"><?= $data['tipo'] === 'registrado' ? 'Compra con cuenta' : 'Compra como invitado' ?></p>
        <form method="post" action="checkout.php" class="checkout-confirm"><?= csrf_field() ?><input type="hidden" name="step" value="confirm"><button type="submit" class="commerce-primary">Confirmar pedido</button></form>
        <p class="commerce-help">Al confirmar se registrará el pedido. Esta tienda de demostración no cobra pagos.</p>
      <?php endif; ?>
    </div>
    <aside class="commerce-panel commerce-summary" aria-labelledby="summary-title">
      <?php $summaryItems = $items; require __DIR__ . '/order-summary.php'; ?>
      <a href="carrito.php">Modificar productos o cantidades</a>
    </aside>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
