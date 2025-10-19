let cart = [];

function addToCart(itemName) {
  cart.push(itemName);
  updateCartDisplay();
}

function updateCartDisplay() {
  const cartCount = document.getElementById("cart-count");
  const cartItems = document.getElementById("cart-items");
  const emptyCartText = document.getElementById("empty-cart");

  cartCount.textContent = cart.length;
  cartItems.innerHTML = "";

  if (cart.length === 0) {
    emptyCartText.style.display = "block";
  } else {
    emptyCartText.style.display = "none";
    cart.forEach((item, index) => {
      const li = document.createElement("li");
      li.className = "list-group-item d-flex justify-content-between align-items-center";
      li.textContent = item;

      const removeBtn = document.createElement("button");
      removeBtn.className = "btn btn-sm btn-outline-danger";
      removeBtn.innerHTML = '<i class="fas fa-trash"></i>';
      removeBtn.onclick = () => removeFromCart(index);

      li.appendChild(removeBtn);
      cartItems.appendChild(li);
    });
  }
}

function removeFromCart(index) {
  cart.splice(index, 1);
  updateCartDisplay();
}

document.addEventListener("DOMContentLoaded", () => {
  updateCartDisplay();
});
