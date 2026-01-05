<script>
document.getElementById('checkout-button').addEventListener('click', function() {
    const nama = '<?php echo htmlspecialchars($nama, ENT_QUOTES, 'UTF-8'); ?>';
    const notelepon = '<?php echo htmlspecialchars($notelepon, ENT_QUOTES, 'UTF-8'); ?>';

    if (!nama.trim() || !notelepon.trim()) {
        alert('Nama dan nomor telepon harus diisi!');
        return;
    }

    // Ambil data keranjang dari localStorage
    let cart = JSON.parse(localStorage.getItem('cart')) || [];

    if (cart.length === 0) {
        alert("Keranjang belanja Anda kosong!");
        return;
    }

    const metodePembayaran = document.getElementById('metodePembayaran').value;
    const orderData = {
        meja: <?php echo json_encode($meja); ?>, // Pastikan JSON valid
        nama: nama,
        notelepon: notelepon,
        items: cart,
        metodePembayaran: metodePembayaran
    };

    fetch("proses_pesanan.php", { // Pastikan URL benar
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(orderData)
    }).then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Pesanan berhasil dibuat! Total harga: Rp' + data.total_harga);
            localStorage.removeItem('cart'); // Kosongkan keranjang
            updateCartUI();

            // Tutup modal jika modal ada
            var cartModalElement = document.getElementById('cartModal');
            if (cartModalElement) {
                var cartModal = bootstrap.Modal.getInstance(cartModalElement);
                if (cartModal) cartModal.hide();
            }
        } else {
            alert('Terjadi kesalahan saat memproses pesanan: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Terjadi kesalahan jaringan.');
    });
});
</script>

</script>