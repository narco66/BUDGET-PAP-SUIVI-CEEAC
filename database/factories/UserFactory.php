<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prenoms = ['Amina', 'Patrick', 'Clarisse', 'Jean-Bosco', 'Nadine', 'Emmanuel', 'Grace', 'Armand', 'Mireille', 'Dieudonne'];
        $noms = ['Ondo', 'Moukoko', 'Nguema', 'Mba', 'Tchicaya', 'Mbarga', 'Koumba', 'Essono', 'Mayi', 'Ndzana'];
        $name = fake()->randomElement($prenoms).' '.fake()->randomElement($noms);

        return [
            'name' => $name,
            'email' => Str::slug($name, '.').fake()->unique()->numberBetween(100, 999).'@ceeac.demo',
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'actif' => true,
            'statut' => 'actif',
            'doit_changer_mot_de_passe' => false,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function institutional(string $role, ?int $structureId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'matricule' => 'CEEAC-'.strtoupper(Str::random(8)),
            'structure_id' => $structureId,
            'fonction_id' => null,
            'telephone' => '+241 '.fake()->numerify('## ## ## ##'),
        ])->afterCreating(function (User $user) use ($role, $structureId) {
            if ($role !== '') {
                $user->assignRole($role);
            }

            if ($structureId) {
                $user->structures()->syncWithoutDetaching([$structureId => ['principal' => true]]);
            }
        });
    }
}
