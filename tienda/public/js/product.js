// El servidor vuelve a validar precio, disponibilidad y cantidad al comprar.
const productVariant = document.querySelector('[data-product-variant]');
if (productVariant) {
  const updateProductFormat = () => {
    const selected = productVariant.selectedOptions[0];
    if (!selected) return;
    const price = document.querySelector('[data-product-price]');
    const stock = document.querySelector('[data-product-stock]');
    const quantity = document.querySelector('[data-product-purchase] input[name="cantidad"]');
    if (price) price.textContent = selected.dataset.price;
    if (stock) stock.textContent = `${selected.dataset.stock} unidades disponibles en este formato.`;
    if (quantity) quantity.max = String(Math.max(1, Math.min(999, Number(selected.dataset.stock) || 0)));
  };
  productVariant.addEventListener('change', updateProductFormat);
  updateProductFormat();
}
