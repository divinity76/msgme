# msgme
cli tool to send a message. useful when you want to be notified of when a slow command has finished.

after configuring your ~/.msgme.ini , and installing it in some place like /usr/bin/msgme , it's as easy as


`msgme ping`

an example usage:
`dd if=/dev/urandom of=/dev/full bs=10M;msgme disk drive wipe completed`
just imagine that instead of writing to /dev/full, you're writing to /dev/sdb or whatever, and it takes hours, and you want to be notified when the wipe is complete. the code above will (using the Facebook relay) send you a message on facebook saying "disk wipe completed", once dd has finished.


```
hanshenrik@WebDevXubuntu:/$ dd if=/dev/urandom of=/dev/full bs=10M;msgme disk drive wipe completed
dd: error writing '/dev/full': No space left on device
1+0 records in
0+0 records out
0 bytes copied, 0,873993 s, 0,0 kB/s
sending message 'hanshenrik@WebDevXubuntu:disk drive wipe completed.' to facebook profile id 100000605585019
sent.
hanshenrik@WebDevXubuntu:/$ 
```
and indeed, my phone, synced to facebook messenger, pings me with "you got a new message!" :)


# example installation
```bash
sudo rm -fv /usr/bin/msgme_standalone.php /usr/bin/msgme
sudo wget -O /usr/bin/msgme_standalone.php https://github.com/divinity76/msgme/releases/download/0.5.0/msgme_standalone.php
sudo chmod 0555 /usr/bin/msgme_standalone.php
sudo ln -s /usr/bin/msgme_standalone.php /usr/bin/msgme
```
# example configuration
for general configurations info, try `msgme --help` ,
and for configuration for a specific relay, `msgme --help relay RelayName`  (like `msgme --help relay Facebook`).
here is a full working ~/.msgme.ini : 
```ini
[global]
relay=Facebook
message_prepend=hanshenrik@DevX: 
message_append=.
allowEmptyMessage=1
[Facebook]
email=pmmepubfacebook@gmail.com
password=ThePublicPassword1234567
recipientID=100000605585019
```
just change the recipientID to your own facebook ID. (if you dont know your FB ID, check http://findmyfbid.com/ or google it, or you can find it by looking for "uid" in facebook's html for viewing your own profile..) -
however, since facebook will not notice you about messages from strangers, but hide them in a section called "Message Requests", for the best effect, send a friend request to https://www.facebook.com/pmedpub.noticers.5 first. 
