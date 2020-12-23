<?php

namespace Outlandish\Wpackagist\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class PackageExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_versions', [$this, 'formatVersions']),
        ];
    }

    public function formatVersions(?array $versionsIn): array
    {
        if (empty($versionsIn)) {
            return [];
        }

        $versions = array_keys($versionsIn);
        usort($versions, 'version_compare');

        return $versions;
    }
}
