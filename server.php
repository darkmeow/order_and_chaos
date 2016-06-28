<?php
use Workerman\Worker;
use Workerman\WebServer;
use Workerman\Autoloader;
use PHPSocketIO\SocketIO;

// composer autoload
include __DIR__ . '/vendor/autoload.php';

// globales para mantener el estado del juego
global $board, $usernames, $numUsers, $order, $chaos, $status;

// status indica el estado del juego en 4 posibles valores
// 1. wait: esperando jugadores
// 2. setup: eligiendo lado (order o chaos)
// 3. order: turno de order
// 4. chaos: turno de chaos
// 5. win_order: gana order
// 6. win_chaos: gana chaos
$status = 'wait';

// inicializar tablero 6x6
$board = array_fill(0, 6, array_fill(0, 6, 0));

$io = new SocketIO(2020); // inicia el socket en puerto 2020

$io->on('connection', function ($socket) {
    $socket->addedUser = false;
    // 'new message' envía un mensaje al chat
    $socket->on('new message', function ($data) use ($socket) {
        // enviar broadcast a todos los clientes con el nuevo mensaje
        $socket->broadcast->emit('new message', array(
            'username' => $socket->username,
            'message' => $data
        ));
    });

    // 'add user' agrega usuario a la lista de jugadores
    $socket->on('add user', function ($username) use ($socket) {
        global $status, $usernames, $numUsers;
        if ($status == 'wait') {
            // se guarda el username en la sesión de socket del cliente
            $socket->username = $username;
            // agregamos el username a la lista global de jugadores
            $usernames[$username] = $username;
            ++$numUsers;
            $socket->addedUser = true;
            $socket->emit('login', array(
                'numUsers' => $numUsers
            ));
            // enviar broadcast a todos los usuarios indicando que un nuevo usuario ha llegado
            $socket->broadcast->emit('user joined', array(
                'username' => $socket->username,
                'numUsers' => $numUsers
            ));
        }

        // si ya tenemos 2 usuarios, cambiar status a setup
        if ($numUsers == 2) {
            $status = 'setup';
        }

        // broadcat del status a los clientes conectados
        $socket->broadcast->emit('update status', array(
            'status' => $status
        ));
        $socket->emit('update status', array(
            'status' => $status
        ));
    });

    $socket->on('player type', function ($type) use ($socket) {
        global $status;
        if ($status == 'setup') {
            // se guarda el tipo de jugador en la sesión de socket del cliente (order o chaos)

            if ($type == 'order') { // si jugador elige order
                if (empty($order)) { // si order no ha sido asignado
                    $order = $socket->username; // asignar jugador a order
                    $socket->type = 'order';
                    $status = 'chaos'; // chaos inicia la partida
                } else {
                    $chaos = $socket->username; // de lo contrario, asignar jugador a chaos
                    $socket->type = 'chaos';
                    $status = 'order'; // order inicia la partida
                }
            } else { // si jugador elige chaos
                if (empty($chaos)) { // si chaos no ha sido asignado
                    $chaos = $socket->username; // asignar jugador a chaos
                    $socket->type = 'chaos';
                    $status = 'order'; // order inicia la partida
                } else {
                    $order = $socket->username; // de lo contrario, asignar jugador a order
                    $socket->type = 'order';
                    $status = 'chaos'; // chaos inicia la partida
                }
            }

            $socket->emit('set type', array(
                'type' => $socket->type
            ));
            $socket->broadcast->emit('set type', array(
                'type' => $status
            ));
            $socket->emit('update status', array(
                'status' => $status
            ));
            $socket->broadcast->emit('update status', array(
                'status' => $status
            ));
        }
    });

    $socket->on('execute turn', function ($data) use ($socket) {
        global $status, $board;
        $socket->broadcast->emit('execute turn', array(
            'target' => $data['target'],
            'chip' => $data['chip']
        ));
        $pos = substr($data['target'], 1);
        if($pos % 6 == 0) {
            $row = (int)floor($pos / 6) - 1;
            $col = 5;
        } else {
            $row = (int)floor($pos / 6);
            $col = $pos - ($row * 6) - 1;
        }
        $chip = $data['chip'] == 'white' ? 1 : 2;
        $board[$row][$col] = $chip;
        showBoard($board);
        $check = checkWin($board);
        if($check == 0) {
            // si nadie ha ganado alternar el turno de juego
            if ($status == 'order') {
                $status = 'chaos';
            } else {
                $status = 'order';
            }
        } elseif($check == 1) {
            // gana order
            $status = "win_order";
        } elseif($check == 2) {
            // gana chaos
            $status = "win_chaos";
        }
        $socket->emit('update status', array(
            'status' => $status
        ));
        $socket->broadcast->emit('update status', array(
            'status' => $status
        ));
    });

    // enviar broadcast para indicar que un usuario está escribiendo un mensaje
    $socket->on('typing', function () use ($socket) {
        $socket->broadcast->emit('typing', array(
            'username' => $socket->username
        ));
    });

    // evento broadcast para indicar que un usuario dejó de escribir un mensaje
    $socket->on('stop typing', function () use ($socket) {
        $socket->broadcast->emit('stop typing', array(
            'username' => $socket->username
        ));
    });

    // desconectar usuario
    $socket->on('disconnect', function () use ($socket) {
        global $usernames, $numUsers;
        // eliminar usuario de la lista de jugadores
        if ($socket->addedUser) {
            unset($usernames[$socket->username]);
            --$numUsers;
            // enviar broadcast indicando que un usuario se desconectó
            $socket->broadcast->emit('user left', array(
                'username' => $socket->username,
                'numUsers' => $numUsers
            ));
        }
    });

});

function checkWin($board) {
    // pasar los valores a cadenas de string dentro de un arreglo
    $values = array();
    // horizontal
    for($r = 0; $r < 6; $r++) {
        $values[] = '';
        $i = count($values) - 1;
        for($c = 0; $c < 6; $c++) {
           $values[$i] .= $board[$r][$c];
        }
    }
    // vertical
    for($c = 0; $c < 6; $c++) {
        $values[] = '';
        $i = count($values) - 1;
        for($r = 0; $r < 6; $r++) {
            $values[$i] .= $board[$r][$c];
        }
    }

    // sub diagonal 1 right
    $values[] = '';
    $i = count($values) - 1;
    $c = 0;
    for($r = 4; $r >= 0; $r--) {
        $values[$i] .= $board[$r][$c];
        $c++;
    }
    
    // full diagonal right
    $values[] = '';
    $i = count($values) - 1;
    $c = 0;
    for($r = 5; $r >= 0; $r--) {
        $values[$i] .= $board[$r][$c];
        $c++;
    }

    // sub diagonal 2 right
    $values[] = '';
    $i = count($values) - 1;
    $c = 1;
    for($r = 4; $r >= 0; $r--) {
        $values[$i] .= $board[$r][$c];
        $c++;
    }

    // sub diagonal 1 left
    $values[] = '';
    $i = count($values) - 1;
    $c = 0;
    for($r = 1; $r < 6; $r++) {
        $values[$i] .= $board[$r][$c];
        $c++;
    }

    // full diagonal left
    $values[] = '';
    $i = count($values) - 1;
    $c = 0;
    for($r = 0; $r < 6; $r++) {
        $values[$i] .= $board[$r][$c];
        $c++;
    }

    // sub diagonal 2 left
    $values[] = '';
    $i = count($values) - 1;
    $c = 1;
    for($r = 0; $r < 5; $r++) {
        $values[$i] .= $board[$r][$c];
        $c++;
    }
    print_r($values);

    $winChaos = false;
    $winOrder = false;
    $boardFull = true;
    foreach($values as $v) {
        if(strpos($v, '11111') !== false) {
            $winOrder = true;
        }
        if(strpos($v, '22222') !== false) {
            $winOrder = true;
        }
        if(strpos($v, '0') !== false) {
            $boardFull = false;
        }
    }
    if($winOrder == false && $boardFull == true) {
        $winChaos = true;
    }

    if($winChaos == false && $winOrder == false && $boardFull == false)
        return 0; // nadie ha ganado aun
    if($winOrder)
        return 1; // gana order
    if($winChaos)
        return 2; // gana chaos
}

function showBoard($board) {
    for($r=0; $r < 6; $r++) {
        for($c=0; $c < 6; $c++) {
            echo $board[$r][$c];
        }
        echo "\n";
    }
}

// iniciar webserver interno
$web = new WebServer('http://0.0.0.0:7000');
$web->addRoot('localhost', __DIR__ . '/public');

// ejecutar todos los workers (socket y webserver)
Worker::runAll();
