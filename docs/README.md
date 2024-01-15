# phpsocket.io manual

## Install
Please use composer to integrate phpsocket.io.

The script references autoload.php in the vendor to implement the loading of SocketIO related classes. For example
```php
require_once '/your vendor path/autoload.php';
```

## Server and client connection
**Create a SocketIO server**
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use PHPSocketIO\SocketIO;

//Create a socket.io server and listen on port 3120
$io = new SocketIO(3120);
//Print a line of text when a client connects
$io->on('connection', function($socket)use($io){
   echo "new connection coming\n";
});

Worker::runAll();
```
**Client**
```javascript
<script src='https://cdn.bootcss.com/socket.io/2.0.3/socket.io.js'></script>
<script>
// If the server is not on this machine, please change 127.0.0.1 to the server ip
var socket = io('http://127.0.0.1:3120');
//The connect default event is triggered when the connection to the server is successful.
socket.on('connect', function(){
     console.log('connect success');
});
</script>
```

## Custom events
Socket.io mainly communicates and interacts through events.

In addition to the three events of connect, message, and disconnect that come with socket connections, developers on the server and client can customize other events.

Both the server and the client trigger events on the other side through the emit method.

For example, the following code defines a ```chat message``` event on the server side, and the event parameter is ```$msg```.
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use PHPSocketIO\SocketIO;

$io = new SocketIO(3120);
//When a client connects
$io->on('connection', function($socket)use($io){
   //Define chat message event callback function
   $socket->on('chat message', function($msg)use($io){
     // Trigger all client-defined chat message from server events
     $io->emit('chat message from server', $msg);
   });
});
Worker::runAll();
```

The client triggers the chat message event on the server through the following method.
```javascript
<script src='//cdn.bootcss.com/socket.io/1.3.7/socket.io.js'></script>
<script>
// Connect to the server
var socket = io('http://127.0.0.1:3120');
// Trigger the chat message event on the server side
socket.emit('chat message', 'This is the content of the message...');
// The server triggers the client's chat message from server event through emit('chat message from server', $msg)
socket.on('chat message from server', function(msg){
     console.log('get message:' + msg + ' from server');
});
</script>
```

## workerStart event
phpsocket.io provides a workerStart event callback, which is a callback triggered when the process is ready to accept client connections.
A process life cycle will only be triggered once. You can set some global things here, such as opening a new Worker port, etc.
```php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use PHPSocketIO\SocketIO;

$io = new SocketIO(9120);

// Listen to an http port. Accessing this port through the http protocol can push data to all clients (url is similar to http://ip:9191?msg=xxxx)
$io->on('workerStart', function()use($io) {
     $inner_http_worker = new Worker('http://0.0.0.0:9191');
     $inner_http_worker->onMessage = function($http_connection, $data)use($io){
         if(!isset($_GET['msg'])) {
             return $http_connection->send('fail, $_GET["msg"] not found');
         }
         $io->emit('chat message', $_GET['msg']);
         $http_connection->send('ok');
     };
     $inner_http_worker->listen();
});

//When a client connects
$io->on('connection', function($socket)use($io){
   //Define chat message event callback function
   $socket->on('chat message', function($msg)use($io){
     // Trigger all client-defined chat message from server events
     $io->emit('chat message from server', $msg);
   });
});

Worker::runAll();
```
After phpsocket.io is started, open the internal http port and push data to the client through phpsocket.io. Reference [web-msg-sender](http://www.workerman.net/web-sender).

## Grouping
Socket.io provides grouping functionality, allowing events to be sent to a group, such as broadcasting data to a room.

1. Join a group (one connection can join multiple groups)
```php
$socket->join('group name');
```
2. Leave the group (will automatically leave the group when the connection is disconnected)
```php
$socket->leave('group name');
```

## Various methods of sending events to the client
$io is the SocketIO object. $socket is the client connection

$data can be numbers, strings, or arrays. When $data is an array, the client automatically converts it to a JavaScript object.

In the same way, if the client emits an event to the server and passes a JavaScript object, it will be automatically converted into a PHP array when received by the server.

1. Send an event to the current client
```php
$socket->emit('event name', $data);
```
2. Send events to all clients
```php
$io->emit('event name', $data);
```
3. Send events to all clients, but not the current connection.
```php
$socket->broadcast->emit('event name', $data);
```

4. Send events to all clients in a group
```php
$io->to('group name')->emit('event name', $data);
```

## Get client ip
```php
$io->on('connection', function($socket)use($io){
         var_dump($socket->conn->remoteAddress);
});
```

## Close link
```php
$socket->disconnect();
```

## Restrict the connection domain name
When we want to specify a specific domain name to connect to the page, we can use the $io->origins method to set the domain name whitelist.
```php
$io = new SocketIO(2020);
$io->origins('http://example.com:8080');
```
Separate multiple domain names with spaces, similar to
```php
$io = new SocketIO(2020);
$io->origins('http://workerman.net http://www.workerman.net');
```

## Support SSL(https wss)
There are two methods of SSL support, workerman native and nginx proxy
### workerman native support
SSL requires workerman>=3.3.7 phpsocket.io>=1.1.1

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
use Workerman\Worker;
use PHPSocketIO\SocketIO;

// Pass in the ssl option, including the path to the certificate
$context = array(
     'ssl' => array(
         'local_cert' => '/your/path/of/server.pem',
         'local_pk' => '/your/path/of/server.key',
         'verify_peer' => false,
     )
);
$io = new SocketIO(2120, $context);

$io->on('connection', function($socket)use($io){
   echo "new connection coming\n";
});

Worker::runAll();
```
**Note:**<br>
1. The certificate needs to verify the domain name, so the client must specify the domain name when connecting to successfully establish the link. <br>
2. The client can no longer use http when connecting. It must be changed to https, similar to the following.
```javascript
<script>
var socket = io('https://yoursite.com:2120');
//.....
</script>
```
### nginx proxy SSL

**Prerequisites and preparations:**

1. nginx has been installed, the version is no less than 1.3

2. Assume that phpsocket.io is listening on port 2120

3. The certificate (pem/crt file and key file) has been applied for and placed under /etc/nginx/conf.d/ssl

4. Plan to use nginx to open port 443 to provide SSL proxy service (the port can be modified as needed)

**nginx configuration is similar to the following:**
```
server {
   listen 443;

   ssl on;
   ssl_certificate /etc/ssl/server.pem;
   ssl_certificate_key /etc/ssl/server.key;
   ssl_session_timeout 5m;
   ssl_session_cache shared:SSL:50m;
   ssl_protocols SSLv3 SSLv2 TLSv1 TLSv1.1 TLSv1.2;
   ssl_ciphers ALL:!ADH:!EXPORT56:RC4+RSA:+HIGH:+MEDIUM:+LOW:+SSLv2:+EXP;

   location/socket.io
   {
     proxy_pass http://127.0.0.1:2120;
     proxy_http_version 1.1;
     proxy_set_header Upgrade $http_upgrade;
     proxy_set_header Connection "Upgrade";
     proxy_set_header X-Real-IP $remote_addr;
   }

   # location / {} Other configurations for the site...
}
```
**Note:**<br>
1. The certificate needs to verify the domain name, so the client must specify the domain name when connecting to successfully establish the link. <br>
2. The client can no longer use http when connecting. It must be changed to https, similar to the following.
```javascript
<script>
var socket = io('https://yoursite.com');
//.....
</script>