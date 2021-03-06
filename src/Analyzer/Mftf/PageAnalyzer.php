<?php

/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\SemanticVersionChecker\Analyzer\Mftf;

use Magento\SemanticVersionChecker\Analyzer\AnalyzerInterface;
use Magento\SemanticVersionChecker\MftfReport;
use Magento\SemanticVersionChecker\Operation\Mftf\Page\PageAdded;
use Magento\SemanticVersionChecker\Operation\Mftf\Page\PageRemoved;
use Magento\SemanticVersionChecker\Operation\Mftf\Page\PageSectionAdded;
use Magento\SemanticVersionChecker\Operation\Mftf\Page\PageSectionRemoved;
use Magento\SemanticVersionChecker\Registry\XmlRegistry;
use Magento\SemanticVersionChecker\Scanner\MftfScanner;
use PHPSemVerChecker\Report\Report;

/**
 * Mftf Page analyzer class.
 */
class PageAnalyzer extends AbstractEntityAnalyzer implements AnalyzerInterface
{
    public const MFTF_SECTION_ELEMENT = "{}section";
    public const MFTF_DATA_TYPE = 'page';

    /**
     * MFTF page.xml analyzer
     *
     * @param XmlRegistry $registryBefore
     * @param XmlRegistry $registryAfter
     * @return Report
     */
    public function analyze($registryBefore, $registryAfter)
    {
        $beforeEntities = $registryBefore->data[MftfScanner::MFTF_ENTITY] ?? [];
        $afterEntities = $registryAfter->data[MftfScanner::MFTF_ENTITY] ?? [];

        foreach ($beforeEntities as $module => $entities) {
            $this->findAddedEntitiesInModule(
                $entities,
                $afterEntities[$module] ?? [],
                self::MFTF_DATA_TYPE,
                $this->getReport(),
                PageAdded::class,
                $module . '/Page'
            );
            foreach ($entities as $entityName => $beforeEntity) {
                if ($beforeEntity['type'] !== self::MFTF_DATA_TYPE) {
                    continue;
                }
                $operationTarget = $module . '/Page/' . $entityName;
                $filenames = implode(", ", $beforeEntity['filePaths']);

                // Validate page still exists
                if (!isset($afterEntities[$module][$entityName])) {
                    $operation = new PageRemoved($filenames, $operationTarget);
                    $this->getReport()->add(MftfReport::MFTF_REPORT_CONTEXT, $operation);
                    continue;
                }

                // Sort Elements
                $beforeSectionElements = [];
                $afterSectionElements = [];

                foreach ($beforeEntity['value'] ?? [] as $beforeChild) {
                    if ($beforeChild['name'] == self::MFTF_SECTION_ELEMENT) {
                        $beforeSectionElements[] = $beforeChild;
                    }
                }
                foreach ($afterEntities[$module][$entityName]['value'] ?? [] as $afterChild) {
                    if ($afterChild['name'] == self::MFTF_SECTION_ELEMENT) {
                        $afterSectionElements[] = $afterChild;
                    }
                }

                // Validate <section> elements
                foreach ($beforeSectionElements as $beforeField) {
                    $beforeFieldKey = $beforeField['attributes']['name'];
                    $matchingElement = $this->findMatchingElement(
                        $beforeField,
                        $afterSectionElements,
                        'name'
                    );
                    if ($matchingElement === null) {
                        $operation = new PageSectionRemoved($filenames, $operationTarget . '/' . $beforeFieldKey);
                        $this->getReport()->add(MftfReport::MFTF_REPORT_CONTEXT, $operation);
                    }
                }
                $this->findAddedElementsInArray(
                    $beforeSectionElements,
                    $afterSectionElements,
                    'name',
                    $this->getReport(),
                    $filenames,
                    PageSectionAdded::class,
                    $operationTarget
                );
            }
        }

        // check new modules
        $newModuleEntities = array_diff_key($afterEntities, $beforeEntities);
        foreach ($newModuleEntities as $module => $entities) {
            $this->findAddedEntitiesInModule(
                $beforeEntities[$module] ?? [],
                $entities ?? [],
                self::MFTF_DATA_TYPE,
                $this->getReport(),
                PageAdded::class,
                $module . '/Page'
            );
        }
        return $this->getReport();
    }
}
