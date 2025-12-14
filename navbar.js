document.addEventListener("DOMContentLoaded", () => {
    const cartBtn = document.getElementById("navbarCartButton");
    const cartPanel = document.getElementById("cartPanel");
    const cartBadge = document.getElementById("cartCountBadge");

    // IF CART DOES NOT EXIST (admin or logged out)
    if (!cartBtn || !cartPanel) return;

    // --- OPEN / CLOSE CART PANEL ---
    cartBtn.addEventListener("click", () => {
        cartPanel.classList.toggle("open");
        loadCartItems(); // load items every open
    });

    // CLOSE IF CLICK OUTSIDE
    document.addEventListener("click", (e) => {
        if (!cartPanel.contains(e.target) && e.target !== cartBtn && !cartBtn.contains(e.target)) {
            cartPanel.classList.remove("open");
        }
    });

    // --- LOAD CART ITEMS ---
    function loadCartItems() {
        fetch("load_cart.php")
            .then(res => res.text())
            .then(data => {
                document.getElementById("cartItems").innerHTML = data;
            })
            .catch(() => {
                document.getElementById("cartItems").innerHTML =
                    "<p style='text-align:center;color:red;'>Failed to load cart.</p>";
            });
    }

    // --- UPDATE CART BADGE ---
    function updateCartBadge() {
        fetch("cart_count.php")
            .then(res => res.json())
            .then(data => {
                if (data.count > 0) {
                    cartBadge.innerText = data.count;
                    cartBadge.style.display = "inline-block";
                } else {
                    cartBadge.style.display = "none";
                }
            });
    }

    updateCartBadge();
});
