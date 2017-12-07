<?php

/**
 * A very simple in-memory logger that allows querying the log for existence of messages
 */
class SAML2_SimpleTestLogger extends \Psr\Log\AbstractLogger
{
    /**
     * @var array
     */
    private $messages = array();

    public function log($level, $message, array $context = array())
    {
        $this->messages[] = array(
            'level'   => $level,
            'message' => $message,
            'context' => $context
        );
    }

    /**
     * Get all the messages logged at the specified level
     * @param $level
     *
     * @return array
     */
    public function getMessagesForLevel($level)
    {
        return array_filter($this->messages, function ($message) use ($level) {
            return $message['level'] === $level;
        });
    }

    /**
     * Check if the given message exists within the log
     * @param $messageToFind
     *
     * @return bool
     */
    public function hasMessage($messageToFind)
    {
        $count = array_filter($this->messages, function ($message) use ($messageToFind) {
            return $message['message'] === $messageToFind;
        });

        return !!count($count);
    }
}
