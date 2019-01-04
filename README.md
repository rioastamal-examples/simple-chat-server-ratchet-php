# About

This repository are intended for learning purpose only. Simple chat server
built using Ratchet PHP. The goal of this application are building simple
chat server which has the following feature.

- Broadcast message
- Private message
- Who's online

It does not have client application. To connect to the server you can use
Telnet or Netcat or similar tools.

![Demo Simple Chat Server using Ratchet PHP](https://s3.amazonaws.com/rioastamal-assets/simple-chat-server-rachet-php/simple-php-chat-server.mov.gif)

# Installation

First is to clone this repository.

```
$ git clone https://github.com/rioastamal-examples/simple-chat-server-ratchet-php
```

Next is to install all dependencies required by chat server using Composer.

```
$ cd simple-chat-server-ratchet-php
$ composer install -vvv
```

# Running the App

To start the server you can execute a script on ./bin directory.

```
$ php ./bin/chat-server.php
Chat server running on 0.0.0.0:9191.
--
```

Now open another terminal and try to connect to port 9191. On this example I'm
using nc (Netcat) on MacOS. If you're using Linux or Windows you can also use
telnet to connect to server.

```
$ nc localhost 9191
**************************************
Welcome to the Club - Enjoy your chat!
**************************************
List of commands:

/nick NICKNAME          Register a nickname
/msg MESSAGE            Send a message to all
/pm NICKNAME MESSAGE    Send private message
/users                  Get list of online users
/help                   Show this message
/quit                   Quit chat club

```

To begin chatting you need to set your nickname first.

```
/nick rio123

SUCCESS: Your nickname has changed to rio123.
```

Now you can try to open other terminal and try to send message to each other.