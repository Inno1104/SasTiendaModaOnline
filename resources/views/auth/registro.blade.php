<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear mi tienda — SaaS Tienda Moda</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 30px 15px;
        }
        .reg-card {
            width: 760px; max-width: 96vw; background: #fff; border-radius: 20px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.4); overflow: hidden;
        }
        .reg-head {
            background: linear-gradient(160deg, #e8398c 0%, #c2185b 40%, #7b1fa2 100%);
            color: #fff; padding: 32px 40px; text-align: center;
        }
        .reg-head .logo { font-size: 34px; margin-bottom: 8px; }
        .reg-head h1 { font-size: 24px; font-weight: 700; }
        .reg-head p { font-size: 13px; opacity: .85; margin-top: 4px; }
        .reg-body { padding: 32px 40px 40px; }
        .alert-error {
            background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca;
            padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 20px;
        }
        .alert-error ul { margin: 0; padding-left: 18px; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
        input[type=text], input[type=email], input[type=password] {
            width: 100%; padding: 12px 14px; border: 1.5px solid #e5e7eb; border-radius: 10px;
            font-family: inherit; font-size: 14px; transition: border-color .2s;
        }
        input:focus { outline: none; border-color: #e8398c; }
        .section-title { font-size: 12px; text-transform: uppercase; letter-spacing: .5px;
            color: #9ca3af; font-weight: 600; margin: 22px 0 10px; }
        .planes { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; }
        .plan {
            border: 2px solid #e5e7eb; border-radius: 12px; padding: 16px; cursor: pointer;
            text-align: center; transition: all .2s; position: relative;
        }
        .plan:hover { border-color: #f9a8d4; }
        .plan input { position: absolute; opacity: 0; }
        .plan.sel { border-color: #e8398c; background: #fdf2f8; }
        .plan .pn { font-weight: 700; font-size: 15px; color: #111827; }
        .plan .pp { font-size: 20px; font-weight: 700; color: #e8398c; margin: 6px 0; }
        .plan .pp small { font-size: 11px; color: #9ca3af; font-weight: 400; }
        .plan .pf { font-size: 11px; color: #6b7280; line-height: 1.7; }
        .btn-reg {
            width: 100%; margin-top: 26px; padding: 14px; border: none; border-radius: 10px;
            background: linear-gradient(135deg, #e8398c, #c2185b); color: #fff; font-family: inherit;
            font-size: 15px; font-weight: 600; cursor: pointer; transition: transform .15s;
        }
        .btn-reg:hover { transform: translateY(-2px); }
        .reg-foot { text-align: center; margin-top: 18px; font-size: 13px; color: #6b7280; }
        .reg-foot a { color: #e8398c; font-weight: 600; text-decoration: none; }
        @media (max-width: 640px) { .grid2, .planes { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="reg-card">
        <div class="reg-head">
            <div class="logo"><i class="fa-solid fa-shirt"></i></div>
            <h1>Crea tu tienda</h1>
            <p>Empieza gratis con 15 días de prueba — sin tarjeta de crédito</p>
        </div>
        <div class="reg-body">
            @if ($errors->any())
                <div class="alert-error">
                    <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="POST" action="{{ route('registro.store') }}">
                @csrf
                <div class="form-group">
                    <label>Nombre de tu tienda</label>
                    <input type="text" name="tienda" value="{{ old('tienda') }}" placeholder="Ej. Moda Bella" required>
                </div>
                <div class="grid2">
                    <div class="form-group">
                        <label>Tu nombre</label>
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Nombre del administrador" required>
                    </div>
                    <div class="form-group">
                        <label>Correo electrónico</label>
                        <input type="email" name="email" value="{{ old('email') }}" placeholder="tu@correo.com" required>
                    </div>
                </div>
                <div class="grid2">
                    <div class="form-group">
                        <label>Contraseña</label>
                        <input type="password" name="password" placeholder="Mínimo 6 caracteres" required>
                    </div>
                    <div class="form-group">
                        <label>Confirmar contraseña</label>
                        <input type="password" name="password_confirmation" placeholder="Repite la contraseña" required>
                    </div>
                </div>

                @if($planes->count())
                <div class="section-title">Elige tu plan</div>
                <div class="planes">
                    @foreach($planes as $i => $plan)
                    <label class="plan {{ $i === 0 ? 'sel' : '' }}" onclick="selPlan(this)">
                        <input type="radio" name="plan_id" value="{{ $plan->id }}" {{ $i === 0 ? 'checked' : '' }}>
                        <div class="pn">{{ $plan->nombre }}</div>
                        <div class="pp">$ {{ number_format($plan->precio, 0) }}<small>/mes</small></div>
                        <div class="pf">
                            {{ $plan->max_productos == -1 ? 'Productos ilimitados' : $plan->max_productos.' productos' }}<br>
                            {{ $plan->max_usuarios == -1 ? 'Usuarios ilimitados' : $plan->max_usuarios.' usuarios' }}
                        </div>
                    </label>
                    @endforeach
                </div>
                @endif

                <button type="submit" class="btn-reg"><i class="fa-solid fa-rocket"></i> Crear mi tienda</button>
            </form>

            <div class="reg-foot">¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a></div>
        </div>
    </div>

    <script>
        function selPlan(el) {
            document.querySelectorAll('.plan').forEach(p => p.classList.remove('sel'));
            el.classList.add('sel');
            el.querySelector('input').checked = true;
        }
    </script>
</body>
</html>
