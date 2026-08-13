<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permissions granted to every role that has at least module-level access.
     * Finer-grained "own records only" restrictions (e.g. an agent only seeing
     * conversations assigned to them) are enforced via Policies, not permissions.
     */
    private const PERMISSIONS = [
        'contacts.view',
        'contacts.manage',
        'phonebook.manage',
        'conversations.view',
        'conversations.reply',
        'conversations.assign',
        'pipeline.manage',
        'campaigns.view',
        'campaigns.manage',
        'automations.manage',
        'chatbots.view',
        'chatbots.manage',
        'voice-agents.view',
        'voice-agents.manage',
        'whatsapp-calling.view',
        'whatsapp-calling.manage',
        'analytics.view',
        'team.manage',
        'settings.manage',
        'settings.notifications',
    ];

    /**
     * Provisioning-only permissions, granted solely to the superadmin role.
     */
    private const SUPERADMIN_PERMISSIONS = [
        'companies.manage',
        'company-admins.manage',
    ];

    private const ROLE_PERMISSIONS = [
        'superadmin' => self::SUPERADMIN_PERMISSIONS,
        'admin' => self::PERMISSIONS,
        'manager' => [
            'contacts.view',
            'contacts.manage',
            'phonebook.manage',
            'conversations.view',
            'conversations.reply',
            'conversations.assign',
            'pipeline.manage',
            'campaigns.view',
            'campaigns.manage',
            'automations.manage',
            'chatbots.view',
            'chatbots.manage',
            'voice-agents.view',
            'voice-agents.manage',
            'whatsapp-calling.view',
            'whatsapp-calling.manage',
            'analytics.view',
            'settings.notifications',
        ],
        'agent' => [
            'contacts.view',
            'conversations.view',
            'conversations.reply',
            'pipeline.manage',
            'settings.notifications',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([...self::PERMISSIONS, ...self::SUPERADMIN_PERMISSIONS] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($permissions);
        }
    }
}
