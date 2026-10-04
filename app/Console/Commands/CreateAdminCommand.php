<?php

namespace App\Console\Commands;

use App\Models\User;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crea (o habilita) una cuenta ADMIN pidiendo los datos de forma interactiva.
 *
 * La contraseña se escribe sin mostrarse en pantalla y nunca se acepta como
 * argumento, para que no quede en el historial de la terminal ni en el código.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'mekatos:crear-admin';

    protected $description = 'Crea un usuario administrador de Mekatos de forma interactiva y segura';

    public function handle(): int
    {
        $this->info('Crear administrador de Mekatos');
        $this->line('La contraseña no se mostrará mientras la escribes.');

        $name = trim((string) $this->ask('Nombre'));
        $email = mb_strtolower(trim((string) $this->ask('Correo electrónico')));

        $identity = Validator::make(
            ['name' => $name, 'email' => $email],
            ['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255']],
            [],
            ['name' => 'nombre', 'email' => 'correo electrónico']
        );

        if ($identity->fails()) {
            return $this->failWith($identity->errors()->all());
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing && ! $this->confirm("Ya existe un usuario con el correo {$email}. ¿Quieres asignarle una nueva contraseña y dejarlo como ADMIN activo?", false)) {
            $this->warn('Operación cancelada. No se modificó ningún usuario.');

            return self::FAILURE;
        }

        if ($existing?->isLocked()) {
            // El comando no desbloquea: el desbloqueo es una acción explícita de un ADMIN
            // o la recuperación de emergencia por correo.
            $this->warn('Este usuario está bloqueado por intentos fallidos. Este comando NO lo desbloquea: un ADMIN debe desbloquearlo desde Usuarios, o usar la recuperación por correo.');
        }

        $password = (string) $this->secret('Contraseña (mínimo 8 caracteres)');
        $confirmation = (string) $this->secret('Repite la contraseña');

        if ($password !== $confirmation) {
            return $this->failWith(['Las contraseñas no coinciden. No se modificó ningún usuario.']);
        }

        $passwordCheck = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', 'min:8']],
            [],
            ['password' => 'contraseña']
        );

        if ($passwordCheck->fails()) {
            return $this->failWith($passwordCheck->errors()->all());
        }

        // El modelo User aplica el cast 'hashed', así que la contraseña se guarda cifrada.
        if ($existing) {
            $existing->update([
                'name' => $name,
                'password' => $password,
                'role' => UserRole::Admin,
                'is_active' => true,
            ]);

            $this->info("Usuario {$email} actualizado como ADMIN activo.");

            return self::SUCCESS;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::Admin,
            'is_active' => true,
        ]);

        $this->info("Administrador {$email} creado correctamente.");

        return self::SUCCESS;
    }

    private function failWith(array $messages): int
    {
        foreach ($messages as $message) {
            $this->error($message);
        }

        return self::FAILURE;
    }
}
