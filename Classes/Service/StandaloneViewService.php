<?php
namespace Pixelant\PxaSurvey\Service;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Class StandaloneViewService
 * @package Pixelant\PxaSurvey\Service
 */
class StandaloneViewService
{
    /**
     * Get view by name.
     *
     * @param string $templateFile
     * @return StandaloneView
     */
    public function getStandaloneView(string $templateFile): StandaloneView
    {
        $om = GeneralUtility::makeInstance(ObjectManager::class);

        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);
        $extConfig = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT, 'pxasurvey', 'survey');

        /** @var StandaloneView $view */
        $view = $om->get(StandaloneView::class);

        $view->setPartialRootPaths($extConfig['plugin.']['tx_pxasurvey_survey.']['view.']['partialRootPaths.']);
        $view->setTemplateRootPaths($extConfig['plugin.']['tx_pxasurvey_survey.']['view.']['templateRootPaths.']);
        $view->setTemplate($templateFile);

        return $view;
    }
}