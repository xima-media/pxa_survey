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

use Pixelant\PxaSurvey\Domain\Model\Answer;
use Pixelant\PxaSurvey\Domain\Model\Doubleoptin;
use Pixelant\PxaSurvey\Domain\Model\Question;
use Pixelant\PxaSurvey\Domain\Model\Survey;
use Pixelant\PxaSurvey\Domain\Model\UserAnswer;
use Pixelant\PxaSurvey\Domain\Repository\DoubleoptinRepository;
use Pixelant\PxaSurvey\Service\Email\EmailService;
use Pixelant\PxaSurvey\Utility\SurveyMainUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Extbase\Domain\Model\FrontendUser;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * SurveyController
 */
class SurveyController extends AbstractController
{
    /**
     * Include reCAPTCHA api js
     */
    public function initializeShowAction()
    {
        if ((int)$this->settings['protectWithReCaptcha'] === 1
            && (int)$this->settings['recaptcha']['donNotIncludeJsApi'] === 0
            && !empty($this->settings['recaptcha']['siteKey'])
            && !empty($this->settings['recaptcha']['siteSecret'])
        ) {
            $pageRenderer = $this->getPageRenderer();
            $pageRenderer->addJsFile(
                'https://www.google.com/recaptcha/api.js',
                'text/javascript',
                false,
                false,
                '',
                true,
                '|',
                true
            );
        }
    }

    /**
     * action show
     *
     * @return void
     */
    public function showAction()
    {
        /** @var Survey $survey */
        $survey = $this->surveyRepository->findByUid((int)$this->settings['survey']);

        if ($survey !== null && !$this->isSurveyAllowed($survey)) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $this->forward('finish', null, null, ['survey' => $survey, 'alreadyFinished' => true, 'gewinnspielTeilnahme' => false, 'email' => null]);
        }

        if ($survey !== null && (int)$this->settings['showAllQuestions'] === 0) {
            $currentQuestion = $this->getNextQuestion($survey);
            $currentPosition = $survey->getQuestions()->getPosition($currentQuestion);
            $countAllQuestions = $survey->getQuestions()->count();

            $this->view->assignMultiple([
                'currentQuestion' => $currentQuestion,
                'currentPosition' => $currentPosition,
                'countAllQuestions' => $countAllQuestions,
                'progress' => round(($currentPosition - 1) / $countAllQuestions, 2) * 100
            ]);
        }

        $this->view->assign('survey', $survey);
    }

    /**
     * answer from user survey
     *
     * @param Survey $survey
     * @param Question $currentQuestion
     * @validate $survey \Pixelant\PxaSurvey\Domain\Validation\Validator\SurveyAnswerValidator
     * @validate $survey \Pixelant\PxaSurvey\Domain\Validation\Validator\ReCaptchaValidator
     */
    public function answerAction(Survey $survey, Question $currentQuestion = null)
    {
        $answers = $this->convertRequestToUserAnswersArray();

        if ((int)$this->settings['showAllQuestions']) {
            $this->saveResultAndFinish($survey, $answers);
        } else {
            // No answer given and question is not required
            if (empty($answers) && $currentQuestion !== null) {
                $answers = [$currentQuestion->getUid() => ''];
            }

            SurveyMainUtility::addAnswerToSessionData($survey->getUid(), $answers);

            // Show next question
            $this->forward('show');
        }
    }

    /**
     * After survey was finished
     *
     * @param Survey $survey
     * @param bool $alreadyFinished User already finished this survey and is not allowed take it again
     * @param bool $gewinnspielTeilnahme
     * @param string $email
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     */
    public function finishAction(Survey $survey, bool $alreadyFinished = false, bool $gewinnspielTeilnahme = false, string $email = '' )
    {
        //wenn am Gewinnspiel teilnehmen gewünscht ist:
        if($gewinnspielTeilnahme && $email)
        {
            //dann rufe die Funktion zur Registrierung der double opt-in Funktion auf
            $new = new Doubleoptin();
            $new->setEmail($email);

            //erstmal verstecken:
            $new->setBestaetigt(0);

            // persist newDoubleoptin
            // wenn bereits verifiziert, dann tue nichts
            if(!$this->doubleoptinRepository->isVerificated($new))
            {
                $this->doubleoptinRepository->add($new);

                //Email schicken:
                $emailService = $this->objectManager->get(EmailService::class);

                $emailService->sendTemplateEmail(
                    [$new->getEmail() => $new->getEmail()],
                    ['confirmation@dwi.de' => 'confirmation@dwi.de'],
                    'Confirmation Mail',
                    'Survey/Email/ConfirmationMail.html',
                    $new
                );
            }
        }

        // If there are more than one survey on page and one of them redirect to finish
        // only real one that was finished should show message
        if ((int)$this->settings['survey'] !== $survey->getUid()) {
            $this->forward('show');
        }

        $this->view
            ->assign('survey', $survey)
            ->assign('alreadyFinished', $alreadyFinished);
    }

    /**
     * action confirm
     *
     * @return void
     */
    public function confirmAction()
    {
        //rufe das Objekt auf, welches den Code hat und wo noch KEIN Datum gesetzt wurde
        if($this->request->hasArgument('code')) {
            /** @var Doubleoptin $doubleoptin */
            $doubleoptin = $this->doubleoptinRepository->findByVerificationCode($this->request->getArgument('code'));
        }

        //wenn das Objekt existiert und noch NICHT bestätigt wurde
        if($doubleoptin && !$this->doubleoptinRepository->isVerificated($doubleoptin)) {
            // it's a valid verificationCode
            $doubleoptin->setVerificationDate(new \DateTime);

            //verstecken aufheben:
            $doubleoptin->setBestaetigt(1);
            $this->doubleoptinRepository->update($doubleoptin);

            // user is now validated

            //Änderungen persistieren:
            $this->doubleoptinRepository->persistAll();

            //Dupletten löschen, wenn vorhanden:
            $this->doubleoptinRepository->deleteUnconfirmedWithSameEmail($doubleoptin);

            $this->forward('confirmationSuccess');

        } else {
            $this->forward('confirmationFailure');
        }
    }

    /**
     * action confirmationSuccess
     *
     * @return void
     */
    public function confirmationSuccessAction() {}

    /**
     * action confirmationFailure
     *
     * @return void
     */
    public function confirmationFailureAction() {}


    /**
     * Show counter action
     */
    public function showCounterAction()
    {
        $uid = (int) $this->settings['question'];

        $count = $this->userAnswerRepository->findUserAnswersByQuestionUid($uid)->count();

        $this->view->assign('count', $count);
    }

    /**
     * Show survey results
     */
    public function showResultsAction()
    {
        /** @var Survey $survey */
        $survey = $this->surveyRepository->findByUid((int)$this->settings['survey']);

        $data = $survey !== null
            ? $this->generateAnalysisData($survey)
            : [];

        $this->view
            ->assign('survey', $survey)
            ->assign('data', $data);
    }

    /**
     * Get answers from request
     *
     * @return array
     */
    protected function convertRequestToUserAnswersArray()
    {
        $answers = [];

        if ($this->request->hasArgument('answers')) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $requestAnswers = $this->request->getArgument('answers');

            /** @noinspection PhpWrongForeachArgumentTypeInspection */
            foreach ($requestAnswers as $questionUid => $requestAnswer) {
                $answers[$questionUid] = $requestAnswer['answer'] ?: $requestAnswer['otherAnswer'];
            }
        }

        return $answers;
    }

    /**
     * @param Survey $survey
     * @return Question|object
     */
    protected function getNextQuestion(Survey $survey)
    {
        $answers = SurveyMainUtility::getAnswerSessionData($survey->getUid());

        if (empty($answers)) {
            // Very first question
            $survey->getQuestions()->rewind();
            return $survey->getQuestions()->current();
        } else {
            // Last answered question uid
            $lastQuestionUid = (int)array_keys(array_reverse($answers, true))[0];

            /** @var Question $question */
            foreach ($survey->getQuestions() as $question) {
                if ($question->getUid() === $lastQuestionUid) {
                    $survey->getQuestions()->next();
                    $nextQuestion = $survey->getQuestions()->current();

                    if ($nextQuestion !== null) {
                        return $nextQuestion;
                    } else {
                        // Reached last question
                        $this->saveResultAndFinish($survey, $answers);
                    }
                }
            }
        }

        // Nothing was found
        // assume we need to start over again
        SurveyMainUtility::clearAnswersSessionData($survey->getUid());
        $survey->getQuestions()->rewind();

        return $survey->getQuestions()->current();
    }

    /**
     * Save user answers and finish
     *
     * @param Survey $survey
     * @param array $data
     */
    protected function saveResultAndFinish(Survey $survey, array $data)
    {
        $userAnswers = [];
        foreach ($data as $questionUid => $answerData) {
            if (empty($answerData)) {
                continue;
            }

            /** @var UserAnswer $userAnswer */
            $userAnswer = $this->objectManager->get(UserAnswer::class);
            $question = $this->getQuestionFromSurveyByUid($survey, (int)$questionUid);
            if ($question !== null) {
                $userAnswer->setQuestion($question);
                $userAnswer->setPid($question->getPid());

                // Save for later fix in TYPO3 version 9.0-9.4
                $userAnswers[] = $userAnswer;
            }

            if (is_string($answerData)) {
                $this->setUserAnswerFromRequestData($userAnswer, $answerData);
            } elseif (is_array($answerData)) {
                foreach ($answerData as $answerSingleFromMultiple) {
                    $this->setUserAnswerFromRequestData($userAnswer, $answerSingleFromMultiple);
                }
            }

            if ($this->isUserLoggedIn()) {
                /** @var FrontendUser $frontendUser */
                $frontendUser = $this->frontendUserRepository->findByUid(
                    SurveyMainUtility::getTSFE()->fe_user->user['uid']
                );
                if ($frontendUser !== null) {
                    $userAnswer->setFrontendUser($frontendUser);
                }
            }

            /** @noinspection PhpUnhandledExceptionInspection */
            $this->userAnswerRepository->add($userAnswer);
        }

        if (version_compare(TYPO3_version, '9.0', '>=') && version_compare(TYPO3_version, '9.5', '<=')) {
            $this->fixQuestionRelationForUserAnswers($userAnswers);
        }

        SurveyMainUtility::clearAnswersSessionData($survey->getUid());
        $this->addSurveyToCookie($survey);

        $gewinnspielTeilnahme = false;
        $email = '';

        /** @var UserAnswer[] $userAnswers */
        for ($i = 0; $i < count($userAnswers); $i++)
        {
            /** @var UserAnswer $userAnswerNew */
            $userAnswerNew = $userAnswers[$i];

            if($userAnswerNew->getQuestion()->getUid() == 8) {
                $email = $userAnswerNew->getCustomValue();
            }

            if($userAnswerNew->getQuestion()->getUid() == 29) {
                $gewinnspielTeilnahme = true;
            }
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $this->redirect('finish', null, null, ['survey' => $survey, 'alreadyFinished' => false, 'gewinnspielTeilnahme' => $gewinnspielTeilnahme, 'email' => $email]);
    }

    /**
     * Wrapper function for testing
     *
     * @param Survey $survey
     */
    protected function addSurveyToCookie(Survey $survey)
    {
        SurveyMainUtility::addValueToListCookie(SurveyMainUtility::SURVEY_FINISHED_COOKIE_NAME, $survey->getUid());
    }

    /**
     * Set data from user answer
     *
     * @param UserAnswer $userAnswer
     * @param string $answerData
     */
    protected function setUserAnswerFromRequestData(UserAnswer $userAnswer, string $answerData)
    {
        // Check if answer is option object
        if (StringUtility::beginsWith($answerData, '__object--')) {
            $answerUid = (int)substr($answerData, 10);
            /** @var Answer $answer */
            $answer = $this->answerRepository->findByUid($answerUid);
            if ($answer !== null) {
                $userAnswer->addAnswer($answer);
            }
        } else {
            $userAnswer->setCustomValue($answerData);
        }
    }

    /**
     * Get question from survey by uid
     *
     * @param Survey $survey
     * @param int $questionUid
     * @return null|Question
     */
    protected function getQuestionFromSurveyByUid(Survey $survey, int $questionUid)
    {
        /** @var Question $question */
        foreach ($survey->getQuestions() as $question) {
            if ($question->getUid() === $questionUid) {
                return $question;
            }
        }

        return null;
    }

    /**
     * Check if user can take survey
     *
     * @param Survey $survey
     * @return bool
     */
    protected function isSurveyAllowed(Survey $survey): bool
    {
        if ((int)$this->settings['allowMultipleAnswerOnSurvey'] === 1) {
            return true;
        }

        // Check by fe user
        if ($this->isUserLoggedIn() && GeneralUtility::_GP('ADMCMD_simUser') === null) {
            /** @var FrontendUser $frontendUser */
            $frontendUser = $this->frontendUserRepository->findByUid(
                SurveyMainUtility::getTSFE()->fe_user->user['uid']
            );
            $frontendUserAnswers = $this->userAnswerRepository->countGivenUserAnswer($survey, $frontendUser);
            $countQuestions = $survey->getQuestions()->count();

            if ($countQuestions > 0 && $frontendUserAnswers >= $countQuestions) {
                return false;
            }
        }

        // check by cookie
        $surveysFinished = $_COOKIE[SurveyMainUtility::SURVEY_FINISHED_COOKIE_NAME] ?? '';
        return !GeneralUtility::inList($surveysFinished, $survey->getUid());
    }

    /**
     * Wrapper for testing
     *
     * @return PageRenderer
     */
    protected function getPageRenderer(): PageRenderer
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return GeneralUtility::makeInstance(PageRenderer::class);
    }

    /**
     * Wrapper
     *
     * @return bool
     */
    protected function isUserLoggedIn(): bool
    {
        return SurveyMainUtility::isFrontendLogin();
    }

    /**
     * Fix relation between user answer and question
     * @TODO remove after support of TYPO3 9.0-9.4 dropped
     *
     * @param UserAnswer[] $userAnswers
     */
    protected function fixQuestionRelationForUserAnswers(array $userAnswers)
    {
        $this->objectManager->get(PersistenceManager::class)->persistAll();

        foreach ($userAnswers as $userAnswer) {
            GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('tx_pxasurvey_domain_model_useranswer')
                ->update(
                    'tx_pxasurvey_domain_model_useranswer',
                    ['question' => $userAnswer->getQuestion()->getUid()],
                    ['uid' => (int)$userAnswer->getUid()],
                    [\PDO::PARAM_INT]
                );
        }
    }
}
