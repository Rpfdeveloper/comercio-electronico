// El servidor vuelve a validar precio, disponibilidad y cantidad al comprar.
const productVariant = document.querySelector('[data-product-variant]');
if (productVariant) {
  const updateProductFormat = () => {
    const selected = productVariant.selectedOptions[0];
    if (!selected) return;
    const price = document.querySelector('[data-product-price]');
    const stock = document.querySelector('[data-product-stock]');
    if (price) price.textContent = selected.dataset.price;
    if (stock) stock.textContent = `${selected.dataset.stock} unidades disponibles en este formato.`;
  };
  productVariant.addEventListener('change', updateProductFormat);
  updateProductFormat();
}
