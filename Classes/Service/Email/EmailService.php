<?php
namespace Pixelant\PxaSurvey\Service\Email;

use Pixelant\PxaSurvey\Service\StandaloneViewService;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Class EmailService
 * @package Pixelant\PxaSurvey\Service\Email
 */
class EmailService
{

    /**
     * @param array $recipient
     * @param array $sender
     * @param $subject
     * @param $templateName
     * @param $entity
     * @return mixed
     */
    public function sendTemplateEmail(array $recipient, array $sender, $subject, $templateName, $entity)
    {
        $om = GeneralUtility::makeInstance(ObjectManager::class);

        $standaloneViewService = $om->get(StandaloneViewService::class);
        $standaloneView = $standaloneViewService->getStandaloneView($templateName);

        $standaloneView->assign('entity', $entity);

        $message = $standaloneView->render();

        $mail = $om->get(MailMessage::class);

        $mail
            ->setFrom($sender)
            ->setTo($recipient)
            ->setSubject($subject)
            ->setBody($message, 'text/html')
        ;

        $mail->send();
        return $mail->isSent();
    }

}