<?php

namespace Pixelant\PxaSurvey\Domain\Model;


/**
 * Doubleoptin
 */
class Doubleoptin extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{

    /**
     * email
     *
     * @var string
     */
    protected $email = '';

    /**
     * verificationCode
     *
     * @var string
     */
    protected $verificationCode = '';

    /**
     * verificationDate
     *
     * @var \DateTime
     */
    protected $verificationDate = null;

    /**
     * @var bool
     */
    protected $bestaetigt;

    /**
     * __construct
     */
    public function __construct()
    {
        $this->initVerificationCode();
    }

    /**
     * Returns the email
     *
     * @return string $email
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Sets the email
     *
     * @param int $email
     * @return void
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * Returns the verificationCode
     *
     * @return string $verificationCode
     */
    public function getVerificationCode()
    {
        return $this->verificationCode;
    }

    /**
     * Sets the verificationCode
     *
     * @param int $verificationCode
     * @return void
     */
    public function setVerificationCode($verificationCode)
    {
        $this->verificationCode = $verificationCode;
    }

    /**
     * Returns the verificationDate
     *
     * @return \DateTime $verificationDate
     */
    public function getVerificationDate()
    {
        return $this->verificationDate;
    }

    /**
     * Sets the verificationDate
     *
     * @param \DateTime $verificationDate
     * @return void
     */
    public function setVerificationDate(\DateTime $verificationDate)
    {
        $this->verificationDate = $verificationDate;
    }

    /**
     * Initializes the verificationCode
     *
     * @return string $randomString
     */
    private function initVerificationCode()
    {
        if (!$this->verificationCode) {
            $this->verificationCode = $this->getRandomString();
        }
    }


    /**
     * Returns random string
     *
     * @return string $randomString
     */
    private function getRandomString($length = 64)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    /**
     * @param bool $bestaetigt
     */
    public function setBestaetigt(bool $bestaetigt)
    {
        $this->bestaetigt = $bestaetigt;
    }

    /**
     * @return bool
     */
    public function isBestaetigt(): bool
    {
        return $this->bestaetigt;
    }

}
