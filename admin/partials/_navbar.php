<!-- partial:partials/_horizontal-navbar.html -->
<div class="horizontal-menu">
        <nav class="navbar top-navbar col-lg-12 col-12 p-0">
          <div class="container">
            <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
              <a class="navbar-brand brand-logo" href="#">
                <img src="../assets/images/dalrum.png" alt="logo" />
              </a>
              <a class="navbar-brand brand-logo-mini" href="#" alt="logo" /></a>
            </div>
            <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
              <ul class="navbar-nav navbar-nav-right">
                <li class="nav-item nav-profile dropdown">
                  <a class="nav-link" id="profileDropdown" href="#" data-toggle="dropdown" aria-expanded="false">
                    <div class="nav-profile-img">
                      <img src="../assets/images/faces/face1.jpg" alt="image" />
                    </div>
                    <div class="nav-profile-text">
                      <p class="text-black font-weight-semibold m-0"> <?php echo $username; ?> </p>
                      <span class="font-13 online-color"><?php echo $role; ?> <i class="mdi mdi-chevron-down"></i></span>
                    </div>
                  </a>
                  <div class="dropdown-menu navbar-dropdown" aria-labelledby="profileDropdown">
                    <a class="dropdown-item" href="../auth/logout.php">
                      <i class="mdi mdi-logout mr-2 text-primary"></i> Logout </a>
                  </div>
                </li>
              </ul>
              <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="horizontal-menu-toggle">
                <span class="mdi mdi-menu"></span>
              </button>
            </div>
          </div>
        </nav>
        <nav class="bottom-navbar">
          <div class="container">
            <ul class="nav page-navigation">
              <li class="nav-item">
                <a class="nav-link" href="../dashboard/">
                  <i class="mdi mdi-compass-outline menu-icon"></i>
                  <span class="menu-title">Dashboard</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../meja/index.php">
                  <i class="mdi mdi-table menu-icon"></i>
                  <span class="menu-title">Meja</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../pelanggan/index.php">
                  <i class="mdi mdi-human-male-female menu-icon"></i>
                  <span class="menu-title">Pelanggan</span>
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../pesanan/index.php">
                  <i class="mdi mdi-cart menu-icon"></i>
                  <span class="menu-title">Pesanan</span>
                </a>
              </li>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="../menu/index.php">
                  <i class="mdi mdi-food menu-icon"></i>
                  <span class="menu-title">Menu</span>
                </a>
              </li>
              <li class="nav-item">
              </li>
            </ul>
          </div>
        </nav>
      </div>
      <!-- partial -->