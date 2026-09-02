<?php

use App\Exceptions\UserPlanLimitReached;
use App\Models\Rol;
use App\Models\User;
use App\Services\UserAdministrationService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$storeId = (int) ($argv[1] ?? 0);
$actorId = (int) ($argv[2] ?? 0);
$email = (string) ($argv[3] ?? 'worker@example.test');
$roleId = Rol::query()->where('nombre', 'Vendedor')->value('id');
$actor = User::query()->findOrFail($actorId);

try {
    app(UserAdministrationService::class)->create($storeId, [
        'name' => 'Usuario concurrente',
        'email' => $email,
        'rol_id' => $roleId,
        'telefono' => null,
        'password' => 'password1',
    ], $actor);

    echo 'created';
} catch (UserPlanLimitReached) {
    echo 'rejected';
}
