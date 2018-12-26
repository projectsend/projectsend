<?php
/**
 * Store messages accross the app and show them before the main content
 */

namespace ProjectSend\Classes;

class Messages extends Base
{
    // Stores the array of messages to be rendered later
    private $message_number;
    private $messages;
    private $special_messages;

    public $dbh;
    public $container;

    function __construct($container)
    {
        parent::__construct($container);
        $this->message_number = 0;
        $this->messages = [];
        $this->special_messages = [];
    }

    /**
     * Add a message to the array
     *
     * @param string $type
     * @param string $message
     * @param array $arguments
     * @return void
     */
    public function add($type, $message, $arguments = array())
    {
        $this->messages[$this->message_number] = [
            'type'          => $type,
            'message'       => $message,
            'add_notice'    => ( isset( $arguments['add_notice'] ) && $arguments['add_notice'] === true ) ? true : false,
        ];

        $this->message_number++;
    }

    /**
     * Display all stored messages
     *
     * @return array
     */
    public function get($id = '')
    {
        if ( empty( $id ) ) {
            $this->ret = $this->messages;
        }
        else {
            $this->find_message = array_search( $id, array_column( $this->messages, $id ) );
            if ( $this->find_message !== false ) {
                $this->ret = $this->messages[$this->find_message];
            }
            else {
                $this->ret = false;
            }
        }

        return $this->ret;
    }

    /**
     * Add the special donations message
     */
    function add_special($type)
    {
        $this->special_messages[] = $type;
    }

    /**
     * Retrieve added special messages
     */
    function get_specials()
    {
        if ( !empty( $this->special_messages ) )
        {
            return $this->special_messages;
        }
        else {
            return false;
        }
    }
}
