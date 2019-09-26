<?php

namespace Pixelant\PxaSurvey\Controller;

/***
 *
 * This file is part of the "Simple Survey" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2017 Andriy Oprysko
 *
 ***/

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Pixelant\PxaSurvey\Domain\Model\Survey;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Class SurveyAnalysisController
 * @package Pixelant\PxaSurvey\Controller
 */
class SurveyAnalysisController extends AbstractController
{
    /**
     * BackendTemplateContainer
     *
     * @var BackendTemplateView
     */
    protected $view;

    /**
     * Backend Template Container
     *
     * @var BackendTemplateView
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

    /**
     * Current page
     *
     * @var int
     */
    protected $pid = 0;


    /**
     * @var array
     */
    protected $forbiddenCharacters = ["\\", "/", "?", "*", "[", "]", "~", "!", "@", "#", "$", "%", "^", "&", "(", ")", "-", "_", "=", "+", "{", "}", "|", ";", ",", "<", ">"];

    /**
     * Initialize
     */
    public function initializeAction()
    {
        $this->pid = (int)GeneralUtility::_GET('id');
        ini_set('max_execution_time', 960); // 960s = 16mins
    }

    /**
     * Main action
     */
    public function mainAction()
    {
        if ($this->pid) {
            $surveys = $this->surveyRepository->findByPid($this->pid);
        }

        $this->view->assign('surveys', $surveys ?? []);
    }

    /**
     * Display analysis for survey
     *
     * @param Survey $survey
     */
    public function seeAnalysisAction(Survey $survey)
    {
        $data = $this->generateAnalysisData($survey, true);

        $this->view->assign('dataJson', json_encode($data));
        $this->view->assign('data', $data);
    }

    /**
     * Export data as csv file
     *
     * @param Survey $survey
     */
    public function exportCsvAction(Survey $survey)
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);
        $filename = $survey->getName();

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="'. $filename .'.xlsx"');
        header('Cache-Control: max-age=0');

        $data = $this->generateAnalysisData($survey);

        foreach ($data as $sheetIndex => $questionData) {
            // Needs to clean the label of a worksheet
            $label = str_replace($this->forbiddenCharacters, "", $questionData['label']);

            $myWorkSheet = new Worksheet($spreadsheet, $label);

            $myWorkSheet->setCellValue('A1', $this->translate('module.answers'));
            $myWorkSheet->setCellValue('B1', $this->translate('module.percentages'));
            $myWorkSheet->setCellValue('C1', $this->translate('module.count'));

            $counter = 2;
            foreach ($questionData['questionData'] as $questionAnswerData) {
                $counter++;
                $myWorkSheet->setCellValue('A'.$counter, $questionAnswerData['label']);
                $myWorkSheet->setCellValue('B'.$counter, $questionAnswerData['percents'] . ' %');
                $myWorkSheet->setCellValue('C'.$counter, $questionAnswerData['count']);
            }
            $myWorkSheet->setCellValue('C'.($counter+1), $this->translate('module.total_answers', [$questionData['allAnswersCount']]));

            $spreadsheet->addSheet($myWorkSheet, $sheetIndex);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output'); // download file
        /*
         * Needs an extra exit() to prevent corrupt xlsx file
         * https://github.com/PHPOffice/PhpSpreadsheet/issues/217
         */
        exit();

    }

    /**
     * Set up view
     *
     * @param ViewInterface $view
     */
    protected function initializeView(ViewInterface $view)
    {
        /** @var BackendTemplateView $view */
        parent::initializeView($view);
        if ($this->view->getModuleTemplate() !== null) {
            $this->createButtons();
        }
    }

    /**
     * Add menu buttons
     *
     * @return void
     */
    protected function createButtons()
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);
        $buttonBar = $this->view->getModuleTemplate()->getDocHeaderComponent()->getButtonBar();

        $button = $buttonBar->makeLinkButton()
            ->setHref($this->buildNewSurveyUrl())
            ->setTitle($this->translate('module.new_survey'))
            ->setIcon($iconFactory->getIcon('actions-document-new', Icon::SIZE_SMALL));

        $buttonBar->addButton($button, ButtonBar::BUTTON_POSITION_LEFT);
    }

    /**
     * Generate url to create new survey
     *
     * @return string
     */
    protected function buildNewSurveyUrl(): string
    {
        $urlParameters = [
            'edit[tx_pxasurvey_domain_model_survey][' . $this->pid . ']' => 'new',
            'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI')
        ];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);

        return (string)$uriBuilder->buildUriFromRoute('record_edit', $urlParameters);
    }

    /**
     * Translate label
     *
     * @param string $key
     * @param array|null $arguments
     * @return string
     */
    protected function translate(string $key, array $arguments = null): string
    {
        return LocalizationUtility::translate($key, 'PxaSurvey', $arguments) ?? '';
    }
}
