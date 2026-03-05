<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\Server\IoServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;

/**
 * Run WS server:
 *   php ws-server.php
 *
 * TODO (later):
 * - TLS (wss) behind reverse proxy
 * - auth via cookie/JWT
 */
$container = require __DIR__ . '/src/bootstrap.php';
$ws = $container['ws'];

$loop = Loop::get();
$socket = new SocketServer('0.0.0.0:8081', [], $loop);

$server = new IoServer(
  new HttpServer(new WsServer($ws)),
  $socket,
  $loop
);

echo "WS listening on ws://127.0.0.1:8081\n";
$server->run();
