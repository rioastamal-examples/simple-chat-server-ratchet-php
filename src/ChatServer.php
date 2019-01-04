<?php
/**
 * Simple chat server which uses Ratchet library.
 *
 * @author Rio Astamal <rio@rioastamal.net>
 * @link https://github.com/rioastamal-examples/simple-chat-server-ratchet-php
 * @license MIT
 */
namespace MyApp;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class ChatServer implements MessageComponentInterface
{
    protected $clients = [];
    protected $prefix = 'SERVER:';
    protected $help = <<<HELP
List of commands:

/nick NICKNAME          Register a nickname
/msg MESSAGE            Send a message to all
/pm NICKNAME MESSAGE    Send private message
/users                  Get list of online users
/help                   Show this message
/quit                   Quit chat club
HELP;

    public function onOpen(ConnectionInterface $conn)
    {
        $data = [
            'id' => $conn->resourceId,
            'nickname' => 'user_' . $conn->resourceId,
            'logged_in' => false,
            'conn' => $conn
        ];
        $this->addClient($data);
        $this->debug('New connection -> ' . $conn->resourceId);

        $message = <<<EOF
**************************************
Welcome to the Club - Enjoy your chat!
**************************************
{$this->help}


EOF;
        $conn->send($message);
    }

    public function onMessage(ConnectionInterface $from, $message)
    {
        if (trim($message) === '') {
            return;
        }

        $trimmedMessage = preg_replace('/\r?\n/', '\n', $message);
        $this->debug('Client id -> ' . $from->resourceId . ' sent a message -> ' . $trimmedMessage);

        switch ($message) {
            case trim($message) === '/quit':
                $from->close();
                break;

            case trim($message) === '/help':
                $from->send("\n{$this->help}\n");
                break;

            case strpos($message, '/nick ') !== false:
                $this->handleNickname($from, $message);
                break;

            case strpos($message, '/pm ') !== false:
                $this->handlePrivateMessage($from, $message);
                break;

            case trim($message) === '/users':
                $this->handleOnlineUsers($from, $message);
                break;

            case strpos($message, '/msg ') !== false:
                $this->handleMessage($from, $message);
                break;

            default:
                $from->send("\nERROR: Unknown command.\n");
        }
    }

    public function onClose(ConnectionInterface $conn)
    {
        $nickname = $this->clients[$conn->resourceId]['nickname'];
        if ($this->clients[$conn->resourceId]['logged_in']) {
            $this->broadcastMessage("User `{$nickname}` has quit chatroom.", $conn);
        }

        $this->debug('Connection closed -> ' . $conn->resourceId);
        unset($this->clients[$conn->resourceId]);
    }

    public function onError(ConnectionInterface $conn, \Exception $e)
    {
    }

    protected function debug($message, $newline = "\n")
    {
        printf("%s %s%s", $this->prefix, $message, $newline);
    }

    protected function addClient($clientData)
    {
        if (array_key_exists($clientData['id'], $this->clients)) {
            return;
        }

        $this->clients[$clientData['id']] = $clientData;
    }

    protected function handleNickname(ConnectionInterface $from, $message)
    {
        if (! preg_match('#^/nick ([a-zA-z0-9_\-]+)#', $message, $matches)) {
            $from->send("\nERROR: Nickname can not contains space.\n");
            return;
        }

        $nickname = $matches[1];

        // Make sure their nickname is unique
        foreach ($this->clients as $id => $client) {
            if ($nickname === $client['nickname']) {
                $from->send("\nERROR: Nickname already taken, please use other nickname.\n");
                return;
            }
        }

        // Change his/her nickname
        foreach ($this->clients as $id => $client) {
            if ((string)$id === (string)$from->resourceId) {
                $from->send("\nSUCCESS: Your nickname has changed to {$nickname}.\n");
                break;
            }
        }

        $this->clients[$from->resourceId]['logged_in'] = true;
        $this->clients[$from->resourceId]['nickname'] = $nickname;
        $this->broadcastMessage("User `{$nickname}` has joined channel.", $from);
    }

    protected function handleMessage(ConnectionInterface $from, $message)
    {
        if (! $this->isLoggedIn($from)) {
            return;
        }

        if (! preg_match('#^/msg (.*)#', $message, $matches)) {
            $from->send("\nERROR: Please provide a message.\n");
            return;
        }

        $nickname = $this->clients[$from->resourceId]['nickname'];
        $this->broadcastMessage("{$nickname}: {$matches[1]}");
    }

    protected function handlePrivateMessage(ConnectionInterface $from, $message)
    {
        if (! $this->isLoggedIn($from)) {
            return;
        }

        if (! preg_match('#^/pm ([a-zA-z0-9_\-]+) (.*)#', $message, $matches)) {
            $from->send("\nERROR: Could not send your message.\n");
            return;
        }

        $nickname = $matches[1];
        $privateMessage = $matches[2];
        $senderNickname = $this->clients[$from->resourceId]['nickname'];

        if ($nickname === $senderNickname) {
            $from->send("\nERROR: You can not PM yourself.\n");
            return;
        }

        $sendToIndex = -1;
        foreach ($this->clients as $id => $client) {
            if ($client['nickname'] === $nickname) {
                $sendToIndex = $id;
                break;
            }
        }

        if ($sendToIndex === -1) {
            $from->send("\nERROR: Nickname `{$nickname}` does not exists.\n");
            return;
        }

        $this->clients[$sendToIndex]['conn']->send("\n>> PM from `{$senderNickname}` -> {$privateMessage}\n");
    }

    protected function handleOnlineUsers(ConnectionInterface $from, $message)
    {
        $totalUsers = count($this->clients);
        $numberOfAnonymous = 0;
        $numberOfLoggedIn = 0;
        $loggedInUsers = [];

        foreach ($this->clients as $client) {
            if ($client['logged_in']) {
                $loggedInUsers[] = $client['nickname'];
            }
        }
        $numberOfLoggedIn = count($loggedInUsers);
        $numberOfAnonymous = $totalUsers - $numberOfLoggedIn;

        $message = "Currently we have {$numberOfLoggedIn} users online and {$numberOfAnonymous} anonymous.\n";
        $message .= str_repeat('-', strlen(trim($message))) . "\n";

        if ($numberOfLoggedIn > 0) {
            $message .= "\n";
            foreach ($loggedInUsers as $user) {
                $message .= "- $user\n";
            }
        }

        $from->send("\n{$message}\n");
    }

    protected function broadcastMessage($message, ConnectionInterface $from = null)
    {
        if (is_null($from)) {
            foreach ($this->clients as $id => $client) {
                $client['conn']->send("\n>> {$message}\n");
            }

            return;
        }

        foreach ($this->clients as $id => $client) {
            if ((string)$id !== (string)$from->resourceId) {
                $client['conn']->send("\n>> {$message}\n");
            }
        }
    }

    protected function isLoggedIn(ConnectionInterface $conn)
    {
        if (! $this->clients[$conn->resourceId]['logged_in']) {
            $conn->send("\nERROR: You need to set your nickname first.\n");
            return false;
        }

        return true;
    }
}