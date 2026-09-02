<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — SaaS Tienda Moda</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --pink: #e0004d;
            --wine: #8c003d;
            --navy: #11132f;
            --muted: #716f88;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: var(--navy);
            background: #f9f8fb;
        }

        .login-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1fr 1fr;
            overflow: hidden;
        }

        .brand-panel {
            min-height: 100vh;
            position: relative;
            isolation: isolate;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 8vw;
            color: #fff;
            background:
                radial-gradient(circle at 75% 28%, rgba(22, 0, 25, .08), rgba(12, 0, 25, .72) 57%, rgba(11, 0, 25, .92) 100%),
                linear-gradient(145deg, #d20043 0%, #7d0039 51%, #13001f 100%);
        }

        .brand-panel::before {
            content: '';
            position: absolute;
            z-index: -1;
            width: 590px;
            height: 590px;
            top: -155px;
            right: -150px;
            border: 95px solid rgba(255, 30, 101, .22);
            border-radius: 50%;
        }

        .dot-pattern {
            position: absolute;
            width: 108px;
            height: 82px;
            opacity: .45;
            background-image: radial-gradient(#fff 1.7px, transparent 2px);
            background-size: 22px 22px;
        }

        .dots-top { top: 25px; left: 24px; }
        .dots-bottom { bottom: 25px; left: 24px; }

        .brand-content {
            width: min(100%, 550px);
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .brand-logo {
            width: 138px;
            height: 138px;
            margin: 0 auto 20px;
            display: grid;
            place-items: center;
            border-radius: 27px;
            font-size: 64px;
            background: linear-gradient(145deg, #ee0050, #a70049);
            box-shadow: 0 18px 35px rgba(34, 0, 22, .25);
        }

        .brand-title {
            font-size: clamp(32px, 3vw, 48px);
            line-height: 1.2;
            font-weight: 800;
            letter-spacing: -.8px;
        }

        .brand-subtitle {
            margin: 18px 0 30px;
            font-size: 20px;
            line-height: 1.6;
            color: rgba(255, 255, 255, .88);
        }

        .features { text-align: left; }

        .feature {
            display: grid;
            grid-template-columns: 52px 1fr;
            align-items: center;
            gap: 16px;
            min-height: 66px;
            border-bottom: 1px solid rgba(255, 255, 255, .07);
            font-size: 17px;
        }

        .feature:last-child { border-bottom: 0; }

        .feature-icon {
            width: 48px;
            height: 48px;
            display: grid;
            place-items: center;
            border-radius: 11px;
            font-size: 21px;
            background: linear-gradient(145deg, #ee0756, #a70047);
            box-shadow: 0 9px 18px rgba(30, 0, 18, .2);
        }

        .trust-bar {
            margin-top: 35px;
            padding: 20px 24px;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            border-radius: 25px;
            text-align: left;
            background: rgba(122, 25, 80, .48);
            backdrop-filter: blur(8px);
        }

        .trust-item { display: flex; gap: 11px; align-items: center; }
        .trust-item + .trust-item { border-left: 1px solid rgba(255,255,255,.2); padding-left: 16px; }
        .trust-item i { font-size: 26px; }
        .trust-item strong { display: block; font-size: 11px; }
        .trust-item small { display: block; margin-top: 3px; font-size: 9px; opacity: .85; }

        .form-panel {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 50px clamp(28px, 5vw, 84px);
            background:
                radial-gradient(circle at 65% 4%, rgba(255,255,255,.95), transparent 36%),
                #faf9fb;
        }

        .login-card {
            width: min(100%, 630px);
            padding: 38px clamp(30px, 4vw, 64px) 34px;
            border: 1px solid rgba(30, 20, 50, .04);
            border-radius: 20px;
            background: rgba(255,255,255,.88);
            box-shadow: 0 22px 50px rgba(35, 25, 55, .13);
        }

        .security-icon {
            width: 70px;
            height: 70px;
            margin: 0 auto 12px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            color: #fff;
            font-size: 31px;
            background: linear-gradient(145deg, #cc0045, #92003d);
            box-shadow: 0 9px 20px rgba(140, 0, 61, .26);
        }

        .form-header { text-align: center; margin-bottom: 31px; }
        .form-header h1 { font-size: clamp(26px, 2.2vw, 37px); font-weight: 800; letter-spacing: -1px; }
        .form-header p { margin-top: 5px; color: var(--muted); font-size: 16px; }

        .alert-error {
            margin-bottom: 18px;
            padding: 12px 14px;
            border: 1px solid #f7c2d2;
            border-radius: 10px;
            color: #a50039;
            background: #fff4f7;
            font-size: 13px;
        }

        .form-group { margin-bottom: 17px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 13px; font-weight: 700; }
        .input-wrap { position: relative; }
        .field-icon, .password-toggle {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
        }
        .field-icon { left: 18px; color: #d9004a; }
        .password-toggle { right: 17px; color: #77758b; font-size: 17px; cursor: pointer; }

        .input-wrap input {
            width: 100%;
            height: 58px;
            padding: 0 48px 0 55px;
            border: 1px solid #ddd5df;
            border-radius: 11px;
            outline: none;
            color: #28263d;
            background: linear-gradient(90deg, #fff9fb, #fdf7fa);
            font: 400 14px 'Poppins', sans-serif;
            transition: .2s ease;
        }

        .input-wrap input:focus { border-color: #db0b50; box-shadow: 0 0 0 4px rgba(224,0,77,.08); background: #fff; }
        .field-error { display: block; margin-top: 6px; color: #c70043; font-size: 11px; }

        .form-options {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin: 3px 0 22px;
            font-size: 12px;
        }

        .remember-me { display: flex; gap: 9px; align-items: center; color: #57556b; cursor: pointer; }
        .remember-me input { width: 17px; height: 17px; accent-color: var(--pink); }
        .forgot-password { color: var(--pink); font-weight: 500; }

        .btn-login {
            width: 100%;
            height: 57px;
            border: 0;
            border-radius: 11px;
            color: #fff;
            background: linear-gradient(100deg, #e4004e, #8e003e);
            font: 600 16px 'Poppins', sans-serif;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(157,0,62,.16);
            transition: transform .2s, box-shadow .2s;
        }

        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 12px 23px rgba(157,0,62,.26); }
        .btn-login i { margin-right: 10px; }

        .divider { display: flex; align-items: center; gap: 15px; margin: 22px 0; color: #747187; font-size: 12px; }
        .divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e7e2e8; }

        .register-link {
            min-height: 58px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 12px 18px;
            border: 1px solid #ddd8e0;
            border-radius: 10px;
            color: var(--navy);
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            font-weight: 600;
            transition: .2s ease;
        }

        .register-link i, .register-link span { color: var(--pink); }
        .register-link:hover { border-color: #df0a50; background: #fff8fa; }

        .form-footer { margin-top: 25px; text-align: center; color: #6c6980; font-size: 11px; line-height: 1.7; }

        @media (max-width: 1050px) {
            .brand-panel { padding: 42px; }
            .trust-bar { display: none; }
        }

        @media (max-width: 800px) {
            .login-page { display: block; padding: 28px 18px; background: linear-gradient(145deg, #b90045, #190023); }
            .brand-panel { min-height: auto; padding: 8px 15px 30px; background: transparent; overflow: visible; }
            .brand-panel::before, .dot-pattern, .features, .brand-subtitle { display: none; }
            .brand-logo { width: 74px; height: 74px; margin-bottom: 10px; border-radius: 17px; font-size: 34px; }
            .brand-title { font-size: 25px; }
            .form-panel { min-height: auto; padding: 0; background: transparent; }
            .login-card { padding: 30px 25px; border-radius: 18px; }
            .security-icon { width: 56px; height: 56px; font-size: 25px; }
        }

        @media (max-width: 420px) {
            .login-page { padding: 22px 12px; }
            .login-card { padding: 26px 20px; }
            .form-header h1 { font-size: 24px; }
            .form-header p { font-size: 13px; }
            .form-options { align-items: flex-start; }
        }
    </style>
</head>
<body>
    <main class="login-page">
        <section class="brand-panel" aria-label="Beneficios de SaaS Tienda Moda">
            <span class="dot-pattern dots-top" aria-hidden="true"></span>
            <span class="dot-pattern dots-bottom" aria-hidden="true"></span>

            <div class="brand-content">
                <div class="brand-logo" aria-hidden="true"><i class="fa-solid fa-shirt"></i></div>
                <h2 class="brand-title">Tienda Moda</h2>
                <p class="brand-subtitle">Sistema integral de gestión<br>para tu tienda de moda</p>

                <div class="features">
                    <div class="feature"><span class="feature-icon"><i class="fa-solid fa-cart-shopping"></i></span><span>Punto de venta rápido y eficiente</span></div>
                    <div class="feature"><span class="feature-icon"><i class="fa-solid fa-boxes-stacked"></i></span><span>Control de inventario en tiempo real</span></div>
                    <div class="feature"><span class="feature-icon"><i class="fa-solid fa-users"></i></span><span>Gestión de clientes y fidelización</span></div>
                    <div class="feature"><span class="feature-icon"><i class="fa-solid fa-chart-column"></i></span><span>Reportes y estadísticas avanzadas</span></div>
                </div>

                <div class="trust-bar">
                    <div class="trust-item"><i class="fa-regular fa-shield"></i><div><strong>Seguro y confiable</strong><small>Tus datos están protegidos</small></div></div>
                    <div class="trust-item"><i class="fa-solid fa-bolt"></i><div><strong>Acceso rápido</strong><small>Gestiona desde cualquier lugar</small></div></div>
                    <div class="trust-item"><i class="fa-solid fa-headset"></i><div><strong>Soporte 24/7</strong><small>Estamos aquí para ayudarte</small></div></div>
                </div>
            </div>
        </section>

        <section class="form-panel">
            <div class="login-card">
                <header class="form-header">
                    <div class="security-icon" aria-hidden="true"><i class="fa-solid fa-shield-halved"></i></div>
                    <h1>¡Bienvenido de nuevo!</h1>
                    <p>Ingresa tus credenciales para continuar</p>
                </header>

                @if(session('error'))
                    <div class="alert-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> {{ session('error') }}</div>
                @endif

                <form method="POST" action="{{ route('login.post') }}">
                    @csrf
                    <div class="form-group">
                        <label for="email">Correo electrónico</label>
                        <div class="input-wrap">
                            <i class="fa-solid fa-envelope field-icon" aria-hidden="true"></i>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="ejemplo@tiendamoda.com" autocomplete="email" required autofocus>
                        </div>
                        @error('email') <small class="field-error">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-group">
                        <label for="password">Contraseña</label>
                        <div class="input-wrap">
                            <i class="fa-solid fa-lock field-icon" aria-hidden="true"></i>
                            <input type="password" id="password" name="password" placeholder="••••••••" autocomplete="current-password" required>
                            <button class="password-toggle" type="button" aria-label="Mostrar contraseña" aria-pressed="false">
                                <i class="fa-regular fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        @error('password') <small class="field-error">{{ $message }}</small> @enderror
                    </div>

                    <div class="form-options">
                        <label class="remember-me"><input type="checkbox" name="remember" {{ old('remember') ? 'checked' : '' }}> Recordarme</label>
                        <span class="forgot-password">¿Olvidaste tu contraseña?</span>
                    </div>

                    <button type="submit" class="btn-login"><i class="fa-solid fa-arrow-right-to-bracket"></i> Ingresar al sistema</button>
                </form>

                <div class="divider">o continúa con</div>

                <a class="register-link" href="{{ route('registro') }}">
                    <i class="fa-solid fa-store"></i>
                    <strong>¿No tienes una tienda? <span>Créala gratis&nbsp; →</span></strong>
                </a>

                <footer class="form-footer">
                    <p>Sistema de Gestión © {{ date('Y') }} SaaS Tienda Moda</p>
                    <p>Demo: admin@tiendamoda.com / admin123 · Super admin:<br>superadmin@tiendamoda.com / super123</p>
                </footer>
            </div>
        </section>
    </main>

    <script>
        const toggle = document.querySelector('.password-toggle');
        const password = document.getElementById('password');

        toggle.addEventListener('click', () => {
            const isVisible = password.type === 'text';
            password.type = isVisible ? 'password' : 'text';
            toggle.setAttribute('aria-label', isVisible ? 'Mostrar contraseña' : 'Ocultar contraseña');
            toggle.setAttribute('aria-pressed', String(!isVisible));
            toggle.querySelector('i').className = isVisible ? 'fa-regular fa-eye' : 'fa-regular fa-eye-slash';
        });
    </script>
</body>
</html>
