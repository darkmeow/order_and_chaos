
# Order & Chaos #

### Redes de Computadores ###

Proyecto Final de Redes de Computadores ELO322 2016-1. Uso de WebSocket + HTML5 para la creación de "Order & Chaos"

### Ejecución ###
* `Inicializar servidor`: 
```
php server.php start
```
* `Cliente`: a través de un navegador conectarse a la ip del servidor en el puerto 7000
```
ip_servidor:7000
```

### Protocolo ###
* `add user`: agrega usuario a la lista de jugadores
* `player type`: asignar order o chaos a un jugador
* `execute turn`: ejecuta el turno de un jugador
* `new message`: envía un mensaje al chat
* `typing`: evento para indicar que un jugador está escribiendo un mensaje
* `stop typing`: evento para indicar que un jugador dejó de escribir un mensaje
* `disconnect`: desconecta un usuario de la lista de jugadores
* `status`: indica el estado del juego en 6 posibles valores

  `wait`: esperando jugadores.
  
  `setup`: eligiendo lado (order o chaos).
  
  `order`: turno de order.
  
  `chaos`: turno de chaos.
  
  `win_order`: gana order.
  
  `win_chaos`: gana chaos.
  
### BUGS ###
* Se debe reiniciar el servidor al terminar el juego (por arreglar)
* Se debe arreglar resolución (no se ve el tablero completo en todos los navegadores, debe ser un tablero de 6x6)
