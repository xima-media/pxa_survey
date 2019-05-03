<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function () {
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'Pixelant.PxaSurvey',
            'Survey',
            [
                'Survey' => 'show, showResults, answer, finish, confirm, confirmationSuccess, confirmationFailure'
            ],
            // non-cacheable actions
            [
                'Survey' => 'show, showResults, answer, finish, confirm, confirmationSuccess, confirmationFailure'
            ]
        );

        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
            'Pixelant.PxaSurvey',
            'Counter',
            [
                'Survey' => 'showCounter'
            ],
            // non-cacheable actions
            [
                'Survey' => 'showCounter'
            ]
        );

        // wizards
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig(
            '<INCLUDE_TYPOSCRIPT: source="FILE:EXT:pxa_survey/Configuration/TypoScript/PageTS/wizards.ts">'
        );
    }
);
