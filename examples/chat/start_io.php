<?php

use PHPSocketIO\SocketIO;
use Workerman\Worker;

// composer autoload
require_once join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'vendor', 'autoload.php']);

$io = new SocketIO(2020);
$io->on('connection', function ($socket) {
    $socket->addedUser = false;
    // when the client emits 'new message', this listens and executes
    $socket->on('new message', function ($data) use ($socket) {
        writeMsg('new message ('.$socket->username.'): '.$data);
        // we tell the client to execute 'new message'
        $socket->broadcast->emit('new message', [
            'username' => $socket->username,
            'message' => $data,
        ]);
    });

    // when the client emits 'add user', this listens and executes
    $socket->on('add user', function ($username) use ($socket) {
        if ($socket->addedUser) {
            return;
        }
        writeMsg('add user: '.$username);
        global $usernames, $numUsers;
        // we store the username in the socket session for this client
        $socket->username = $username;
        ++$numUsers;
        $socket->addedUser = true;
        $socket->emit('login', [
            'numUsers' => $numUsers,
        ]);
        // echo globally (all clients) that a person has connected
        $socket->broadcast->emit('user joined', [
            'username' => $socket->username,
            'numUsers' => $numUsers,
        ]);
    });

    // when the client emits 'typing', we broadcast it to others
    $socket->on('typing', function () use ($socket) {
        $socket->broadcast->emit('typing', [
            'username' => $socket->username,
        ]);
    });

    // when the client emits 'stop typing', we broadcast it to others
    $socket->on('stop typing', function () use ($socket) {
        $socket->broadcast->emit('stop typing', [
            'username' => $socket->username,
        ]);
    });

    // when the user disconnects.. perform this
    $socket->on('disconnect', function () use ($socket) {
        global $usernames, $numUsers;
        if ($socket->addedUser) {
            writeMsg('disconnect: '.($socket->username ?? $usernames ?? ''));
            --$numUsers;

            // echo globally that this client has left
            $socket->broadcast->emit('user left', [
                'username' => $socket->username,
                'numUsers' => $numUsers,
             ]);
        }
    });
});

if (!defined('GLOBAL_START')) {
    Worker::runAll();
}

function writeMsg(string $msg)
{
}
