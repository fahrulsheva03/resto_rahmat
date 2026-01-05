<script>
    const CHECKOUT_URL = 'proses_pesanan.php';
    document.addEventListener("DOMContentLoaded", function() {

        // Inisialisasi Keranjang dari Local Storage
        let cart = JSON.parse(localStorage.getItem('cart')) || [];
        updateCartUI(); // Panggil untuk pertama kali untuk menampilkan keranjang yang tersimpan

        // Fungsi Update Keranjang UI (Jumlah Item, Total Harga)
        function updateCartUI() {
            let cartCount = cart.reduce((sum, item) => sum + item.quantity, 0);
            document.getElementById('cart-count').innerText = cartCount;

            // Tampilkan Item di Modal Keranjang
            const cartItemsElement = document.getElementById('cart-items');
            cartItemsElement.innerHTML = ''; // Kosongkan dulu

            let cartTotal = 0;
            if (cart.length === 0) {
                cartItemsElement.innerHTML = '<p class="text-center">Keranjang kosong.</p>';
            } else {
                cart.forEach(item => {
                    const subtotal = item.harga * item.quantity;
                    cartTotal += subtotal;

                    const itemElement = document.createElement('div');
                    itemElement.classList.add('cart-item', 'd-flex', 'align-items-center', 'py-2', 'border-bottom');
                    itemElement.innerHTML = `
                        <img src="../../images/${item.gambar}" alt="${item.nama}" class="cart-item-image me-3 rounded" style="width: 60px; height: 60px; object-fit: cover;">
                        <div class="cart-item-details flex-grow-1">
                            <div class="cart-item-name fw-bold">${item.nama}</div>
                            <div class="cart-item-price text-muted">Rp${item.harga.toLocaleString('id-ID', {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0
                            })}</div>
                            <div class="cart-item-quantity mt-1">
                                <button class="btn btn-sm btn-outline-secondary decrease-quantity" data-id="${item.id}">-</button>
                                <span class="mx-2">${item.quantity}</span>
                                <button class="btn btn-sm btn-outline-secondary increase-quantity" data-id="${item.id}">+</button>
                            </div>
                        </div>
                         <div class="cart-item-subtotal ms-3 text-end fw-bold">Rp${subtotal.toLocaleString('id-ID', {
                            minimumFractionDigits: 0,
                            maximumFractionDigits: 0
                        })}</div>
                        <button class="btn btn-sm btn-danger remove-from-cart ms-3" data-id="${item.id}">
                            <i class="fa fa-trash"></i>
                        </button>
                    `;
                    cartItemsElement.appendChild(itemElement);
                });
            }


            document.getElementById('cart-total').innerText = 'Rp' + cartTotal.toLocaleString('id-ID', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0
            });

            // Simpan ke Local Storage
            localStorage.setItem('cart', JSON.stringify(cart));
        }

        // Fungsi Tambah Item ke Keranjang
        function addToCart(id, nama, harga, gambar) {
            const existingItem = cart.find(item => item.id === id);

            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({
                    id: id,
                    nama: nama,
                    harga: harga,
                    quantity: 1,
                    gambar: gambar // Simpan nama file gambar
                });
            }
            // Setelah menambah/mengupdate, langsung update UI
            updateCartUI();
            // Optional: Beri feedback ke user, misal notifikasi toast atau alert
            console.log(`"${nama}" ditambahkan ke keranjang.`);
        }

        // Fungsi untuk mengurangi kuantitas item di keranjang
        function decreaseQuantity(id) {
            const item = cart.find(item => item.id === id);
            if (item) {
                item.quantity--;
                if (item.quantity <= 0) {
                    removeFromCart(id); // Jika kuantitas jadi 0 atau kurang, hapus item
                }
            }
            updateCartUI(); // Setelah mengurangi/menghapus, update UI
        }

        // Fungsi untuk menambah kuantitas item di keranjang
        function increaseQuantity(id) {
            const item = cart.find(item => item.id === id);
            if (item) {
                item.quantity++;
            }
            updateCartUI(); // Setelah menambah, update UI
        }

        // Fungsi untuk menghapus item dari keranjang
        function removeFromCart(id) {
            const initialLength = cart.length;
            cart = cart.filter(item => item.id !== id);
            if (cart.length < initialLength) {
                console.log(`Item dengan ID ${id} dihapus dari keranjang.`);
            }
            updateCartUI(); // Setelah menghapus, update UI
        }


        // --- Event Delegation untuk Tombol-tombol Dinamis ---
        // Pasang satu listener pada elemen induk (misalnya, body dokumen)
        // Ini akan menangani klik untuk tombol 'Tambah ke Keranjang', 'Suka', 'Decrease', 'Increase', 'Remove'
        document.body.addEventListener('click', function(event) {
            // Tambah ke Keranjang Button
            if (event.target.closest('.add-to-cart')) {
                const button = event.target.closest('.add-to-cart'); // Pastikan kita dapat elemen button
                const id = button.dataset.id;
                const nama = button.dataset.nama;
                const harga = parseFloat(button.dataset.harga);
                const gambar = button.dataset.gambar;
                addToCart(id, nama, harga, gambar); // addToCart sudah memanggil updateCartUI()
            }

            // Like Button
            if (event.target.closest('.like-button')) {
                const button = event.target.closest('.like-button');
                const idMenu = button.dataset.idMenu;
                const isLiked = localStorage.getItem(`liked_${idMenu}`) === 'true';

                // Toggle status like di Local Storage
                const newLikedStatus = !isLiked;
                localStorage.setItem(`liked_${idMenu}`, newLikedStatus);

                // Update tampilan tombol segera (text dan warna)
                const buttonText = newLikedStatus ? 'Disukai' : 'Suka';
                const buttonColor = newLikedStatus ? 'btn-success' : 'btn-danger';
                // Ambil jumlah like saat ini dari teks tombol (jika ada dalam kurung)
                const match = button.innerText.match(/\((\d+)\)/);
                const currentLikes = match ? parseInt(match[1], 10) : 0;
                // Hitung jumlah like baru (increment jika suka, decrement jika batal suka)
                const newLikes = currentLikes + (newLikedStatus ? 1 : -1);
                // Pastikan jumlah like tidak negatif
                const displayedLikes = Math.max(0, newLikes);

                button.innerHTML = `<i class="fa fa-heart"></i> ${buttonText} (${displayedLikes})`;
                button.classList.toggle('btn-danger', !newLikedStatus);
                button.classList.toggle('btn-success', newLikedStatus);

                // Kirim update ke server
                likeMenu(idMenu, newLikedStatus); // likeMenu tidak perlu elemen button lagi karena UI sudah diupdate
            }

            // Decrease Quantity Button (di modal keranjang)
            if (event.target.classList.contains('decrease-quantity')) {
                const id = event.target.dataset.id;
                decreaseQuantity(id); // decreaseQuantity sudah memanggil updateCartUI()
            }

            // Increase Quantity Button (di modal keranjang)
            if (event.target.classList.contains('increase-quantity')) {
                const id = event.target.dataset.id;
                increaseQuantity(id); // increaseQuantity sudah memanggil updateCartUI()
            }

            // Remove from Cart Button (di modal keranjang)
            if (event.target.classList.contains('remove-from-cart')) {
                const id = event.target.dataset.id;
                removeFromCart(id); // removeFromCart sudah memanggil updateCartUI()
            }
        });
        // --- Akhir Event Delegation ---


        // Tampilkan Modal Keranjang Saat Ikon Keranjang Diklik
        document.getElementById('cart').addEventListener('click', function() {
            var cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
            cartModal.show();
        });

        // Proses Checkout (Pemesanan)
        document.getElementById('checkout-button').addEventListener('click', function() {
            // Gunakan variabel cart yang sudah ada, tidak perlu fetch dari localStorage lagi
            if (cart.length === 0) {
                alert('Tidak ada item di dalam Keranjang');
                return;
            }

            // Ambil nilai dari PHP (pastikan variabel PHP ini selalu diset sebelum script JS dijalankan)
            // Kita tambahkan sedikit validasi di JS juga jika PHP tidak menjamin variabel selalu ada
            const nama_pelanggan = "<?php echo isset($_SESSION['nama_pelanggan']) ? htmlspecialchars($_SESSION['nama_pelanggan']) : ''; ?>";
            const notelepon_pelanggan = "<?php echo isset($_SESSION['notelepon_pelanggan']) ? htmlspecialchars($_SESSION['notelepon_pelanggan']) : ''; ?>";
            // Untuk angka, pastikan nilainya adalah angka, fallback ke 0 atau null jika tidak ada
            const id_pelanggan = <?php echo isset($id_pelanggan) ? json_encode($id_pelanggan) : 'null'; ?>;
            const meja = <?php echo isset($meja) ? json_encode($meja) : 'null'; ?>;

            // Cek apakah data pelanggan atau meja penting tidak ada
            if (!nama_pelanggan || !notelepon_pelanggan || id_pelanggan === null || meja === null) {
                console.error("Informasi pelanggan atau meja tidak lengkap dari PHP.");
                // Beri notifikasi yang lebih informatif ke user jika perlu
                alert("Informasi pemesan (nama, telepon) atau nomor meja tidak tersedia. Mohon muat ulang halaman atau hubungi staf.");
                return; // Hentikan proses checkout jika data penting kurang
            }


            // Dapatkan metode pembayaran yang dipilih
            const metodePembayaran = document.getElementById('metodePembayaran').value;

            // Persiapkan data untuk dikirim ke proses_pesanan.php
            const orderData = {
                meja: meja, // Gunakan variabel yang sudah diproses
                nama_pemesan: nama_pelanggan, // Gunakan variabel yang sudah diproses
                notelepon: notelepon_pelanggan, // Gunakan variabel yang sudah diproses
                items: cart,
                metodePembayaran: metodePembayaran, // Kirim metode pembayaran yang dipilih
                id_pelanggan: id_pelanggan // Gunakan variabel yang sudah diproses
            };

            console.log("Data yang akan dikirim:", JSON.stringify(orderData));

            // Disable tombol checkout sementara untuk mencegah multiple submits
            const checkoutButton = document.getElementById('checkout-button');
            checkoutButton.disabled = true;
            checkoutButton.innerText = 'Memproses...'; // Opsional: ganti teks tombol

            // Kirim data pesanan ke proses_pesanan.php
            fetch(CHECKOUT_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(orderData)
                })
                .then(response => {
                    // Re-enable tombol checkout setelah menerima respon (baik sukses atau error)
                    checkoutButton.disabled = false;
                    checkoutButton.innerText = 'Checkout'; // Kembalikan teks tombol

                    // Periksa code Respon HTTP
                    if (!response.ok) {
                        // Coba baca respon body untuk pesan error lebih lanjut
                        return response.text().then(text => {
                            const errorDetail = text.substring(0, 200) + (text.length > 200 ? '...' : '');
                            console.error('HTTP error!', response.status, response.statusText, 'Body:', errorDetail);
                            // Lempar error baru yang lebih informatif
                            throw new Error(`HTTP error! status: ${response.status} | Response: ${errorDetail}`);
                        });
                    }
                    // Jika HTTP response OK (status 2xx), parse sebagai JSON
                    return response.json();
                })
                .then(data => {
                    // Handler saat data JSON berhasil diterima dan di-parse dari proses_pesanan.php
                    console.log("Response from proses_pesanan.php:", data);

                    if (data.success) {
                        // Backend berhasil membuat pesanan di database (dengan status pending atau lainnya)

                        // *** Clear Cart dan Update UI (Dilakukan SETELAH order di backend success) ***
                        cart = [];
                        localStorage.removeItem('cart');
                        updateCartUI();

                        // Tutup modal keranjang jika terbuka
                        var cartModal = bootstrap.Modal.getInstance(document.getElementById('cartModal'));
                        if (cartModal) cartModal.hide();

                        // --- LOGIKA PENANGANAN METODE PEMBAYARAN ---
                        if (data.metode_pembayaran === 'midtrans' && data.snap_token) {
                            // Jika metode yang dipilih adalah Midtrans DAN backend memberikan Snap Token

                            console.log("Received Snap Token:", data.snap_token);

                            // Pastikan Snap.js library sudah terpasang
                            if (typeof snap !== 'undefined') {
                                snap.pay(data.snap_token, {
                                    onSuccess: function(result) {
                                        console.log('Midtrans Payment Success:', result);
                                        // Redirect ke halaman sukses dengan status pembayaran berhasil
                                        window.location.href = `payment/sukses.php?order_id=${data.id_pesanan}&payment_status=success&meja=${meja}`;                                    },
                                    onPending: function(result) {
                                        console.log('Midtrans Payment Pending:', result);
                                        // Redirect ke halaman sukses dengan status pembayaran pending
                                        window.location.href = `sukses.php?order_id=${data.id_pesanan}&payment_status=pending&meja=${meja}`;
                                    },
                                    onError: function(result) {
                                        console.log('Midtrans Payment Error:', result);
                                        // Redirect ke halaman sukses/status dengan status pembayaran gagal
                                        // Gunakan order_id dari data, fallback jika tidak ada
                                        window.location.href = `sukses.php?order_id=${data.id_pesanan || 'unknown'}&payment_status=failed`;
                                    },
                                    onClose: function() {
                                        console.log('Midtrans Snap pop-up closed.');
                                        // Redirect ke halaman sukses/status dengan status pembayaran dibatalkan user
                                        window.location.href = `sukses.php?order_id=${data.id_pesanan || 'unknown'}&payment_status=canceled`;
                                    }
                                });
                            } else {
                                console.error("Midtrans Snap library ('snap' object) is not available.");
                                alert("Gagal memuat form pembayaran online. Pesanan Anda telah tercatat, silakan hubungi staf untuk pembayaran atau coba lagi.");
                                // Redirect ke halaman status pesanan pending jika Snap tidak available
                                if (data.id_pesanan) {
                                    window.location.href = `sukses.php?order_id=${data.id_pesanan}&status=midtrans_snap_error`;
                                } else {
                                    // Jika bahkan ID pesanan tidak ada, ini error serius di backend
                                    alert("Gagal membuat pesanan. Mohon hubungi staf.");
                                    // Tetap di halaman sekarang.
                                }
                            }

                        } else if (data.metode_pembayaran === 'kasir') {
                            // Logika jika metode pembayaran adalah 'kasir'
                            console.log('Order created for Kasir payment:', data);
                            // Redirect ke halaman sukses dengan status kasir
                            window.location.href = `payment/kasir.php?order_id=${data.id_pesanan}&status=kasir&meja=${meja}`;


                        } else {
                            // Jika success=true tapi metode pembayaran tidak dikenali (kasus jarang terjadi)
                            console.warn("Received success response with unhandled payment method:", data.metode_pembayaran, data);
                            // Redirect ke halaman sukses dengan status created (atau unhandled)
                            window.location.href = `sukses.php?order_id=${data.id_pesanan}&status=created`;
                        }

                    } else {
                        // Backend mengembalikan success: false (Terjadi kesalahan di server sebelum inisiasi pembayaran)
                        console.error('Order creation failed on backend:', data.message);
                        alert('Terjadi kesalahan saat memproses pesanan: ' + data.message); // Tampilkan pesan error dari backend ke pengguna
                        // Keranjang dan modal dibiarkan apa adanya.
                    }
                    // --- AKHIR BAGIAN LOGIKA SETELAH SERVER RESPON ---

                })
                .catch(error => {
                    // Handler saat ada error pada Fetch API (masalah jaringan, server tidak reachable, error di-throw di chain .then)
                    console.error('Fetch request or processing error:', error);

                    // Re-enable tombol checkout jika terjadi error
                    const checkoutButton = document.getElementById('checkout-button');
                    checkoutButton.disabled = false;
                    checkoutButton.innerText = 'Checkout'; // Kembalikan teks tombol

                    // Tampilkan pesan error ke pengguna
                    let userErrorMessage = 'Terjadi kesalahan saat berkomunikasi dengan server.';
                    if (error.message) {
                        userErrorMessage += ' Detail: ' + error.message;
                    }
                    alert(userErrorMessage);

                    // Keranjang dan modal dibiarkan apa adanya.
                });
        });

        // Ambil data menu dari PHP yang sudah di-encode sebagai JSON
        // Pastikan variabel $data_makanan dan $data_minuman selalu diset di PHP
        const dataMakanan = <?php echo isset($data_makanan) ? json_encode($data_makanan) : '[]'; ?>;
        const dataMinuman = <?php echo isset($data_minuman) ? json_encode($data_minuman) : '[]'; ?>;

        // Fungsi untuk menampilkan menu berdasarkan data
        function tampilkanMenu(dataMenu, targetElementId) {
            const targetElement = document.getElementById(targetElementId);
            if (!targetElement) {
                console.error(`Element with ID ${targetElementId} not found.`);
                return;
            }
            targetElement.innerHTML = ''; // Kosongkan dulu

            if (dataMenu.length === 0) {
                targetElement.innerHTML = '<p class="text-center col-12">Tidak ada menu yang ditemukan.</p>';
                return;
            }


            dataMenu.forEach(menu => {
                const menuElement = document.createElement('div');
                menuElement.classList.add('col-lg-3', 'col-md-6', 'wow', 'fadeInUp');
                menuElement.setAttribute('data-wow-delay', '0.1s'); // Delay ini mungkin perlu disesuaikan atau dihapus jika bikin lambat

                // Check if the menu item is liked from localStorage for initial display
                const isLiked = localStorage.getItem(`liked_${menu.id_menu}`) === 'true';
                const buttonText = isLiked ? 'Disukai' : 'Suka';
                const buttonColor = isLiked ? 'btn-success' : 'btn-danger';
                // Gunakan jumlah likes dari data PHP jika ada
                const likesCount = menu.likes !== undefined ? menu.likes : 0;

                menuElement.innerHTML = `
                    <div class="team-item text-center rounded overflow-hidden">
                        <div class="rounded-circle overflow-hidden m-4">
                            <img class="img-fluid" src="../../images/${menu.gambar}" alt="${menu.nama_menu}">
                        </div>
                        <h5 class="mb-0">${menu.nama_menu}</h5>
                        <div class="flex-column text-center">
                            <h5>
                                <span class="text-primary">Rp${Number(menu.harga).toLocaleString('id-ID', {
                                    minimumFractionDigits: 0,
                                    maximumFractionDigits: 0
                                })}</span>
                            </h5>
                        </div>
                        <div class="d-flex justify-content-center mt-3">
                            <button class="btn btn-sm btn-primary add-to-cart" data-id="${menu.id_menu}" data-nama="${menu.nama_menu}" data-harga="${menu.harga}" data-gambar="${menu.gambar}">
                                <i class="fa fa-shopping-cart"></i> Keranjang
                            </button>
                           <button class="btn btn-sm like-button ms-3 ${buttonColor}" data-id-menu="${menu.id_menu}">
                                <i class="fa fa-heart"></i> ${buttonText} (${menu.likes})
                            </button>
                        </div>
                    </div>
                `;
                targetElement.appendChild(menuElement);
            });
        }

        // Fungsi untuk melakukan pencarian
        function lakukanPencarian(dataMenu, namaInputId, hargaMaxInputId, targetElementId) {
            const namaInput = document.getElementById(namaInputId);
            const hargaMaxInput = document.getElementById(hargaMaxInputId);

            if (!namaInput || !hargaMaxInput) {
                console.error("Search input elements not found.");
                return;
            }

            const nama = namaInput.value.toLowerCase();
            // Gunakan 0 sebagai nilai default jika input harga kosong, Infinity jika tidak valid
            const hargaMax = parseFloat(hargaMaxInput.value);
            const filterHarga = isNaN(hargaMax) ? Infinity : (hargaMax === 0 ? Infinity : hargaMax); // Treat 0 as no max price filter


            const hasilPencarian = dataMenu.filter(menu => {
                const namaMenu = menu.nama_menu.toLowerCase();
                const hargaMenu = parseFloat(menu.harga);

                // Filter berdasarkan nama (jika input nama tidak kosong)
                const namaMatch = nama === '' || namaMenu.includes(nama);

                // Filter berdasarkan harga (jika input harga tidak kosong dan valid)
                const hargaMatch = hargaMenu <= filterHarga;

                return namaMatch && hargaMatch;
            });

            tampilkanMenu(hasilPencarian, targetElementId);
        }

        // Event listener untuk pencarian makanan
        document.getElementById('search-makanan-nama')?.addEventListener('input', function() {
            lakukanPencarian(dataMakanan, 'search-makanan-nama', 'search-makanan-harga-max', 'daftar-makanan');
        });

        document.getElementById('search-makanan-harga-max')?.addEventListener('input', function() {
            lakukanPencarian(dataMakanan, 'search-makanan-nama', 'search-makanan-harga-max', 'daftar-makanan');
        });

        // Event listener untuk pencarian minuman
        document.getElementById('search-minuman-nama')?.addEventListener('input', function() {
            lakukanPencarian(dataMinuman, 'search-minuman-nama', 'search-minuman-harga-max', 'daftar-minuman');
        });


        document.getElementById('search-minuman-harga-max')?.addEventListener('input', function() {
            lakukanPencarian(dataMinuman, 'search-minuman-nama', 'search-minuman-harga-max', 'daftar-minuman');
        });

        // Inisialisasi tampilan menu awal
        tampilkanMenu(dataMakanan, 'daftar-makanan');
        tampilkanMenu(dataMinuman, 'daftar-minuman');

        // Event listeners untuk tombol Tambah ke Keranjang dan Suka sekarang ditangani oleh Delegation pada document.body
        // Anda TIDAK perlu lagi memanggil attachAddToCartListeners() dan attachLikeButtonListeners() di sini.


        // Fungsi untuk mengirim permintaan "Suka" ke server
        // Parameter 'button' tidak lagi diperlukan karena UI diupdate via delegation
        function likeMenu(idMenu, newLikedStatus) {
            fetch('like.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    // Kirim status like baru dan ID menu
                    body: `id_menu=${encodeURIComponent(idMenu)}&isLiked=${encodeURIComponent(newLikedStatus ? 1 : 0)}` // Kirim 1 atau 0
                })
                .then(response => {
                    if (!response.ok) {
                        // Tangani jika respon bukan OK
                        return response.text().then(text => {
                            console.error('HTTP error during like update!', response.status, response.statusText, 'Body:', text);
                            throw new Error(`HTTP error updating like status: ${response.status}`);
                        });
                    }
                    return response.text(); // Harusnya mengembalikan jumlah likes dalam teks
                })
                .then(likes => {
                    console.log(`Like status updated for menu ${idMenu}. New total likes: ${likes}`);
                    // UI sudah diupdate di event delegation click,
                    // Tapi kita bisa memastikan jumlahnya konsisten dengan server jika perlu
                    // Cari item menu yang sesuai di DOM dan update jumlah likesnya
                    const menuElement = document.querySelector(`.like-button[data-id-menu="${idMenu}"]`);
                    if (menuElement) {
                        const buttonText = localStorage.getItem(`liked_${idMenu}`) === 'true' ? 'Disukai' : 'Suka';
                        menuElement.innerHTML = `<i class="fa fa-heart"></i> ${buttonText} (${likes})`;
                    }

                })
                .catch(error => {
                    console.error('Error updating like status:', error);
                    // Jika ada error, mungkin perlu mengembalikan status like di UI ke sebelum klik
                    // Atau hanya memberi notifikasi error
                    alert('Terjadi kesalahan saat memperbarui status suka.');
                    // Optional: Revert localStorage state if fetch failed
                    const previousStatus = !newLikedStatus;
                    localStorage.setItem(`liked_${idMenu}`, previousStatus);
                    // Optional: Find the button and revert its UI state
                    const menuElement = document.querySelector(`.like-button[data-id-menu="${idMenu}"]`);
                    if (menuElement) {
                        // Re-fetch data menu to get correct likes count, or estimate
                        // This can be complex. Simpler: just show error.
                        console.warn("Could not revert UI for like button easily.");
                    }
                });
        }

        // Fungsi untuk mendapatkan parameter dari URL (Tidak lagi digunakan dalam kode ini)
        // function getParameterByName(name, url = window.location.href) { ... }
        // Jika Anda perlu membaca parameter dari URL setelah redirect (misal: order_id)
        // Anda bisa menambahkannya di sini atau di bagian lain kode Anda
        // Example: check for URL parameters on page load

        const orderSuccess = urlParams.get('order_success');
        const orderPending = urlParams.get('order_pending');
        const orderError = urlParams.get('order_error');
        const orderClosed = urlParams.get('order_closed');
        const orderKasir = urlParams.get('order_kasir');


        if (orderId) {
            if (orderSuccess) {
                alert(`Pesanan #${orderId} berhasil dibuat dan pembayaran sukses!`);
            } else if (orderPending) {
                alert(`Pesanan #${orderId} berhasil dibuat dan menunggu pembayaran. Silakan selesaikan transaksi Anda.`);
            } else if (orderError) {
                alert(`Pesanan #${orderId} berhasil dibuat, tetapi terjadi kesalahan saat pembayaran. Silakan coba lagi.`);
            } else if (orderClosed) {
                alert(`Anda menutup jendela pembayaran untuk Pesanan #${orderId}. Pesanan Anda menunggu pembayaran.`);
            } else if (orderKasir) {
                alert(`Pesanan #${orderId} berhasil dibuat dengan metode pembayaran Kasir. Silakan bayar di kasir.`);
            }
            // Optional: Clear URL parameters after showing message
            history.replaceState({}, document.title, window.location.pathname); // Requires HTML5 History API
        }

    });
</script>