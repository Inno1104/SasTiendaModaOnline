# Ejecutar como Administrador
# Configura Laragon para servir SaaS Tienda Moda en puerto 8090

$nginxConf = @"
server {
    listen 8090;
    server_name 127.0.0.1 localhost saas_tienda_moda.test;
    root "C:/SAAS/saas_tienda_moda/public";
    index index.php index.html;
    charset utf-8;

    location / {
        try_files `$uri `$uri/ /index.php?`$query_string;
    }

    location ~ \.php$ {
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass php_upstream;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME `$document_root`$fastcgi_script_name;
        fastcgi_intercept_errors off;
        fastcgi_buffer_size 16k;
        fastcgi_buffers 4 16k;
    }

    location ~ /\.ht {
        deny all;
    }
}
"@

# Detectar ruta de Laragon
$laragonPaths = @(
    "C:\laragon\etc\nginx\sites-enabled",
    "D:\laragon\etc\nginx\sites-enabled",
    "$env:USERPROFILE\laragon\etc\nginx\sites-enabled"
)

$sitesPath = $null
foreach ($path in $laragonPaths) {
    if (Test-Path $path) {
        $sitesPath = $path
        break
    }
}

if ($null -eq $sitesPath) {
    Write-Host "ERROR: No se encontro Laragon. Rutas buscadas:" -ForegroundColor Red
    $laragonPaths | ForEach-Object { Write-Host "  - $_" }
    Write-Host ""
    Write-Host "Ingresa la ruta de Laragon manualmente:"
    $customPath = Read-Host "Ruta (ej: C:\laragon\etc\nginx\sites-enabled)"
    if (Test-Path $customPath) {
        $sitesPath = $customPath
    } else {
        Write-Host "Ruta no valida. Saliendo." -ForegroundColor Red
        pause
        exit
    }
}

Write-Host "Laragon encontrado en: $sitesPath" -ForegroundColor Green

# Escribir config
$confFile = Join-Path $sitesPath "saas_tienda_moda.conf"
$nginxConf | Out-File -FilePath $confFile -Encoding UTF8 -Force
Write-Host "Archivo creado: $confFile" -ForegroundColor Green

# Recargar Nginx de Laragon
$laragonRoot = Split-Path (Split-Path (Split-Path $sitesPath))
$nginxExe = Join-Path $laragonRoot "bin\nginx\nginx.exe"

if (Test-Path $nginxExe) {
    Write-Host "Recargando Nginx..." -ForegroundColor Yellow
    & $nginxExe -s reload
    Write-Host "Nginx recargado correctamente." -ForegroundColor Green
} else {
    Write-Host "Recarga Nginx manualmente desde Laragon -> Menu -> Nginx -> Reload" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "  Abre: http://127.0.0.1:8090" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
pause
