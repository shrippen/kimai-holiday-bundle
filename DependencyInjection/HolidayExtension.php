<?php

/*
 * This file is part of the HolidayBundle plugin for Kimai.
 */

namespace KimaiPlugin\HolidayBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

class HolidayExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Prefer plugin templates over Kimai core (e.g. user/contract.html.twig).
        $container->prependExtensionConfig('twig', [
            'paths' => [
                \dirname(__DIR__) . '/Resources/views' => null,
            ],
        ]);

        $permissions = [
            'hours_own_profile',
            'hours_other_profile',
            'contract_other_profile',
            'view_booking_contract',
            'create_booking_contract',
            'approve_times_contract',
            'unlock_times_contract',
            'workdays_override_timesheet',
            'absence',
            'edit_own_absence',
            'edit_other_absence',
            'delete_own_absence',
            'delete_other_absence',
            'view_team_absence',
            'view_other_absence',
            'approve_own_absence',
            'approve_other_absence',
            'approval_other_absence',
            'edit_public_holidays',
        ];

        $container->prependExtensionConfig('kimai', [
            'permissions' => [
                'roles' => [
                    'ROLE_SUPER_ADMIN' => $permissions,
                    'ROLE_ADMIN' => [
                        'hours_own_profile',
                        'hours_other_profile',
                        'contract_other_profile',
                        'view_booking_contract',
                        'create_booking_contract',
                        'approve_times_contract',
                        'unlock_times_contract',
                        'workdays_override_timesheet',
                        'absence',
                        'edit_own_absence',
                        'edit_other_absence',
                        'delete_own_absence',
                        'delete_other_absence',
                        'view_team_absence',
                        'view_other_absence',
                        'approve_own_absence',
                        'approve_other_absence',
                        'approval_other_absence',
                        'edit_public_holidays',
                    ],
                    'ROLE_TEAMLEAD' => [
                        'hours_own_profile',
                        'hours_other_profile',
                        'view_booking_contract',
                        'absence',
                        'edit_own_absence',
                        'delete_own_absence',
                        'view_team_absence',
                        'view_other_absence',
                        'approve_other_absence',
                    ],
                    'ROLE_USER' => [
                        'hours_own_profile',
                        'absence',
                        'edit_own_absence',
                        'delete_own_absence',
                    ],
                ],
            ],
        ]);

        $container->prependExtensionConfig('jms_serializer', [
            'metadata' => [
                'warmup' => [
                    'paths' => [
                        'included' => [
                            __DIR__ . '/../Entity/',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
