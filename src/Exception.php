<?php

namespace SpojeNet\Realpad;

/**
 * Description of Exception
 *
 * @author vitex
 */
class Exception extends \Ease\Exception
{
    /**
     * Original server response
     * @var string
     */
    private $serverResponse = '';

    /**
     * Error messages sit here
     * @var array
     */
    private $errorMessages = [];

    /**
     * RealPad API response as Exception
     *
     * @param string $message good to know
     *
     * @param ApiClient $caller Client Object
     *
     * @param \Ease\Exception $previous
     */
    public function __construct($message, ApiClient $caller, \Ease\Exception $previous = null)
    {
        $this->errorMessages = $caller->getErrors();
        parent::__construct(get_class($caller) . ': ' . $message, $caller->getLastResponseCode(), $previous);
    }

    /**
     * Get (first) error message
     *
     * @param int $index which message
     *
     * @return string
     */
    public function getErrorMessage($index = 0)
    {
        return $this->errorMessages[$index];
    }

    /**
     * All stored Error messages
     *
     * @return array
     */
    public function getErrorMessages()
    {
        return $this->errorMessages;
    }
}
