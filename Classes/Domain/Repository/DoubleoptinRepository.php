<?php
namespace Pixelant\PxaSurvey\Domain\Repository;



use Pixelant\PxaSurvey\Domain\Model\Doubleoptin;

/**
 * The repository for Doubleoptins
 */
class DoubleoptinRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    // Ändern der QuerySettings im Repository eines Models
    public function initializeObject() {
        /** @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface $configurationManager */
        $configurationManager = $this->objectManager->get('TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface');

        $settings = $configurationManager->getConfiguration(
            \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT,
            'PxaSurvey',
            'Survey'
        );

        /** @var $querySettings \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings */
        $querySettings = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\Generic\\Typo3QuerySettings');

        // neue Storage IDs
        $querySettings->setStoragePageIds(array($settings['plugin.']['tx_pxasurvey_survey.']['settings.']['registrations.']['storagePid']));

        //auch versteckte Einträge finden:
        $querySettings->setIgnoreEnableFields(TRUE)->setIncludeDeleted(FALSE);

        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Datenbank updaten
     */
    public function persistAll(){
        $this->persistenceManager->persistAll();
    }

    /**
     * Gets the Doubleoptin by verificationCode
     *
     * @param string $verificationCode
     * @return \ExtDev\Doubleoptin\Domain\Model\Doubleoptin $doubleoptin
     */
    public function findByVerificationCode(String $verificationCode)
    {
        $query = $this->createQuery();

        $constraints = array(
            $query->equals('verification_code', $verificationCode),
            $query->equals('verification_date', 0)
        );

        $query->matching(
            $query->logicalAnd($constraints)
        );

        $result = $query->execute()->toArray();
        if(count($result) > 0) {
            return $result[0];
        } else {
            return NULL;
        }
    }

    /**
     * @param Doubleoptin $doubleoptin
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException
     */
    public function isVerificated(Doubleoptin $doubleoptin){
        $query = $this->createQuery();

        $constraints = array(
            $query->equals('email', $doubleoptin->getEmail()),
            $query->greaterThan('verification_date', 0)
        );

        $query->matching(
            $query->logicalAnd($constraints)
        );

        $result = $query->execute()->toArray();

        if(count($result) > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param Doubleoptin $doubleoptin
     * @return bool
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     */
    public function deleteUnconfirmedWithSameEmail(Doubleoptin $doubleoptin){
        $query = $this->createQuery();

        $constraints = array(
            $query->equals('email', $doubleoptin->getEmail()),
            $query->equals('verification_date', 0)
        );

        $query->matching(
            $query->logicalAnd($constraints)
        );

        $result = $query->execute()->toArray();

        foreach ($result as $doubleoptin)
            $this->remove($doubleoptin);

        $this->persistAll();

        if(count($result) > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return array|\TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException
     */
    public function findAllConfirmed(){
        $query = $this->createQuery();

        $constraints = array(
            $query->greaterThan('verification_date', 0)
        );

        $query->matching(
            $query->logicalAnd($constraints)
        );

        return $query->execute();
    }

}
