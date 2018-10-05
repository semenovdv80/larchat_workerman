<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;

// Channel Server.
$channel_server = new Channel\Server('0.0.0.0', 2206);

// Websocket server
$worker = new Worker("websocket://0.0.0.0:8001");
$worker->uidConnections = [];

// 4 processes
$worker->count = 1;

$worker->onWorkerStart = function ($worker) {
    // Channel client - connect current worker to Channel Server.
    Channel\Client::connect('127.0.0.1', 2206);
    // Subscribe worker to broadcast event .
    Channel\Client::on('broadcast', function ($data) use ($worker) {

        $oData = json_decode($data);
        switch ($oData->type) {
            case 'usermsg':
                if (!empty($oData->addr)) {
                    if (isset($worker->uidConnections[$oData->addr])) {
                        $uidConnection = $worker->uidConnections[$oData->addr];
                        $uidConnection->send($data);
                    }
                    if (isset($worker->uidConnections[$oData->from])) {
                        $uidConnection = $worker->uidConnections[$oData->from];
                        $uidConnection->send($data);
                    }
                } else {
                    foreach ($worker->uidConnections as $uidConnection)
                    {
                        $uidConnection->send($data);
                    }
                }
            break;
        }
    });

    // you can subscribe any events you want.
    Channel\Client::on('userlst', function ($data) use ($worker) {
        $oData = json_decode($data);
        $oData->userIds = array_keys($worker->uidConnections);
        $data = json_encode($oData);
        switch ($oData->type) {
            case 'userin': case 'userout':
                foreach ($worker->uidConnections as $uidConnection)
                {
                    $uidConnection->send($data);
                }
            break;
        }
    });
};


// Emitted when new connection come, add uid to connection's params
$worker->onConnect = function ($connection) use ($worker) {
    $connection->onWebSocketConnect = function ($connection, $header) use ($worker) {
        $connection->uid = $_GET['uid'];
        $worker->uidConnections[$connection->uid] = $connection;
        $oData = new stdClass();
        $oData->type = 'userin';
        $oData->userId = $connection->uid;
        $data = json_encode($oData);
        Channel\Client::publish('userlst', $data);

        echo "New connection: user $connection->uid\n";
    };
};

// Emitted when data received, send to broadcast for all workers
$worker->onMessage = function ($connection, $data) use ($worker) {
    // Publish broadcast event to all worker processes.
    Channel\Client::publish('broadcast', $data);
};

// Emitted when connection closed
$worker->onClose = function ($connection) use ($worker) {
    if (isset($connection->uid)) {
        unset($worker->uidConnections[$connection->uid]);
        $oData = new stdClass();
        $oData->type = 'userout';
        $oData->userId = $connection->uid;
        $data = json_encode($oData);
        Channel\Client::publish('userlst', $data);
    }
    echo "Connection closed\n";
};

// Run worker
Worker::runAll();