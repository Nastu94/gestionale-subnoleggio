<?php

namespace Database\Seeders;

use App\Models\{User, Organization};
use Illuminate\Database\Seeder;

/**
 * Seeder "production-safe":
 * - Crea SOLO:
 *   1) Organization admin (Sodano Consulting) con dati minimi
 *   2) Organization admin (proprietario) con dati più completi
 *   3) 2 utenti admin collegati alle rispettive organization
 *
 * NOTA RUOLI:
 * Il ruolo "admin" viene assegnato dal RolesAndPermissionsSeeder
 * in base a Organization.type = 'admin'.
 */
class DomainDemoSeeder extends Seeder
{
    public function run(): void
    {
        // === INPUT DA ENV (evitiamo hardcode di dati che cambiano) ===
        $ownerAdminEmail = env('OWNER_ADMIN_EMAIL');
        $ownerAdminPassword = env('OWNER_ADMIN_PASSWORD');
        $adminUserPassword = env('ADMIN_USER_PASSWORD');

        $ownerOrgName = env('OWNER_ORG_NAME');

        throw_if(empty($adminUserPassword), new \RuntimeException('Missing ADMIN_USER_PASSWORD in .env'));
        throw_if(empty($ownerAdminEmail) || empty($ownerAdminPassword), new \RuntimeException('Missing OWNER_ADMIN_EMAIL / OWNER_ADMIN_PASSWORD in .env'));
        throw_if(empty($ownerOrgName), new \RuntimeException('Missing OWNER_ORG_NAME in .env'));

        // === ORG 1: Sodano (minimale) ===
        $adminOrg = Organization::query()->updateOrCreate(
            [
                'type'  => 'admin',
                'email' => 'admin@sodanoconsulting.it',
            ],
            [
                'name'      => env('SODANO_ORG_NAME', 'Sodano Consulting'),
                'is_active' => true,
            ]
        );

        // === ORG 2: Proprietario (più completa) ===
        $ownerOrg = Organization::query()->updateOrCreate(
            [
                'type' => 'admin',
                'name' => $ownerOrgName,
            ],
            [
                'vat'          => env('OWNER_ORG_VAT') ?? null,
                'address_line' => env('OWNER_ORG_ADDRESS_LINE') ?? null,
                'city'         => env('OWNER_ORG_CITY') ?? null,
                'province'     => env('OWNER_ORG_PROVINCE') ?? null,
                'postal_code'  => env('OWNER_ORG_POSTAL_CODE') ?? null,
                'country_code' => env('OWNER_ORG_COUNTRY_CODE', 'IT'),
                'phone'        => env('OWNER_ORG_PHONE') ?? null,
                'email'        => env('OWNER_ORG_EMAIL', $ownerAdminEmail),
                'is_active'    => true,
            ]
        );

        // === USER 1: tuo admin (Sodano) ===
        $adminUser = User::query()->updateOrCreate(
            [
                'email' => 'admin@sodanoconsulting.it',
            ],
            [
                'organization_id' => $adminOrg->id,
                'name'            => env('ADMIN_USER_NAME', 'Admin'),
                /**
                 * Nel tuo User model hai cast 'password' => 'hashed':
                 * quindi puoi passare la password in chiaro e Laravel la hasha automaticamente.
                 */
                'password'        => $adminUserPassword,
                'is_active'       => true,
            ]
        );

        // email_verified_at NON è fillable nel tuo User model, quindi usiamo forceFill.
        $adminUser->forceFill(['email_verified_at' => now()])->save();

        // === USER 2: admin proprietario ===
        $ownerUser = User::query()->updateOrCreate(
            [
                'email' => $ownerAdminEmail,
            ],
            [
                'organization_id' => $ownerOrg->id,
                'name'            => env('OWNER_ADMIN_NAME'),
                'password'        => $ownerAdminPassword,
                'is_active'       => true,
            ]
        );

        $ownerUser->forceFill(['email_verified_at' => now()])->save();
    }
}
