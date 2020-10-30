<?php

namespace Outlandish\Wpackagist\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class PackageExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_category', [$this, 'formatCategory']),
            new TwigFilter('format_versions', [$this, 'formatVersions']),
        ];
    }

    /**
     * @param string $category
     * @return string
     */
    public function formatCategory($category): string
    {
        return str_replace('Outlandish\Wpackagist\Entity\\', '', $category);
    }

    public function formatVersions(?array $versionsIn): array
    {
        $versions = array_keys($versionsIn);
        usort($versions, 'version_compare');

        return $versions;
    }
}
