<!DOCTYPE html>
<html lang="es">

<head>
    <?php include __DIR__ . '/../partials/head.php'; ?>

</head>

<body class="authentication-bg pb-0" data-layout-config='{"darkMode":false}'>

    <div class="auth-fluid">
        <!--Auth fluid left content -->
        <div class="auth-fluid-form-box">
            <div class="align-items-center d-flex h-100">
                <div class="card-body">

                    <!-- Logo -->
                    <div class="auth-brand text-center text-lg-start mb-4">
                        <a href="index.html" class="logo-dark">
                            <span><img src="public/assets/images/logo-dark.png" alt="" height="18"></span>
                        </a>
                        <a href="index.html" class="logo-light">
                            <span><img src="public/assets/images/logo.png" alt="" height="18"></span>
                        </a>
                    </div>

                    <!-- title-->
                    <h4 class="mt-4">Ingresar</h4>
                    <p class="text-muted mb-4">
                        Ingresa tu correo y contraseña para acceder a tu cuenta.
                    </p>

                    <!-- form -->
                    <form id="formLogin" method="POST">

                        <div class="mb-3">
                            <label for="emailaddress" class="form-label">
                                Correo Electronico
                            </label>
                            <input class="form-control" type="email" name="email" required="" placeholder="
                                Ingresa tu correo
                                ">
                        </div>
                        <div class="mb-3">
                            <a href="pages-recoverpw-2.html" class="text-muted float-end"><small>
                                    Olvidaste tu contraseña?
                                </small></a>
                            <label for="password" class="form-label">
                                Contraseña
                            </label>
                            <input class="form-control" type="password" required="" name="password" placeholder="
                                Ingresa tu contraseña
                                ">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="checkbox-signin">
                                <label class="form-check-label" for="checkbox-signin">
                                    Recordarme
                                </label>
                            </div>
                        </div>
                        <div class="d-grid mb-0 text-center">
                            <button class="btn btn-primary" type="submit"><i class="mdi mdi-login"></i>
                                Ingresar
                            </button>
                        </div>
                </div> <!-- end .card-body -->
            </div> <!-- end .align-items-center.d-flex.h-100-->
        </div>
        <!-- end auth-fluid-form-box-->

        <!-- Auth fluid right content -->
        <div class="auth-fluid-right text-center " style="display: none;">
            <div class="auth-user-testimonial">
                <h2 class="mb-3"></h2>
                <p class="lead"><i class="mdi mdi-format-quote-open"></i> It's a elegent templete. I love it very much!
                    . <i class="mdi mdi-format-quote-close"></i>
                </p>
                <p>
                    - Hyper Admin User
                </p>
            </div> <!-- end auth-user-testimonial-->
        </div>
        <!-- end Auth fluid right content -->
    </div>
    <!-- end auth-fluid-->

    <!-- bundle -->
    <script src="public/assets/js/vendor.min.js"></script>
    <script src="public/assets/js/app.min.js"></script>
    <script src="public/assets/js/login.js"></script>

</body>

</html>