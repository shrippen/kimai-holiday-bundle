<?php

/*
 * This file is part of the HolidayBundle plugin for Kimai.
 */

namespace KimaiPlugin\HolidayBundle\Command;

use App\Command\AbstractBundleInstallerCommand;

class InstallCommand extends AbstractBundleInstallerCommand
{
    protected function getBundleCommandNamePart(): string
    {
        return 'holiday';
    }

    protected function getMigrationConfigFilename(): ?string
    {
        return __DIR__ . '/../Migrations/doctrine_migrations.yaml';
    }
}
